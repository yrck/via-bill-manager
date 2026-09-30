<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Billing\CustomerBills;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::allows('manage-customers')) {
            return redirect()->route('platform.home');
        }
        if ($token = $request->session()->pull('pending_invitation')) {
            return redirect()->route('invitations.show', $token);
        }
        $organizations = app(CustomerAccounts::class)->accessible($request->user())->orderBy('o.name')->get();

        return view('customer.home', compact('organizations'));
    }

    public function show(Request $request, int $organization, BillingAccess $access)
    {
        $record = app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->first();
        abort_unless($record, 404);

        return view('customer.workspace', [
            'organization' => $record,
            'locations' => $access->locations($request->user(), $organization)->orderBy('locations.name')->get(),
            'accountCount' => $access->accounts($request->user(), $organization)->count(),
            'statementCount' => $access->statements($request->user(), $organization)->count(),
            'reviewBills' => app(CustomerBills::class)->query($request->user(), $organization)->where('s.status', 'review')->orderByDesc('e.period')->limit(5)->get(),
        ]);
    }
}
