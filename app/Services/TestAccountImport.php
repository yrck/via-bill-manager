<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Explicit trusted-console provisioning, never a public onboarding endpoint or seeder. */
class TestAccountImport
{
    public function run(array $payload, string $email, bool $createOwner = false): int
    {
        $data = Validator::make($payload, [
            'dataset_key' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'], 'legal_name' => ['required', 'string', 'max:255'],
            'billing_address' => ['required', 'string', 'max:1000'], 'location_name' => ['required', 'string', 'max:150'],
            'service_address' => ['required', 'string', 'max:1000'], 'points' => ['required', 'array', 'min:1', 'max:100'],
            'points.*' => ['array:esi_id,label,service_address,reference,supplier,meter_number,observed_on,source_document,source_sha256'],
            'points.*.esi_id' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:64', 'distinct:strict'],
            'points.*.label' => ['required', 'string', 'max:255'], 'points.*.service_address' => ['required', 'string', 'max:1000'],
            'points.*.reference' => ['required', 'string', 'max:150', 'distinct:strict'], 'points.*.supplier' => ['required', 'string', 'max:150'],
            'points.*.meter_number' => ['required', 'string', 'max:150', 'distinct:strict'], 'points.*.observed_on' => ['required', 'date_format:Y-m-d'],
            'points.*.source_document' => ['required', 'string', 'max:255'], 'points.*.source_sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ])->validate();
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:254']])->validate();
        $digest = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $digest, $email, $createOwner) {
            $owner = User::where('email', Str::lower(trim($email)))->lockForUpdate()->first();
            if (! $owner && $createOwner) {
                $owner = new User;
                $owner->forceFill(['name' => $email, 'email' => Str::lower(trim($email)), 'password' => Str::random(64), 'is_active' => true])->save();
            }
            if (! $owner || ! $owner->is_active) {
                throw ValidationException::withMessages(['owner' => 'An active owner login is required. Use --create-owner to create a missing login; existing users are never reactivated.']);
            }
            $existing = DB::table('test_account_imports')->where('dataset_key', $data['dataset_key'])->first();
            if ($existing) {
                if ($existing->payload_sha256 !== $digest || $existing->owner_user_id !== $owner->id) {
                    throw ValidationException::withMessages(['dataset_key' => 'This dataset was already imported with different data or ownership. No changes made.']);
                }

                return $existing->organization_id;
            }
            $organization = DB::table('organizations')->insertGetId([
                'key' => (string) Str::uuid(), 'name' => $data['account_name'], 'legal_name' => $data['legal_name'],
                'billing_address' => $data['billing_address'], 'is_test_account' => true, 'is_active' => true,
                'owner_user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $organization, 'user_id' => $owner->id, 'role' => 'reviewer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $portfolio = DB::table('portfolios')->insertGetId(['organization_id' => $organization, 'key' => (string) Str::uuid(), 'name' => 'Test locations']);
            $location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => $data['location_name'], 'service_address' => $data['service_address']]);
            DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $location, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['points'] as $point) {
                $account = DB::table('utility_accounts')->insertGetId(['location_id' => $location, 'reference' => $point['reference'], 'supplier' => $point['supplier'], 'commodity' => 'Electricity']);
                $servicePoint = DB::table('service_points')->insertGetId([
                    'organization_id' => $organization, 'location_id' => $location, 'esi_id' => $point['esi_id'], 'label' => $point['label'],
                    'service_address' => $point['service_address'], 'source_document' => $point['source_document'], 'source_sha256' => $point['source_sha256'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('meters')->insert(['service_point_id' => $servicePoint, 'meter_number' => $point['meter_number'], 'observed_on' => $point['observed_on'], 'created_at' => now(), 'updated_at' => now()]);
                DB::table('utility_account_service_points')->insert(['utility_account_id' => $account, 'service_point_id' => $servicePoint]);
            }
            DB::table('test_account_imports')->insert(['dataset_key' => $data['dataset_key'], 'payload_sha256' => $digest, 'organization_id' => $organization, 'owner_user_id' => $owner->id, 'created_at' => now()]);

            return $organization;
        });
    }
}
