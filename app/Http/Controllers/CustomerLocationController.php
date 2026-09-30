<?php

namespace App\Http\Controllers;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Services\TeamInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerLocationController extends Controller
{
    public const COMMODITIES = ['Electricity', 'Natural gas', 'Water', 'Sewer', 'Waste', 'Other'];

    public function create(Request $request, int $organization, TeamInvitations $invitations)
    {
        return view('customer.location-create', ['organization' => $invitations->owner($request->user(), $organization)]);
    }

    public function store(Request $request, int $organization, TeamInvitations $invitations)
    {
        $invitations->owner($request->user(), $organization);
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        $location = DB::transaction(function () use ($request, $organization, $invitations, $data) {
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            $invitations->owner($request->user(), $organization);
            $name = trim($data['name']);
            if (DB::table('locations')->join('portfolios', 'portfolios.id', '=', 'locations.portfolio_id')->where('portfolios.organization_id', $organization)->whereRaw('LOWER(locations.name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'A location with this name already exists in this account.']);
            }
            $portfolio = DB::table('portfolios')->where('organization_id', $organization)->where('key', 'customer-'.$organization.'-locations')->value('id');
            if (! $portfolio) {
                $portfolio = DB::table('portfolios')->insertGetId(['organization_id' => $organization, 'key' => 'customer-'.$organization.'-locations', 'name' => 'Customer locations']);
            }
            $id = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => $name]);
            $membership = DB::table('organization_memberships')->where('organization_id', $organization)->where('user_id', $request->user()->id)->value('id');
            DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });

        return redirect()->route('customer.locations.show', [$organization, $location])->with('status', 'Location created. Add its utility accounts below, then assign team access when ready.');
    }

    public function show(Request $request, int $organization, int $location, BillingAccess $access)
    {
        $account = app(CustomerAccounts::class)->accessible($request->user())->where('o.id', $organization)->first();
        abort_unless($account, 404);
        $record = $access->locations($request->user(), $organization)->where('locations.id', $location)->first();
        abort_unless($record, 404);

        return view('customer.location', [
            'organization' => $account, 'location' => $record, 'commodities' => self::COMMODITIES,
            'servicePoints' => $access->servicePoints($request->user(), $organization)->where('location_id', $location)->orderBy('label')->get(),
            'meters' => DB::table('meters')->whereIn('service_point_id', $access->servicePoints($request->user(), $organization)->where('location_id', $location)->select('id'))->get()->groupBy('service_point_id'),
            'accounts' => $access->accounts($request->user(), $organization)->where('location_id', $location)->orderBy('supplier')->orderBy('reference')->paginate(25),
        ]);
    }

    public function storeAccount(Request $request, int $organization, int $location, TeamInvitations $invitations, BillingAccess $access)
    {
        $invitations->owner($request->user(), $organization);
        abort_unless($access->locations($request->user(), $organization)->where('locations.id', $location)->exists(), 404);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:150'], 'supplier' => ['required', 'string', 'max:150'],
            'commodity' => ['required', Rule::in(self::COMMODITIES)],
        ]);
        DB::transaction(function () use ($request, $organization, $location, $invitations, $access, $data) {
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            $invitations->owner($request->user(), $organization);
            abort_unless($access->locations($request->user(), $organization)->where('locations.id', $location)->exists(), 404);
            if (DB::table('utility_accounts')->where('location_id', $location)->whereRaw('LOWER(reference) = ?', [mb_strtolower($data['reference'])])->exists()) {
                throw ValidationException::withMessages(['reference' => 'This utility account number already exists at this location.']);
            }
            DB::table('utility_accounts')->insert(['location_id' => $location, 'reference' => $data['reference'], 'supplier' => $data['supplier'], 'commodity' => $data['commodity']]);
        });

        return redirect()->route('customer.locations.show', [$organization, $location])->with('status', 'Utility account added. Bill intake is the next step once available.');
    }
}
