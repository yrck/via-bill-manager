<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PlatformManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $owner;

    private int $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        $this->staff = User::factory()->create(['is_active' => true, 'is_platform_staff' => true, 'email' => 'manager@nu-devco.com']);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Example Customer', 'key' => 'example', 'owner_user_id' => $this->owner->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $this->organization, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
    }

    private function signIn(User $user): static
    {
        $this->app['session']->forget('password_hash_web');

        return $this->actingAs($user);
    }

    private function statusUrl(): string
    {
        return '/management/customers/'.$this->organization.'/status';
    }

    public function test_only_verified_active_platform_staff_can_access_management(): void
    {
        $this->get('/management/customers')->assertRedirect('/login');
        $this->signIn($this->owner)->get('/management/customers')->assertForbidden();
        $this->post($this->statusUrl(), ['version' => 0, 'action' => 'suspend', 'reason' => 'Customer owner must not have staff privileges.'])->assertForbidden();
        $this->signIn($this->staff)->get('/management/customers')->assertOk()->assertSee('Example Customer');
        $this->get('/management/customers/'.$this->organization)->assertOk()->assertSee('Account overview');
        DB::table('users')->where('id', $this->staff->id)->update(['is_platform_staff' => false]);
        $this->get('/management/customers')->assertForbidden();
        $this->post($this->statusUrl(), ['version' => 0, 'action' => 'suspend', 'reason' => 'Revoked staff access must not work.'])->assertForbidden();
        $this->assertDatabaseCount('organization_status_changes', 0);
    }

    public function test_staff_management_does_not_grant_customer_property_or_team_access(): void
    {
        $this->signIn($this->staff)->get('/workspace/'.$this->organization)->assertNotFound();
        $this->get('/workspace/'.$this->organization.'/team')->assertNotFound();
        $this->assertSame(0, app(BillingAccess::class)->locations($this->staff, $this->organization)->count());
        $this->assertFalse(Gate::forUser($this->owner)->allows('manage-customers'));
    }

    public function test_directory_filters_status_and_owner_email(): void
    {
        DB::table('organizations')->insert(['name' => 'Suspended Example', 'key' => 'suspended', 'is_active' => false]);
        $this->signIn($this->staff)->get('/management/customers?status=suspended')->assertOk()->assertSee('Suspended Example')->assertDontSee('Example Customer');
        $this->get('/management/customers?search='.urlencode($this->owner->email))->assertOk()->assertSee('Example Customer')->assertDontSee('Suspended Example');
        $this->get('/management/customers?search=nomatch')->assertSee('No customers match');
        $this->get('/management/customers/9999')->assertNotFound();
    }

    public function test_suspension_and_restoration_record_actor_reason_and_block_only_target_organization(): void
    {
        $other = DB::table('organizations')->insertGetId(['name' => 'Other Organization', 'key' => 'other', 'owner_user_id' => $this->owner->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $this->owner->id, 'role' => 'viewer', 'is_active' => true]);
        $this->signIn($this->staff)->post($this->statusUrl(), ['version' => 0, 'action' => 'suspend', 'reason' => 'Fictional customer requested an access pause.'])->assertRedirect('/management/customers/'.$this->organization);
        $this->assertDatabaseHas('organization_status_changes', ['organization_id' => $this->organization, 'actor_id' => $this->staff->id, 'was_active' => true, 'is_active' => false, 'version' => 1]);
        $this->signIn($this->owner)->get('/workspace/'.$this->organization)->assertNotFound();
        $this->get('/workspace/'.$other)->assertOk();
        $this->get('/workspace/'.$this->organization.'/team')->assertNotFound();
        $this->signIn($this->staff)->post($this->statusUrl(), ['version' => 1, 'action' => 'restore', 'reason' => 'Fictional customer requested access restored.'])->assertRedirect();
        $this->get('/management/customers/'.$this->organization)->assertSee('Fictional customer requested an access pause.')->assertSee('Fictional customer requested access restored.');
        $this->signIn($this->owner)->get('/workspace/'.$this->organization)->assertOk();
        $this->assertDatabaseCount('organization_status_changes', 2);
    }

    public function test_stale_duplicate_and_invalid_changes_do_not_overwrite_status(): void
    {
        $this->signIn($this->staff)->post($this->statusUrl(), ['version' => 0, 'action' => 'suspend', 'reason' => '   '])->assertSessionHasErrors('reason');
        $this->post($this->statusUrl(), ['version' => 0, 'action' => 'delete', 'reason' => 'Invalid operation should be rejected.'])->assertSessionHasErrors('action');
        $this->post($this->statusUrl(), ['version' => 0, 'action' => 'suspend', 'reason' => 'Valid fictional suspension for testing.'])->assertSessionHasNoErrors();
        $this->post($this->statusUrl(), ['version' => 0, 'action' => 'restore', 'reason' => 'Stale tab cannot undo the newer status.'])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('organizations', ['id' => $this->organization, 'is_active' => false, 'status_version' => 1]);
        $this->assertDatabaseCount('organization_status_changes', 1);
    }

    public function test_explicit_console_provisioning_requires_active_verified_identity_and_can_revoke(): void
    {
        $this->owner->email = 'employee@nu-devco.com';
        $this->owner->save();
        $this->artisan('platform:staff', ['email' => $this->owner->email])->assertSuccessful();
        $this->assertTrue($this->owner->fresh()->is_platform_staff);
        $this->artisan('platform:staff', ['email' => $this->owner->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($this->owner->fresh()->is_platform_staff);
        $unverified = User::factory()->unverified()->create(['is_active' => true]);
        $this->artisan('platform:staff', ['email' => $unverified->email])->assertFailed();
        $this->assertFalse($unverified->fresh()->is_platform_staff);
    }

    public function test_unverified_and_disabled_staff_are_blocked(): void
    {
        $unverified = User::factory()->unverified()->create(['is_active' => true, 'is_platform_staff' => true, 'email' => 'unverified@nu-devco.com']);
        $this->signIn($unverified)->get('/management/customers')->assertRedirect('/verify-email');
        DB::table('users')->where('id', $this->staff->id)->update(['is_active' => false]);
        $this->signIn($this->staff)->get('/management/customers')->assertForbidden();
    }
}
