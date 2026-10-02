<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BillValidation
{
    public const VERSION = 'electricity-monthly-v1';

    public function refresh(User $user, int $organization, int $bill): void
    {
        DB::transaction(function () use ($user, $organization, $bill) {
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            abort_unless(app(CustomerAccounts::class)->accessible($user)->where('o.id', $organization)->exists(), 404);
            $expected = app(BillingAccess::class)->expectedBills($user, $organization, true)->where('id', $bill)->first();
            abort_unless($expected, 404);
            $this->evaluateAccount($organization, $expected->utility_account_id);
        });
    }

    /** Internal intake/refresh operation; serializes with corrections and access changes. */
    public function evaluateAccount(int $organization, int $account): void
    {
        DB::transaction(function () use ($organization, $account) {
            $org = DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            abort_unless($org && $org->is_active, 404);
            $accountRecord = DB::table('utility_accounts as a')->join('locations as l', 'l.id', '=', 'a.location_id')
                ->join('portfolios as p', 'p.id', '=', 'l.portfolio_id')->where('p.organization_id', $organization)->where('a.id', $account)->first(['a.*']);
            abort_unless($accountRecord, 404);
            $statements = DB::table('statements as s')->join('expected_bills as e', 'e.id', '=', 's.expected_bill_id')
                ->where('e.utility_account_id', $account)->orderBy('e.period')->get(['s.*', 'e.period']);
            $previous = null;
            foreach ($statements as $statement) {
                $inputs = ['commodity' => $accountRecord->commodity, 'current' => $this->input($statement), 'previous' => $previous ? $this->input($previous) : null];
                $fingerprint = hash('sha256', json_encode([self::VERSION, $inputs], JSON_THROW_ON_ERROR));
                $run = DB::table('bill_validation_runs')->where('statement_id', $statement->id)->where('fingerprint', $fingerprint)->value('id');
                if (! $run) {
                    $run = DB::table('bill_validation_runs')->insertGetId([
                        'statement_id' => $statement->id, 'source_revision' => $statement->revision_number,
                        'rules_version' => self::VERSION, 'fingerprint' => $fingerprint,
                        'inputs' => json_encode($inputs, JSON_THROW_ON_ERROR),
                        'findings' => json_encode($this->findings($inputs), JSON_THROW_ON_ERROR), 'created_at' => now(),
                    ]);
                }
                DB::table('statements')->where('id', $statement->id)->update(['validation_run_id' => $run]);
                $previous = $statement;
            }
        });
    }

    private function input(object $statement): array
    {
        return array_intersect_key((array) $statement, array_flip(['id', 'expected_bill_id', 'revision_number', 'period', 'service_start', 'service_end', 'usage_kwh', 'charges_cents', 'currency']));
    }

    private function days(array $source): ?int
    {
        if (! $source['service_start'] || ! $source['service_end'] || $source['service_end'] < $source['service_start']) {
            return null;
        }

        return (int) CarbonImmutable::parse($source['service_start'])->diffInDays(CarbonImmutable::parse($source['service_end'])) + 1;
    }

    private function findings(array $inputs): array
    {
        $current = $inputs['current'];
        $previous = $inputs['previous'];
        $days = $this->days($current);
        $eligible = $inputs['commodity'] === 'Electricity' && $current['currency'] === 'USD';
        $adjacent = $previous && CarbonImmutable::parse($current['period'])->subMonth()->toDateString() === $previous['period'];
        $priorDays = $previous ? $this->days($previous) : null;
        $baseline = $eligible && $adjacent && $days && $priorDays && $previous['currency'] === $current['currency'];
        $results = [];
        $results[] = ['rule' => 'Service duration', 'status' => ! $eligible || ! $days ? 'insufficient' : ($days < 20 || $days > 40 ? 'warning' : 'clear'),
            'explanation' => ! $eligible || ! $days ? 'USD electricity and valid service dates are required.' : "{$days} inclusive service days. Flagged below 20 or above 40 days; this is a screening threshold, not a billing requirement."];
        if (! $baseline) {
            $results[] = ['rule' => 'Service continuity', 'status' => 'insufficient', 'explanation' => 'Requires valid service dates on USD electricity bills in adjacent reporting months. No missing service is inferred from unavailable history.'];
        } else {
            $gap = (int) CarbonImmutable::parse($previous['service_end'])->diffInDays(CarbonImmutable::parse($current['service_start'])) - 1;
            $ordered = $current['service_start'] > $previous['service_start'];
            $results[] = ['rule' => 'Service continuity', 'status' => $gap === 0 && $ordered ? 'clear' : 'warning',
                'explanation' => ! $ordered ? 'Service dates do not progress with reporting months; inspect both statements.' : ($gap > 0 ? "{$gap} uncovered days between the recorded periods." : ($gap < 0 ? 'Recorded service periods overlap; inspect the date boundaries on both statements.' : 'The recorded service periods are consecutive.'))];
        }
        foreach (['usage_kwh' => 'Usage per day', 'charges_cents' => 'Current charges per day'] as $field => $label) {
            if (! $baseline || $current[$field] === null || $previous[$field] === null || $previous[$field] <= 0 || $current[$field] < 0) {
                $results[] = ['rule' => $label, 'status' => 'insufficient', 'explanation' => 'Requires adjacent USD electricity bills with valid service dates, a positive baseline and nonnegative current values. Missing, zero or credit baselines cannot establish a percentage comparison.'];

                continue;
            }
            $scale = $field === 'charges_cents' ? 100 : 1;
            $now = $current[$field] / $days / $scale;
            $prior = $previous[$field] / $priorDays / $scale;
            $change = ($now / $prior - 1) * 100;
            $unit = $field === 'charges_cents' ? 'USD/day' : 'kWh/day';
            $results[] = ['rule' => $label, 'status' => abs($change) > 30 + 0.0000001 ? 'warning' : 'clear',
                'explanation' => number_format($now, 4)." {$unit} versus ".number_format($prior, 4)." {$unit}; ".number_format($change, 2).'% change. Flagged when the absolute change exceeds 30%. No weather, occupancy or tariff adjustment is applied.'];
        }

        return $results;
    }
}
