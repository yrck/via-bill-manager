<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\CustomerStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:all,active,suspended,unverified']]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $customers = DB::table('organizations as o')->leftJoin('users as u', 'u.id', '=', 'o.owner_user_id')
            ->select(['o.id', 'o.name', 'o.is_active', 'o.created_at', 'u.name as owner_name', 'u.email as owner_email', 'u.email_verified_at'])
            ->selectSub(DB::table('organization_memberships as m')->selectRaw('count(*)')->whereColumn('m.organization_id', 'o.id'), 'member_count')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('o.name', '%'.$search.'%')->orWhereLike('u.email', '%'.$search.'%');
            }))
            ->when($status === 'active', fn ($query) => $query->where('o.is_active', true))
            ->when($status === 'suspended', fn ($query) => $query->where('o.is_active', false))
            ->when($status === 'unverified', fn ($query) => $query->whereNotNull('u.id')->whereNull('u.email_verified_at'))
            ->orderByDesc('o.id')->paginate(20)->withQueryString();

        return view('platform.customers', compact('customers', 'search', 'status'));
    }

    public function show(int $organization)
    {
        $customer = DB::table('organizations as o')->leftJoin('users as u', 'u.id', '=', 'o.owner_user_id')
            ->where('o.id', $organization)->first(['o.*', 'u.name as owner_name', 'u.email as owner_email', 'u.email_verified_at']);
        abort_unless($customer, 404);

        return view('platform.customer', [
            'customer' => $customer,
            'members' => DB::table('organization_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.organization_id', $organization)->orderBy('u.name')->select(['u.name', 'u.email', 'u.is_active as user_active', 'u.email_verified_at', 'm.role', 'm.is_active', 'm.user_id'])->paginate(20, ['*'], 'members'),
            'pendingInvites' => DB::table('organization_invitations')->where('organization_id', $organization)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            'history' => DB::table('organization_status_changes')->where('organization_id', $organization)->orderByDesc('version')->paginate(10, ['*'], 'history'),
        ]);
    }

    public function update(Request $request, int $organization, CustomerStatus $status)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:suspend,restore'], 'reason' => ['required', 'string', 'max:2000']]);
        $status->change($request->user(), $organization, (int) $data['version'], $data['action'], $data['reason']);

        return redirect()->route('platform.customers.show', $organization)->with('status', $data['action'] === 'suspend' ? 'Customer suspended. Organization access is now blocked.' : 'Customer restored. Existing active memberships and property grants apply again.');
    }
}
