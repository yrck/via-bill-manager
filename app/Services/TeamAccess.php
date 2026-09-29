<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamAccess
{
    public function member(int $organization, int $membership): object
    {
        $member = DB::table('organization_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.organization_id', $organization)->where('m.id', $membership)
            ->first(['m.*', 'u.name', 'u.email']);
        abort_unless($member, 404);

        return $member;
    }

    public function update(User $owner, int $organization, int $membership, array $data): void
    {
        DB::transaction(function () use ($owner, $organization, $membership, $data) {
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            $account = app(TeamInvitations::class)->owner($owner, $organization);
            $member = DB::table('organization_memberships')->where('organization_id', $organization)->where('id', $membership)->lockForUpdate()->first();
            abort_unless($member, 404);
            abort_if($member->user_id === $account->owner_user_id, 403, 'Owner access cannot be changed here.');
            if ($member->access_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Access changed since you opened this page. Reload before saving.']);
            }
            $locations = array_map('intval', $data['locations'] ?? []);
            sort($locations);
            $allowed = app(BillingAccess::class)->locations($owner, $organization)->pluck('locations.id')->all();
            if (array_diff($locations, $allowed)) {
                throw ValidationException::withMessages(['locations' => 'Select only properties you can access in this account.']);
            }
            $before = ['role' => $member->role, 'is_active' => (bool) $member->is_active, 'locations' => DB::table('location_grants')->where('organization_membership_id', $membership)->orderBy('location_id')->pluck('location_id')->all()];
            // Deactivation also removes grants so restoring access requires an explicit selection.
            $after = ['role' => $data['role'], 'is_active' => (bool) $data['is_active'], 'locations' => $data['is_active'] ? $locations : []];
            if ($before === $after) {
                return;
            }
            DB::table('organization_memberships')->where('id', $membership)->update(['role' => $after['role'], 'is_active' => $after['is_active'], 'access_version' => $member->access_version + 1, 'updated_at' => now()]);
            DB::table('location_grants')->where('organization_membership_id', $membership)->delete();
            foreach ($after['locations'] as $location) {
                DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $location, 'created_at' => now(), 'updated_at' => now()]);
            }
            $before['location_names'] = app(BillingAccess::class)->locations($owner, $organization)->whereIn('locations.id', $before['locations'])->orderBy('locations.id')->pluck('locations.name')->all();
            $after['location_names'] = app(BillingAccess::class)->locations($owner, $organization)->whereIn('locations.id', $after['locations'])->orderBy('locations.id')->pluck('locations.name')->all();
            DB::table('membership_access_changes')->insert([
                'organization_membership_id' => $membership, 'actor_id' => $owner->id, 'actor_name' => $owner->name,
                'version' => $member->access_version + 1, 'before' => json_encode($before), 'after' => json_encode($after),
                'reason' => $data['reason'], 'created_at' => now(),
            ]);
        });
    }
}
