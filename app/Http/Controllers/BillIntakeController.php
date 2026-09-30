<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Services\BillIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BillIntakeController extends Controller
{
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

        return $this->document($organization, $statement->id);
    }

    public function document(int $organization, int $statement)
    {
        $document = DB::table('bill_documents')->where('organization_id', $organization)->where('statement_id', $statement)->first();
        abort_unless($document && Storage::disk('bills')->exists($document->path), 404);

        return Storage::disk('bills')->download($document->path, 'statement-'.$statement.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
