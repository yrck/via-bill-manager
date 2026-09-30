<?php

namespace App\Billing;

use App\Access\BillingAccess;
use App\Models\User;
use App\Services\BillingSchedules;
use Illuminate\Support\Facades\DB;

class CustomerOverview
{
    public function data(User $user, int $organization, string $period): array
    {
        $access = app(BillingAccess::class);
        $accounts = $access->accounts($user, $organization)->get();
        $rules = DB::table('billing_schedules')->whereIn('utility_account_id', $accounts->pluck('id'))->get()->groupBy('utility_account_id');
        $scheduled = $excluded = $unknown = 0;
        $scheduledIds = [];
        foreach ($accounts as $account) {
            $rule = app(BillingSchedules::class)->rule($rules->get($account->id, collect()), $period);
            if (! $rule) {
                $unknown++;
            } elseif ($rule->mode === 'monthly') {
                $scheduled++;
                $scheduledIds[] = $account->id;
            } else {
                $excluded++;
            }
        }
        $query = app(CustomerBills::class)->query($user, $organization);
        $periodBills = (clone $query)->where('e.period', $period)->get();
        $expected = $periodBills->whereIn('utility_account_id', $scheduledIds)->where('expectation_source', 'schedule')->where('is_excluded', false);
        $received = $expected->whereNotNull('statement_id')->count();
        $missing = $periodBills->where('display_status', 'missing')->count();
        $awaiting = $periodBills->where('display_status', 'awaiting')->count();
        $pending = $scheduled - $expected->count();

        return [
            'period' => $period, 'scheduledCount' => $scheduled, 'excludedCount' => $excluded, 'unknownCount' => $unknown,
            'expectedReceived' => $received, 'missingCount' => $missing, 'awaitingCount' => $awaiting, 'pendingCount' => $pending,
            'reviewCount' => (clone $query)->where('s.status', 'review')->count(),
            'reviewBills' => (clone $query)->where('s.status', 'review')->orderBy('s.received_on')->limit(5)->get(),
            'missingBills' => (clone $query)->whereNull('s.id')->where('e.is_excluded', false)->where('e.expectation_source', '!=', 'intake')->whereRaw('COALESCE(e.missing_after, e.expected_by) < ?', [today()->toDateString()])->orderBy('e.expected_by')->limit(5)->get(),
            'lastReceived' => $access->statements($user, $organization)->max('received_on'),
        ];
    }
}
