<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Billing\CustomerBills;
use App\Models\User;
use App\Services\BillExceptions;
use App\Services\BillValidation;
use App\Services\CustomerBillReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerBillController extends Controller
{
    private function account(Request $request, int $organization): object
    {
        $account = app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->first();
        abort_unless($account, 404);

        return $account;
    }

    public function index(Request $request, int $organization)
    {
        return $this->indexFor($request, $request->user(), $this->account($request, $organization));
    }

    public function indexFor(Request $request, User $user, object $organization, ?object $customerView = null)
    {
        $filters = $request->validate(['location' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:review,verified,missing,awaiting'], 'q' => ['nullable', 'string', 'max:150'], 'period' => ['nullable', 'date_format:Y-m-01']]);
        $query = app(CustomerBills::class)->query($user, $organization->id);
        if ($period = $filters['period'] ?? null) {
            $query->where('e.period', $period);
        }
        if ($location = $filters['location'] ?? null) {
            abort_unless(app(BillingAccess::class)->locations($user, $organization->id)->where('locations.id', $location)->exists(), 404);
            $query->where('l.id', $location);
        }
        if ($status = $filters['status'] ?? null) {
            if (in_array($status, ['review', 'verified'])) {
                $query->where('s.status', $status);
            } else {
                $query->whereNull('s.id');
                if ($status === 'missing') {
                    $query->where('e.expectation_source', '!=', 'intake')->whereRaw('COALESCE(e.missing_after, e.expected_by) < ?', [today()->toDateString()]);
                } else {
                    $query->where(fn ($where) => $where->where('e.expectation_source', 'intake')->orWhereRaw('COALESCE(e.missing_after, e.expected_by) >= ?', [today()->toDateString()]));
                }
            }
        }
        if ($search = $filters['q'] ?? null) {
            $query->where(function ($where) use ($search) {
                $where->where('a.reference', 'like', '%'.$search.'%')->orWhere('a.supplier', 'like', '%'.$search.'%');
            });
        }

        return view('customer.bills', [
            'organization' => $organization, 'customerView' => $customerView, 'filters' => $filters,
            'locations' => app(BillingAccess::class)->locations($user, $organization->id)->orderBy('locations.name')->get(),
            'bills' => $query->orderByDesc('e.period')->orderByDesc('e.id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Request $request, int $organization, int $bill)
    {
        return $this->showFor($request->user(), $this->account($request, $organization), $bill, null, $request);
    }

    public function showFor(User $user, object $organization, int $bill, ?object $customerView = null, ?Request $request = null)
    {
        $record = app(CustomerBills::class)->query($user, $organization->id, true)->where('e.id', $bill)->first();
        abort_unless($record, 404);
        $selection = $request?->validate(['revision' => ['nullable', 'integer', 'min:1']]) ?? [];
        $currentNumber = $record->revision_number;
        $number = (int) ($selection['revision'] ?? $currentNumber);
        $revision = DB::table('statement_revisions')->where('statement_id', $record->statement_id)->where('number', $number)->first();
        abort_if(isset($selection['revision']) && ! $revision, 404);
        $historical = $number !== (int) $currentNumber;
        if ($historical && $revision) {
            $snapshot = json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR);
            foreach (['charges_cents', 'currency', 'received_on', 'due_on', 'invoice_number', 'issued_on', 'service_start', 'service_end', 'balance_forward_cents', 'amount_due_cents', 'usage_kwh', 'evidence'] as $field) {
                $record->$field = $snapshot[$field] ?? null;
            }
            $record->display_status = 'superseded';
        }

        $exception = DB::table('bill_exceptions')->where('expected_bill_id', $bill)->first();

        return view('customer.bill', [
            'validationRuns' => DB::table('bill_validation_runs')->where('statement_id', $record->statement_id)->where('source_revision', $number)->orderByDesc('id')->paginate(5, ['*'], 'checks')->withQueryString(),
            'exception' => $exception,
            'canManageException' => ! $customerView && ! $historical && app(BillingAccess::class)->expectedBills($user, $organization->id, true)->where('id', $bill)->exists(),
            'assignees' => app(BillExceptions::class)->assignees($organization->id, $bill),
            'exceptionEvents' => DB::table('bill_exception_events')->where('bill_exception_id', $exception?->id)->orderByDesc('version')->paginate(10, ['*'], 'events')->withQueryString(),
            'organization' => $organization, 'customerView' => $customerView, 'bill' => $record,
            'canReview' => ! $customerView && ! $historical && app(BillingAccess::class)->canReviewBill($user, $organization->id, $bill),
            'document' => $revision ? DB::table('bill_documents')->where('organization_id', $organization->id)->where('statement_id', $record->statement_id)->where('id', $revision->document_id)->first() : null,
            'revisions' => DB::table('statement_revisions')->where('statement_id', $record->statement_id)->orderByDesc('number')->get(),
            'lineItems' => DB::table('statement_line_items')->where('statement_revision_id', $revision?->id)->orderBy('position')->get(),
            'selectedRevision' => $number, 'historical' => $historical,
            'history' => DB::table('customer_review_decisions')->where('statement_id', $record->statement_id)->orderByDesc('version')->paginate(10)->withQueryString(),
        ]);
    }

    public function validateBill(Request $request, int $organization, int $bill, BillValidation $validation)
    {
        $this->account($request, $organization);
        abort_if($request->session()->has('customer_view_id'), 403);
        $validation->refresh($request->user(), $organization, $bill);

        return redirect()->route('customer.bills.show', [$organization, $bill])->with('status', 'Validation refreshed for this utility account. Review and investigation decisions are unchanged.');
    }

    public function exception(Request $request, int $organization, int $bill, BillExceptions $exceptions)
    {
        $this->account($request, $organization);
        abort_if($request->session()->has('customer_view_id'), 403);
        $exceptions->record($request->user(), $organization, $bill, $request->all());

        return redirect()->route('customer.bills.show', [$organization, $bill])->with('status', 'Investigation saved. Bill verification is unchanged.');
    }

    public function exceptions(Request $request, int $organization)
    {
        return $this->exceptionsFor($request, $request->user(), $this->account($request, $organization));
    }

    public function exceptionsFor(Request $request, User $user, object $organization, ?object $customerView = null)
    {
        $filters = $request->validate(['state' => ['nullable', 'in:open,resolved'], 'assignment' => ['nullable', 'in:mine,unassigned'], 'overdue' => ['nullable', 'in:1']]);
        $query = app(CustomerBills::class)->query($user, $organization->id, true)
            ->join('bill_exceptions as x', 'x.expected_bill_id', '=', 'e.id')
            ->where('x.status', $filters['state'] ?? 'open')
            ->addSelect('x.status as exception_status', 'x.assignee_name', 'x.next_action', 'x.due_on as action_due', 'x.source_revision', 'x.source_validation_run_id');
        if (($filters['assignment'] ?? null) === 'mine') {
            $query->where('x.assignee_id', $user->id);
        } elseif (($filters['assignment'] ?? null) === 'unassigned') {
            $query->whereNull('x.assignee_id');
        }
        if ($filters['overdue'] ?? false) {
            $query->where('x.status', 'open')->where('x.due_on', '<', today()->toDateString());
        }

        return view('customer.exceptions', ['organization' => $organization, 'customerView' => $customerView, 'filters' => $filters,
            'exceptions' => $query->orderBy('x.due_on')->orderBy('x.id')->paginate(25)->withQueryString()]);
    }

    public function review(Request $request, int $organization, int $bill, CustomerBillReview $review)
    {
        $this->account($request, $organization);
        abort_unless(app(BillingAccess::class)->canReviewBill($request->user(), $organization, $bill), 404);
        $request->merge(['note' => trim((string) $request->input('note'))]);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'decision' => ['required', 'in:verify,reopen'], 'note' => ['required', 'string', 'min:10', 'max:2000']]);
        $review->record($request->user(), $organization, $bill, $data);

        return redirect()->route('customer.bills.show', [$organization, $bill])->with('status', 'Review saved.');
    }
}
