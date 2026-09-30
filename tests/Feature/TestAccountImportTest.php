<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use App\Services\TestAccountImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestAccountImportTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['dataset_key' => 'fictional-meter-test', 'account_name' => 'Fictional Meter Test', 'legal_name' => 'Fictional Company', 'billing_address' => 'Example billing address', 'location_name' => 'Example site', 'service_address' => 'Example service address', 'points' => [
            ['esi_id' => '00000000000000001', 'label' => 'Building A', 'service_address' => 'Example A', 'reference' => '000001', 'supplier' => 'Example Supplier', 'meter_number' => 'METER-A', 'observed_on' => '2026-06-01', 'source_document' => 'fictional-a.pdf', 'source_sha256' => str_repeat('a', 64)],
            ['esi_id' => '00000000000000002', 'label' => 'Building B', 'service_address' => 'Example B', 'reference' => '000002', 'supplier' => 'Example Supplier', 'meter_number' => 'METER-B', 'observed_on' => '2026-06-02', 'source_document' => 'fictional-b.pdf', 'source_sha256' => str_repeat('b', 64)],
        ]];
    }

    public function test_private_dataset_creates_scoped_inventory_and_repeat_preserves_revocations(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['is_active' => true]);
        $import = app(TestAccountImport::class);
        $id = $import->run($this->payload(), $owner->email);
        $this->assertDatabaseHas('organizations', ['id' => $id, 'is_test_account' => true, 'owner_user_id' => $owner->id]);
        $this->assertDatabaseHas('service_points', ['organization_id' => $id, 'esi_id' => '00000000000000001']);
        $this->assertDatabaseHas('utility_accounts', ['reference' => '000001']);
        $this->assertDatabaseCount('meters', 2);
        $this->assertDatabaseCount('statements', 0);
        $location = DB::table('locations')->value('id');
        $this->actingAs($owner)->get('/workspace/'.$id.'/locations/'.$location)->assertOk()->assertSee('00000000000000001')->assertSee('METER-A');
        $outsider = User::factory()->create(['is_active' => true]);
        $this->assertSame(0, app(BillingAccess::class)->servicePoints($outsider, $id)->count());
        DB::table('organization_memberships')->where('organization_id', $id)->update(['is_active' => false]);
        DB::table('location_grants')->delete();
        $this->assertSame($id, $import->run($this->payload(), $owner->email));
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('location_grants', 0);
        $this->assertDatabaseCount('service_points', 2);
        $this->assertSame(0, app(BillingAccess::class)->servicePoints($owner, $id)->count());
        $this->get('/workspace/'.$id.'/locations/'.$location)->assertNotFound();
    }

    public function test_changed_payload_or_owner_is_rejected_without_duplicate_account(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $import = app(TestAccountImport::class);
        $import->run($this->payload(), $owner->email);
        foreach ([[$this->payload(), 'someone@example.test'], [array_replace($this->payload(), ['account_name' => 'Changed']), $owner->email]] as [$data, $email]) {
            try {
                $import->run($data, $email, true);
                $this->fail('Expected conflict.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('dataset_key', $e->errors());
            }
        }
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_identifiers_roll_back_without_creating_owner(): void
    {
        $payload = $this->payload();
        $payload['points'][1]['esi_id'] = $payload['points'][0]['esi_id'];
        try {
            app(TestAccountImport::class)->run($payload, 'owner@example.test', true);
            $this->fail('Expected duplicate validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('organizations', 0);
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_owner_creation_requires_password_setup_and_verification_and_grants_no_manager_role(): void
    {
        $id = app(TestAccountImport::class)->run($this->payload(), 'owner@example.test', true);
        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($owner->is_active);
        $this->assertNull($owner->email_verified_at);
        $this->assertFalse($owner->is_platform_staff);
        $this->actingAs($owner)->get('/workspace/'.$id)->assertRedirect('/verify-email');
        $owner->forceFill(['is_active' => false])->save();
        $this->expectException(ValidationException::class);
        app(TestAccountImport::class)->run($this->payload(), $owner->email, true);
    }

    public function test_command_requires_explicit_remote_opt_in_and_reports_invalid_json(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('account:import-test', ['file' => '/missing', '--owner' => 'owner@example.test'])->assertFailed();
        $file = tempnam(sys_get_temp_dir(), 'fictional-manifest-');
        try {
            file_put_contents($file, '{broken');
            $this->artisan('account:import-test', ['file' => $file, '--owner' => 'owner@example.test', '--allow-development-server' => true])->assertFailed();
            file_put_contents($file, json_encode($this->payload()));
            $this->artisan('account:import-test', ['file' => $file, '--owner' => 'owner@example.test', '--create-owner' => true, '--allow-development-server' => true])->assertSuccessful();
            $this->assertDatabaseCount('test_account_imports', 1);
        } finally {
            unlink($file);
        }
    }
}
