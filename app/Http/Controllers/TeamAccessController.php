<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Services\TeamAccess;
use App\Services\TeamInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamAccessController extends Controller
{
    public function edit(Request $request, int $organization, int $membership, TeamInvitations $invitations, TeamAccess $access)
    {
        $account = $invitations->owner($request->user(), $organization);
        $member = $access->member($organization, $membership);
        abort_if($member->user_id === $account->owner_user_id, 403);

        return view('customer.member', [
            'organization' => $account, 'member' => $member,
            'locations' => app(BillingAccess::class)->locations($request->user(), $organization)->orderBy('locations.name')->get(),
            'grants' => DB::table('location_grants')->where('organization_membership_id', $membership)->pluck('location_id')->all(),
            'history' => DB::table('membership_access_changes')->where('organization_membership_id', $membership)->orderByDesc('version')->paginate(10),
        ]);
    }

    public function update(Request $request, int $organization, int $membership, TeamInvitations $invitations, TeamAccess $access)
    {
        $invitations->owner($request->user(), $organization);
        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'], 'role' => ['required', 'in:viewer,reviewer'],
            'is_active' => ['required', 'boolean'], 'locations' => ['sometimes', 'array', 'max:500'],
            'locations.*' => ['integer', 'distinct'], 'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $access->update($request->user(), $organization, $membership, $data);

        return redirect()->route('team.member.edit', [$organization, $membership])->with('status', 'Member access saved.');
    }
}
