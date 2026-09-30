<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillingSchedules
{
    public function save(User $actor, int $organization, int $account, array $input): void
    {
        $data = Validator::make($input, [
            'effective_month' => ['required', 'date_format:Y-m-01', 'after_or_equal:'.today()->startOfMonth()->subMonths(24)->toDateString(), 'before_or_equal:'.today()->startOfMonth()->addMonths(12)->toDateString()],
            'mode' => ['required', 'in:monthly,not_expected'],
            'receipt_day' => ['required_if:mode,monthly', 'nullable', 'integer', 'between:1,31'],
            'month_offset' => ['required_if:mode,monthly', 'nullable', 'integer', 'in:0,1'],
            'grace_days' => ['required_if:mode,monthly', 'nullable', 'integer', 'between:0,30'],
            'version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ])->validate();
        DB::transaction(function () use ($actor, $organization, $account, $data) {
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            app(TeamInvitations::class)->owner($actor, $organization);
            abort_unless(app(BillingAccess::class)->accounts($actor, $organization)->where('id', $account)->exists(), 404);
            $version = DB::table('billing_schedules')->where('utility_account_id', $account)->max('id') ?? 0;
            if ((int) $data['version'] !== (int) $version) {
                throw ValidationException::withMessages(['version' => 'The schedule changed. Reload before saving another change.']);
            }
            DB::table('billing_schedules')->insert([
                'utility_account_id' => $account, 'effective_month' => $data['effective_month'], 'mode' => $data['mode'],
                'receipt_day' => $data['mode'] === 'monthly' ? $data['receipt_day'] : null,
                'month_offset' => $data['mode'] === 'monthly' ? $data['month_offset'] : null,
                'grace_days' => $data['mode'] === 'monthly' ? $data['grace_days'] : null,
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $data['reason'], 'created_at' => now(),
            ]);
            $this->materialize($account);
        });
    }

    public function rule(Collection $rules, string $month): ?object
    {
        return $rules->filter(fn ($rule) => $rule->effective_month <= $month)
            ->sort(fn ($a, $b) => [$b->effective_month, $b->id] <=> [$a->effective_month, $a->id])->first();
    }

    // Call under the owning organization's lock, both for configuration and scheduled refresh.
    public function materialize(int $account): void
    {
        $rules = DB::table('billing_schedules')->where('utility_account_id', $account)->get();
        if ($rules->isEmpty()) {
            return;
        }
        $through = CarbonImmutable::today()->startOfMonth()->addMonth();
        $start = CarbonImmutable::parse($rules->min('effective_month'))->startOfMonth();
        for ($month = $start; $month <= $through; $month = $month->addMonth()) {
            $period = $month->toDateString();
            $rule = $this->rule($rules, $period);
            if (! $rule) {
                continue;
            }
            $existing = DB::table('expected_bills')->where('utility_account_id', $account)->where('period', $period)->first();
            if ($rule->mode === 'not_expected') {
                if ($existing) {
                    DB::table('expected_bills')->where('id', $existing->id)->update(['is_excluded' => true, 'billing_schedule_id' => $rule->id]);
                }

                continue;
            }
            $receiptMonth = $month->addMonths((int) $rule->month_offset);
            $expected = $receiptMonth->day(min((int) $rule->receipt_day, $receiptMonth->daysInMonth));
            $values = ['expected_by' => $expected->toDateString(), 'missing_after' => $expected->addDays((int) $rule->grace_days)->toDateString(), 'expectation_source' => 'schedule', 'billing_schedule_id' => $rule->id, 'is_excluded' => false];
            if ($existing) {
                DB::table('expected_bills')->where('id', $existing->id)->update($values);
            } else {
                DB::table('expected_bills')->insert($values + ['utility_account_id' => $account, 'period' => $period]);
            }
        }
    }

    public function refreshOrganization(int $organization): void
    {
        DB::transaction(function () use ($organization) {
            $record = DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            if (! $record?->is_active) {
                return;
            }
            $accounts = DB::table('utility_accounts as a')->join('locations as l', 'l.id', '=', 'a.location_id')->join('portfolios as p', 'p.id', '=', 'l.portfolio_id')->where('p.organization_id', $organization)->whereIn('a.id', DB::table('billing_schedules')->select('utility_account_id'))->pluck('a.id');
            foreach ($accounts as $account) {
                $this->materialize($account);
            }
        });
    }
}
