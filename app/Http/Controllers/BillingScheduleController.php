<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Services\BillingSchedules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingScheduleController extends Controller
{
    public function show(Request $request, int $organization, int $account)
    {
        $record = app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->first();
        $utility = app(BillingAccess::class)->accounts($request->user(), $organization)->where('id', $account)->first();
        abort_unless($record && $utility, 404);
        $history = DB::table('billing_schedules')->where('utility_account_id', $account)->orderByDesc('id')->get();

        return view('customer.billing-schedule', ['organization' => $record, 'account' => $utility, 'history' => $history,
            'current' => app(BillingSchedules::class)->rule($history, today()->startOfMonth()->toDateString()), 'version' => $history->max('id') ?? 0]);
    }

    public function store(Request $request, int $organization, int $account, BillingSchedules $schedules)
    {
        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $schedules->save($request->user(), $organization, $account, $request->all());

        return redirect()->route('customer.accounts.schedule', [$organization, $account])->with('status', 'Schedule saved and expectations refreshed. Existing statements were preserved.');
    }
}
