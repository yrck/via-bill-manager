<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillIntake
{
    public function record(int $organization, array $input, string $file, string $filename, ?User $actor): int
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
        ])->validate();
        if (! is_file($file) || filesize($file) > 8 * 1024 * 1024 || file_get_contents($file, false, null, 0, 5) !== '%PDF-' || (new \finfo(FILEINFO_MIME_TYPE))->file($file) !== 'application/pdf') {
            throw ValidationException::withMessages(['document' => 'Provide a PDF no larger than 8 MiB.']);
        }
        $charges = $this->cents($data['current_charges']);
        $balance = $this->cents($data['balance_forward']);
        $due = $this->cents($data['amount_due']);
        if ($charges + $balance !== $due) {
            throw ValidationException::withMessages(['amount_due' => 'Current charges plus balance forward must equal amount due. Use a negative balance for credits.']);
        }
        $path = null;
        try {
            return DB::transaction(function () use ($organization, $data, $file, $filename, $actor, $charges, $balance, $due, &$path) {
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
                $hash = hash_file('sha256', $file);
                $duplicate = DB::table('bill_documents')->where('organization_id', $organization)->where('sha256', $hash)->exists();
                $invoice = DB::table('statements as s')->join('expected_bills as e', 'e.id', '=', 's.expected_bill_id')->where('e.utility_account_id', $account->id)->where('s.invoice_number', $data['invoice_number'])->exists();
                $expected = DB::table('expected_bills')->where('utility_account_id', $account->id)->where('period', $data['period'])->first();
                if ($duplicate || $invoice || ($expected && DB::table('statements')->where('expected_bill_id', $expected->id)->exists())) {
                    throw ValidationException::withMessages(['document' => 'This PDF, invoice number, or reporting period already has a statement. Existing records were not changed.']);
                }
                $expectedId = $expected?->id ?? DB::table('expected_bills')->insertGetId(['utility_account_id' => $account->id, 'period' => $data['period'], 'expected_by' => $data['issued_on'], 'expectation_source' => 'intake']);
                $statement = DB::table('statements')->insertGetId([
                    'expected_bill_id' => $expectedId, 'charges_cents' => $charges, 'balance_forward_cents' => $balance, 'amount_due_cents' => $due,
                    'currency' => 'USD', 'invoice_number' => $data['invoice_number'], 'issued_on' => $data['issued_on'],
                    'service_start' => $data['service_start'], 'service_end' => $data['service_end'], 'usage_kwh' => $data['usage_kwh'],
                    'received_on' => today()->toDateString(), 'due_on' => $data['due_on'], 'status' => 'review', 'title' => 'Source statement requires review',
                    'evidence' => 'Manually entered from the attached statement. Confirm account, service dates, usage, current charges, prior balance and amount due against the PDF.', 'owner' => 'Unassigned',
                ]);
                $path = 'bills/'.$organization.'/'.Str::uuid().'.pdf';
                Storage::disk('bills')->put($path, file_get_contents($file));
                DB::table('bill_documents')->insert(['organization_id' => $organization, 'statement_id' => $statement, 'uploaded_by' => $actor?->id, 'source' => $actor ? 'customer_upload' : 'trusted_test_import', 'sha256' => $hash, 'path' => $path, 'filename' => Str::limit(basename($filename), 240, ''), 'created_at' => now()]);

                return $expectedId;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('bills')->delete($path);
            }
            throw $exception;
        }
    }

    private function cents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = explode('.', ltrim($value, '-'));

        return ((int) $whole * 100 + (int) $fraction) * ($negative ? -1 : 1);
    }
}
