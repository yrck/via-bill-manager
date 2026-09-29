<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Services\TeamInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request, int $organization, TeamInvitations $invitations)
    {
        return view('customer.team', [
            'organization' => $invitations->owner($request->user(), $organization),
            'members' => DB::table('organization_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.organization_id', $organization)->orderBy('u.name')->get(['m.id', 'm.user_id', 'm.role', 'm.is_active', 'u.name', 'u.email']),
            'invitations' => DB::table('organization_invitations')->where('organization_id', $organization)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->orderByDesc('id')->get(),
            'locations' => app(BillingAccess::class)->locations($request->user(), $organization)->orderBy('locations.name')->get(),
        ]);
    }

    public function invite(Request $request, int $organization, TeamInvitations $invitations)
    {
        $invitations->owner($request->user(), $organization);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'role' => ['required', 'in:viewer,reviewer'], 'locations' => ['sometimes', 'array', 'max:500'], 'locations.*' => ['integer', 'distinct']]);
        $invitations->send($request->user(), $organization, $data['email'], $data['role'], $data['locations'] ?? []);

        return back()->with('status', 'Invitation sent. It expires in seven days.');
    }

    public function cancel(Request $request, int $organization, int $invitation, TeamInvitations $invitations)
    {
        DB::transaction(function () use ($request, $organization, $invitation, $invitations) {
            $invitations->owner($request->user(), $organization);
            $row = DB::table('organization_invitations')->where('id', $invitation)->where('organization_id', $organization)->lockForUpdate()->first();
            abort_unless($row && ! $row->accepted_at, 404);
            DB::table('organization_invitations')->where('id', $row->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'Invitation cancelled. The link can no longer be used.');
    }
}
