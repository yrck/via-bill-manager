<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deny-by-default query boundary for the future authenticated application.
 * The isolated fictional demo deliberately uses App\Demo\BillingDataset instead.
 */
class BillingAccess
{
    public function locations(?User $user, int $organizationId, bool $forReview = false): Builder
    {
        $query = DB::table('locations')->select('locations.*')
            ->join('portfolios', 'portfolios.id', '=', 'locations.portfolio_id')
            ->where('portfolios.organization_id', $organizationId)
            ->whereExists(fn (Builder $organizations) => $organizations->selectRaw('1')->from('organizations')->whereColumn('organizations.id', 'portfolios.organization_id')->where('organizations.is_active', true));

        if (! $user || ! $user->exists) {
            return $query->whereRaw('1 = 0');
        }

        // Read user/membership state from the DB on every query. Long-lived sessions
        // and jobs must not retain access through a stale hydrated User instance.
        return $query->whereExists(function (Builder $grants) use ($user, $organizationId, $forReview) {
            $grants->selectRaw('1')->from('location_grants as g')
                ->join('organization_memberships as m', 'm.id', '=', 'g.organization_membership_id')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->whereColumn('g.location_id', 'locations.id')
                ->where('m.organization_id', $organizationId)
                ->where('m.user_id', $user->getKey())
                ->where('m.is_active', true)->where('u.is_active', true)
                ->whereIn('m.role', $forReview ? ['reviewer'] : ['viewer', 'reviewer']);
        });
    }

    public function servicePoints(?User $user, int $organizationId): Builder
    {
        return DB::table('service_points')->where('organization_id', $organizationId)->whereIn('location_id',
            $this->locations($user, $organizationId)->select('locations.id')
        );
    }

    public function accounts(?User $user, int $organizationId, bool $forReview = false): Builder
    {
        return DB::table('utility_accounts')->whereIn('location_id',
            $this->locations($user, $organizationId, $forReview)->select('locations.id')
        );
    }

    public function expectedBills(?User $user, int $organizationId, bool $forReview = false): Builder
    {
        return DB::table('expected_bills')->whereIn('utility_account_id',
            $this->accounts($user, $organizationId, $forReview)->select('utility_accounts.id')
        );
    }

    public function statements(?User $user, int $organizationId, bool $forReview = false): Builder
    {
        return DB::table('statements')->whereIn('expected_bill_id',
            $this->expectedBills($user, $organizationId, $forReview)->select('expected_bills.id')
        );
    }

    public function canViewBill(?User $user, int $organizationId, int $expectedBillId): bool
    {
        return $this->expectedBills($user, $organizationId)->where('id', $expectedBillId)->exists();
    }

    public function canReviewBill(?User $user, int $organizationId, int $expectedBillId): bool
    {
        // A missing obligation can be viewed, but there is no statement to review.
        return $this->statements($user, $organizationId, true)->where('expected_bill_id', $expectedBillId)->exists();
    }
}
