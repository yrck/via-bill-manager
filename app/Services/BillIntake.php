<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Billing\StatementCharges;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillIntake
{
    public function record(int $organization, array $input, ?string $file, string $filename, ?User $actor, ?int $bill = null): int
    {
        $data = Validator::make($input, [
            'utility_account_id' => ['required', 'integer'], 'invoice_number' => ['required', 'string', 'max:150'],
            'period' => ['required', 'date_format:Y-m-01'], 'issued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'service_start' => ['required', 'date_format:Y-m-d'], 'service_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:service_start', 'before_or_equal:issued_on'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
            'current_charges' => ['required', 'string', 'regex:/^-?\d{1,9}\.\d{2}$/'],
            'balance_forward' => ['required', 'string', 'regex:/^-?\d{1,9}\.\d{2}$/'],
            'amount_due' => ['required', 'string', 'regex:/^-?\d{1,9}\.\d{2}$/'],
            'usage_kwh' => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,3})?$/'],
            'version' => [$bill ? 'required' : 'nullable', 'integer', 'min:0'],
            'reason' => [$bill ? 'required' : 'nullable', 'string', 'min:10', 'max:2000'],
        ])->validate();
        if ((! $file && ! $bill) || ($file && (! is_file($file) || filesize($file) > 8 * 1024 * 1024 || file_get_contents($file, false, null, 0, 5) !== '%PDF-' || (new \finfo(FILEINFO_MIME_TYPE))->file($file) !== 'application/pdf'))) {
            throw ValidationException::withMessages(['document' => 'Provide a PDF no larger than 8 MiB.']);
        }
        $charges = StatementCharges::cents($data['current_charges']);
        $balance = StatementCharges::cents($data['balance_forward']);
        $due = StatementCharges::cents($data['amount_due']);
        $items = app(StatementCharges::class)->validate($input, $charges);
        if ($charges + $balance !== $due) {
            throw ValidationException::withMessages(['amount_due' => 'Current charges plus balance forward must equal amount due. Use a negative balance for credits.']);
        }
        $path = null;
        try {
            return DB::transaction(function () use ($organization, $data, $file, $filename, $actor, $charges, $balance, $due, $bill, $items, &$path) {
                $org = DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
                abort_unless($org && $org->is_active, 404);
                if ($actor) {
                    abort_unless(app(CustomerAccounts::class)->accessible($actor)->where('o.id', $organization)->exists(), 404);
                    $account = app(BillingAccess::class)->accounts($actor, $organization, true)->where('id', $data['utility_account_id'])->first();
                } else {
                    // Only the explicit trusted-console test import can use this path.
                    abort_unless(app()->runningInConsole() && $org->is_test_account, 403);
                    $account = DB::table('utility_accounts as a')->join('locations as l', 'l.id', '=', 'a.location_id')->join('portfolios as p', 'p.id', '=', 'l.portfolio_id')->where('p.organization_id', $organization)->where('a.id', $data['utility_account_id'])->first(['a.*']);
                }
                abort_unless($account, 404);
                if ($account->commodity !== 'Electricity') {
                    throw ValidationException::withMessages(['utility_account_id' => 'This first intake flow supports USD electricity statements only.']);
                }
                $current = null;
                if ($bill) {
                    abort_unless($actor, 403);
                    $current = app(BillingAccess::class)->statements($actor, $organization, true)->where('expected_bill_id', $bill)->lockForUpdate()->first();
                    abort_unless($current, 404);
                    $obligation = DB::table('expected_bills')->where('id', $bill)->first();
                    if ($obligation->utility_account_id !== $account->id || $obligation->period !== $data['period']) {
                        throw ValidationException::withMessages(['period' => 'A correction must keep the original account and reporting month.']);
                    }
                    if ($current->review_version !== (int) $data['version']) {
                        throw ValidationException::withMessages(['version' => 'This statement changed. Reload the current bill before correcting it.']);
                    }
                    if (! DB::table('statement_revisions')->where('statement_id', $current->id)->where('number', $current->revision_number)->exists()) {
                        DB::table('statement_revisions')->insert(['statement_id' => $current->id, 'number' => $current->revision_number, 'snapshot' => json_encode($current, JSON_THROW_ON_ERROR), 'reason' => 'Original statement preserved.', 'created_at' => now()]);
                    }
                }
                $hash = $file ? hash_file('sha256', $file) : null;
                $duplicate = $hash ? DB::table('bill_documents')->where('organization_id', $organization)->where('sha256', $hash)->first() : null;
                $invoice = DB::table('statements as s')->join('expected_bills as e', 'e.id', '=', 's.expected_bill_id')->where('e.utility_account_id', $account->id)->where('s.invoice_number', $data['invoice_number'])->when($current, fn ($query) => $query->where('s.id', '!=', $current->id))->exists();
                $historicalInvoice = DB::table('statement_revisions as r')->join('statements as s', 's.id', '=', 'r.statement_id')->join('expected_bills as e', 'e.id', '=', 's.expected_bill_id')
                    ->where('e.utility_account_id', $account->id)->where('r.snapshot->invoice_number', $data['invoice_number'])
                    ->when($current, fn ($query) => $query->where('s.id', '!=', $current->id))->exists();
                $expected = DB::table('expected_bills')->where('utility_account_id', $account->id)->where('period', $data['period'])->first();
                if (($duplicate && (! $current || $duplicate->statement_id !== $current->id)) || $invoice || $historicalInvoice || (! $current && $expected && DB::table('statements')->where('expected_bill_id', $expected->id)->exists())) {
                    throw ValidationException::withMessages(['document' => 'This PDF, invoice number, or reporting period already has a statement. Existing records were not changed.']);
                }
                $expectedId = $expected?->id ?? DB::table('expected_bills')->insertGetId(['utility_account_id' => $account->id, 'period' => $data['period'], 'expected_by' => $data['issued_on'], 'expectation_source' => 'intake']);
                $values = [
                    'expected_bill_id' => $expectedId, 'charges_cents' => $charges, 'balance_forward_cents' => $balance, 'amount_due_cents' => $due,
                    'currency' => 'USD', 'invoice_number' => $data['invoice_number'], 'issued_on' => $data['issued_on'],
                    'service_start' => $data['service_start'], 'service_end' => $data['service_end'], 'usage_kwh' => $data['usage_kwh'],
                    'received_on' => today()->toDateString(), 'due_on' => $data['due_on'], 'status' => 'review', 'title' => 'Source statement requires review',
                    'evidence' => 'Manually entered from the attached statement. Confirm account, service dates, usage, current charges, prior balance and amount due against the PDF.', 'owner' => 'Unassigned',
                    'revision_number' => $current ? $current->revision_number + 1 : 1,
                    'review_version' => $current ? $current->review_version + 1 : 0,
                ];
                if ($current) {
                    $statement = $current->id;
                    // Correcting entered values does not change when the source was received.
                    if (! $file || $duplicate) {
                        $values['received_on'] = $current->received_on;
                    }
                    DB::table('statements')->where('id', $statement)->update($values);
                } else {
                    $statement = DB::table('statements')->insertGetId($values);
                }
                $documentId = $duplicate?->id;
                if ($file && ! $duplicate) {
                    $path = 'bills/'.$organization.'/'.Str::uuid().'.pdf';
                    Storage::disk('bills')->put($path, file_get_contents($file));
                    $documentId = DB::table('bill_documents')->insertGetId(['organization_id' => $organization, 'statement_id' => $statement, 'uploaded_by' => $actor?->id, 'source' => $actor ? 'customer_upload' : 'trusted_test_import', 'sha256' => $hash, 'path' => $path, 'filename' => Str::limit(basename($filename), 240, ''), 'created_at' => now()]);
                } elseif (! $file && $current) {
                    $documentId = DB::table('statement_revisions')->where('statement_id', $statement)->where('number', $current->revision_number)->value('document_id');
                }
                $revision = DB::table('statement_revisions')->insertGetId([
                    'statement_id' => $statement, 'number' => $values['revision_number'], 'snapshot' => json_encode($values, JSON_THROW_ON_ERROR),
                    'document_id' => $documentId, 'actor_id' => $actor?->id, 'actor_name' => $actor?->name,
                    'reason' => $current ? $data['reason'] : 'Initial statement intake.', 'created_at' => now(),
                ]);
                foreach ($items as $item) {
                    DB::table('statement_line_items')->insert($item + ['statement_revision_id' => $revision]);
                }

                return $expectedId;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('bills')->delete($path);
            }
            throw $exception;
        }
    }
}
