<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Billing\CustomerBills;
use App\Models\User;
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
        $filters = $request->validate(['location' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:review,verified,missing,awaiting'], 'q' => ['nullable', 'string', 'max:150']]);
        $query = app(CustomerBills::class)->query($user, $organization->id);
        if ($location = $filters['location'] ?? null) {
            abort_unless(app(BillingAccess::class)->locations($user, $organization->id)->where('locations.id', $location)->exists(), 404);
            $query->where('l.id', $location);
        }
        if ($status = $filters['status'] ?? null) {
            if (in_array($status, ['review', 'verified'])) {
                $query->where('s.status', $status);
            } else {
                $query->whereNull('s.id')->where('e.expected_by', $status === 'missing' ? '<' : '>=', today()->toDateString());
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
        return $this->showFor($request->user(), $this->account($request, $organization), $bill);
    }

    public function showFor(User $user, object $organization, int $bill, ?object $customerView = null)
    {
        $record = app(CustomerBills::class)->query($user, $organization->id)->where('e.id', $bill)->first();
        abort_unless($record, 404);

        return view('customer.bill', [
            'organization' => $organization, 'customerView' => $customerView, 'bill' => $record,
            'canReview' => ! $customerView && app(BillingAccess::class)->canReviewBill($user, $organization->id, $bill),
            'document' => DB::table('bill_documents')->where('organization_id', $organization->id)->where('statement_id', $record->statement_id)->first(),
            'history' => DB::table('customer_review_decisions')->where('statement_id', $record->statement_id)->orderByDesc('version')->paginate(10),
        ]);
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
