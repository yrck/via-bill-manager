<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CustomerAccounts
{
    public function accessible(User $user): Builder
    {
        return DB::table('organizations as o')
            ->join('organization_memberships as m', 'm.organization_id', '=', 'o.id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('u.id', $user->id)->where('u.is_active', true)->whereNotNull('u.email_verified_at')
            ->where('m.is_active', true)->where('o.is_active', true)
            ->whereIn('m.role', ['viewer', 'reviewer'])
            ->select(['o.id', 'o.name', 'o.owner_user_id', 'o.is_test_account', 'o.legal_name', 'o.billing_address', 'm.role']);
    }
}
