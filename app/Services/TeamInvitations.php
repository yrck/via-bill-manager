<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamInvitations
{
    public function owner(User $user, int $organization): object
    {
        $record = DB::table('organizations as o')->join('organization_memberships as m', 'm.organization_id', '=', 'o.id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('o.id', $organization)->where('o.owner_user_id', $user->id)->where('o.is_active', true)
            ->where('m.user_id', $user->id)->where('m.is_active', true)->where('u.is_active', true)
            ->whereNotNull('u.email_verified_at')->first(['o.*']);
        abort_unless($record, 404);

        return $record;
    }

    public function send(User $owner, int $organization, string $email, string $role, array $locations): void
    {
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($owner, $organization, $email, $role, $locations, $token) {
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            $record = $this->owner($owner, $organization);
            $allowed = app(BillingAccess::class)->locations($owner, $organization)->pluck('locations.id')->all();
            if (array_diff($locations, $allowed)) {
                throw ValidationException::withMessages(['locations' => 'Select only properties you can access in this organization.']);
            }
            $member = DB::table('organization_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.organization_id', $organization)->where('u.email', $email)->exists();
            $pending = DB::table('organization_invitations')->where('organization_id', $organization)->where('email', $email)
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
            if ($member || $pending) {
                throw ValidationException::withMessages(['email' => 'This address already has a membership or pending invitation.']);
            }
            $id = DB::table('organization_invitations')->insertGetId([
                'organization_id' => $organization, 'invited_by' => $owner->id, 'email' => $email, 'role' => $role,
                'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_unique($locations) as $location) {
                DB::table('invitation_locations')->insert(['organization_invitation_id' => $id, 'location_id' => $location]);
            }

            return (object) ['id' => $id, 'name' => $record->name];
        });
        try {
            Notification::route('mail', $email)->notify(new OrganizationInvitation($invitation->name, $token));
        } catch (\Throwable $exception) {
            DB::table('organization_invitations')->where('id', $invitation->id)->update(['revoked_at' => now()]);
            throw ValidationException::withMessages(['email' => 'The invitation could not be sent. Please try again later.']);
        }
    }

    public function find(string $token, bool $lock = false): object
    {
        $query = DB::table('organization_invitations')->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
        $invitation = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($invitation, 404, 'This invitation is no longer available. Ask the owner for a new invitation.');
        $owner = User::find($invitation->invited_by);
        abort_unless($owner, 404);
        $this->owner($owner, $invitation->organization_id);

        return $invitation;
    }

    public function accept(User $user, string $token): int
    {
        return DB::transaction(function () use ($user, $token) {
            $invitation = $this->find($token, true);
            $user = $user->fresh();
            abort_unless($user && $user->is_active && $user->hasVerifiedEmail() && $user->email === $invitation->email, 403);
            // Never use a link to reactivate or change an existing membership.
            if (DB::table('organization_memberships')->where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'You already have a membership. Contact the organization owner.']);
            }
            $locations = DB::table('invitation_locations')->where('organization_invitation_id', $invitation->id)->pluck('location_id')->all();
            $allowed = app(BillingAccess::class)->locations(User::findOrFail($invitation->invited_by), $invitation->organization_id)->pluck('locations.id')->all();
            if (array_diff($locations, $allowed)) {
                throw ValidationException::withMessages(['invitation' => 'Property access has changed. Ask the owner for a new invitation.']);
            }
            $membership = DB::table('organization_memberships')->insertGetId([
                'organization_id' => $invitation->organization_id, 'user_id' => $user->id, 'role' => $invitation->role,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($locations as $location) {
                DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $location, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('organization_invitations')->where('id', $invitation->id)->update(['accepted_at' => now(), 'updated_at' => now()]);

            return $invitation->organization_id;
        });
    }
}
