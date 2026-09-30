<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Billing\CustomerBills;
use App\Services\BillIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BillIntakeController extends Controller
{
    public function correction(Request $request, int $organization, int $bill)
    {
        $account = app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->first();
        abort_unless($account && app(BillingAccess::class)->canReviewBill($request->user(), $organization, $bill), 404);
        $record = app(CustomerBills::class)->query($request->user(), $organization)->where('e.id', $bill)->first();
        $revision = DB::table('statement_revisions')->where('statement_id', $record->statement_id)->where('number', $record->revision_number)->first();

        return view('customer.bill-upload', [
            'organization' => $account, 'correction' => $record,
            'accounts' => app(BillingAccess::class)->accounts($request->user(), $organization, true)->where('id', $record->utility_account_id)->get(),
            'lineItems' => DB::table('statement_line_items')->where('statement_revision_id', $revision?->id)->orderBy('position')->get(),
        ]);
    }

    public function correct(Request $request, int $organization, int $bill, BillIntake $intake)
    {
        abort_unless(app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->exists() && app(BillingAccess::class)->canReviewBill($request->user(), $organization, $bill), 404);
        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $request->validate(['document' => ['nullable', 'file', 'mimes:pdf', 'max:8192']]);
        $file = $request->file('document');
        $intake->record($organization, $request->except('document'), $file?->getRealPath(), $file?->getClientOriginalName() ?? '', $request->user(), $bill);

        return redirect()->route('customer.bills.show', [$organization, $bill])->with('status', 'Correction saved as a new version. The statement needs review again.');
    }

    public function create(Request $request, int $organization)
    {
        $account = app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->where('m.role', 'reviewer')->first();
        abort_unless($account, 404);

        return view('customer.bill-upload', [
            'organization' => $account,
            'accounts' => app(BillingAccess::class)->accounts($request->user(), $organization, true)->where('commodity', 'Electricity')->orderBy('reference')->get(),
        ]);
    }

    public function store(Request $request, int $organization, BillIntake $intake)
    {
        abort_unless(app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->where('m.role', 'reviewer')->exists(), 404);
        $request->validate(['document' => ['required', 'file', 'mimes:pdf', 'max:8192']]);
        $file = $request->file('document');
        $id = $intake->record($organization, $request->except('document'), $file->getRealPath(), $file->getClientOriginalName(), $request->user());

        return redirect()->route('customer.bills.show', [$organization, $id])->with('status', 'Statement received and queued for review.');
    }

    public function download(Request $request, int $organization, int $bill)
    {
        abort_unless(app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->exists(), 404);
        $statement = app(BillingAccess::class)->statements($request->user(), $organization)->where('expected_bill_id', $bill)->first();
        abort_unless($statement, 404);

        $data = $request->validate(['revision' => ['nullable', 'integer', 'min:1']]);

        return $this->document($organization, $statement->id, (int) ($data['revision'] ?? $statement->revision_number));
    }

    public function document(int $organization, int $statement, int $number)
    {
        $revision = DB::table('statement_revisions')->where('statement_id', $statement)->where('number', $number)->first();
        abort_unless($revision, 404);
        $document = DB::table('bill_documents')->where('organization_id', $organization)->where('statement_id', $statement)->where('id', $revision->document_id)->first();
        abort_unless($document && Storage::disk('bills')->exists($document->path), 404);

        return Storage::disk('bills')->download($document->path, 'statement-'.$statement.'-v'.$number.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
