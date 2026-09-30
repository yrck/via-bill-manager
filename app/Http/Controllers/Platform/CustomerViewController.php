<?php

namespace App\Http\Controllers\Platform;

use App\Access\BillingAccess;
use App\Billing\CustomerBills;
use App\Http\Controllers\BillIntakeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerBillController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerViewController extends Controller
{
    private function member(int $organization, int $user): array
    {
        $target = User::find($user);
        $record = DB::table('organizations')->where('id', $organization)->where('is_active', true)->first();
        $membership = DB::table('organization_memberships')->where('organization_id', $organization)->where('user_id', $user)->where('is_active', true)->whereIn('role', ['viewer', 'reviewer'])->exists();
        abort_unless($record && $target && $target->is_active && $target->hasVerifiedEmail() && $membership && ! $target->is_platform_staff, 404);

        return [$record, $target];
    }

    public function start(Request $request, int $organization)
    {
        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        [$record, $target] = $this->member($organization, (int) $data['user_id']);
        $this->endCurrent($request);
        $id = DB::table('customer_view_sessions')->insertGetId(['manager_id' => $request->user()->id, 'user_id' => $target->id, 'organization_id' => $record->id, 'reason' => $data['reason'], 'started_at' => now()]);
        $request->session()->put('customer_view_id', $id);

        return redirect()->route('platform.customer-view');
    }

    public function show(Request $request, BillingAccess $access)
    {
        [$event, $record, $target] = $this->context($request);

        return view('customer.workspace', [
            'organization' => $record, 'viewUserId' => $target->id, 'customerView' => $event,
            'locations' => $access->locations($target, $record->id)->orderBy('locations.name')->get(),
            'accountCount' => $access->accounts($target, $record->id)->count(),
            'statementCount' => $access->statements($target, $record->id)->count(),
            'reviewBills' => app(CustomerBills::class)->query($target, $record->id)->where('s.status', 'review')->orderByDesc('e.period')->limit(5)->get(),
        ]);
    }

    public function team(Request $request, BillingAccess $access)
    {
        [$event, $record, $target] = $this->context($request);
        abort_unless($record->owner_user_id === $target->id, 404);

        return view('customer.team', [
            'organization' => $record, 'customerView' => $event,
            'members' => DB::table('organization_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.organization_id', $record->id)->orderBy('u.name')->get(['m.id', 'm.user_id', 'm.role', 'm.is_active', 'u.name', 'u.email']),
            'invitations' => DB::table('organization_invitations')->where('organization_id', $record->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->orderByDesc('id')->get(),
            'locations' => $access->locations($target, $record->id)->orderBy('locations.name')->get(),
        ]);
    }

    public function bills(Request $request, CustomerBillController $bills)
    {
        [$event, $record, $target] = $this->context($request);

        return $bills->indexFor($request, $target, $record, $event);
    }

    public function bill(Request $request, int $bill, CustomerBillController $bills)
    {
        [$event, $record, $target] = $this->context($request);

        return $bills->showFor($target, $record, $bill, $event);
    }

    public function document(Request $request, int $bill, BillIntakeController $documents)
    {
        [$event, $record, $target] = $this->context($request);
        $statement = app(BillingAccess::class)->statements($target, $record->id)->where('expected_bill_id', $bill)->first();
        abort_unless($statement, 404);

        return $documents->document($record->id, $statement->id);
    }

    private function context(Request $request): array
    {
        $event = DB::table('customer_view_sessions')->where('id', $request->session()->get('customer_view_id'))->where('manager_id', $request->user()->id)->whereNull('ended_at')->where('started_at', '>', now()->subHour())->first();
        abort_unless($event, 404);
        [$record, $target] = $this->member($event->organization_id, $event->user_id);
        $event->user_name = $target->name;
        $event->user_email = $target->email;

        return [$event, $record, $target];
    }

    private function endCurrent(Request $request): void
    {
        if ($id = $request->session()->pull('customer_view_id')) {
            DB::table('customer_view_sessions')->where('id', $id)->where('manager_id', $request->user()->id)->whereNull('ended_at')->update(['ended_at' => now()]);
        }
    }

    public function stop(Request $request)
    {
        $this->endCurrent($request);

        return redirect()->route('platform.home');
    }
}
