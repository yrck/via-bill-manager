<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Billing\CustomerOverview;
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

        $filters = $request->validate(['period' => ['nullable', 'date_format:Y-m-01']]);
        $period = $filters['period'] ?? today()->startOfMonth()->toDateString();

        return view('customer.workspace', [
            'organization' => $record,
            'locations' => $access->locations($request->user(), $organization)->orderBy('locations.name')->get(),
            'accountCount' => $access->accounts($request->user(), $organization)->count(),
            'statementCount' => $access->statements($request->user(), $organization)->count(),
        ] + app(CustomerOverview::class)->data($request->user(), $record->id, $period));
    }
}
