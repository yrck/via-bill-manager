<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManagerExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $customer;

    private int $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        $this->manager = User::factory()->create(['email' => 'manager@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true, 'password' => 'fictional-manager-password']);
        $this->customer = User::factory()->create(['email' => 'customer@example.test', 'is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Customer A', 'key' => 'customer-a', 'owner_user_id' => $this->customer->id]);
        $membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->customer->id, 'role' => 'viewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['organization_id' => $this->organization, 'key' => 'a', 'name' => 'Portfolio A']);
        $visible = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Granted location']);
        DB::table('locations')->insert(['portfolio_id' => $portfolio, 'name' => 'Hidden location']);
        DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $visible]);
    }

    private function enter(array $extra = [])
    {
        return $this->actingAs($this->manager)->post('/management/customers/'.$this->organization.'/view', array_replace(['user_id' => $this->customer->id, 'reason' => 'Investigate a fictional customer question.'], $extra));
    }

    public function test_manager_can_designate_verified_employees_and_changes_are_recorded(): void
    {
        $employee = User::factory()->create(['email' => 'employee@nu-devco.com', 'is_active' => true]);
        $this->actingAs($this->manager)->get('/management/users')->assertOk()->assertSee('Users & managers.', false);
        $this->post('/management/users/'.$employee->id.'/manager', ['manager' => 1, 'previous' => 0])->assertSessionHasNoErrors();
        $this->assertTrue($employee->fresh()->is_platform_staff);
        $this->assertDatabaseHas('manager_access_changes', ['actor_id' => $this->manager->id, 'user_id' => $employee->id, 'is_manager' => true]);
        $this->post('/management/users/'.$employee->id.'/manager', ['manager' => 0, 'previous' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($employee->fresh()->is_platform_staff);
        $this->post('/management/users/'.$this->manager->id.'/manager', ['manager' => 0, 'previous' => 1])->assertSessionHasErrors('manager');
    }

    public function test_manager_domain_is_exact_and_enforced_on_grants_console_and_existing_sessions(): void
    {
        foreach (['someone@example.test', 'someone@sub.nu-devco.com', 'someone@nu-devco.com.evil.test'] as $email) {
            $user = User::factory()->create(['email' => $email, 'is_active' => true]);
            $this->actingAs($this->manager)->post('/management/users/'.$user->id.'/manager', ['manager' => 1, 'previous' => 0])->assertSessionHasErrors('manager');
            $this->artisan('platform:staff', ['email' => $email])->assertFailed();
            $user->is_platform_staff = true;
            $user->save();
            $this->assertFalse(Gate::forUser($user)->allows('manage-customers'));
        }
        $unverified = User::factory()->unverified()->create(['email' => 'unverified@nu-devco.com', 'is_active' => true]);
        $this->post('/management/users/'.$unverified->id.'/manager', ['manager' => 1, 'previous' => 0])->assertSessionHasErrors('manager');
        DB::table('users')->where('id', $this->manager->id)->update(['email' => 'changed@example.test']);
        $this->get('/management/users')->assertForbidden();
    }

    public function test_managers_land_in_management_after_login_and_workspace_home_redirects(): void
    {
        $this->post('/login', ['email' => $this->manager->email, 'password' => 'fictional-manager-password'])->assertRedirect('/management');
        $this->get('/workspace')->assertRedirect('/management');
        $this->get('/management')->assertRedirect('/management/customers');
    }

    public function test_customer_view_uses_target_grants_preserves_manager_identity_and_has_return_path(): void
    {
        $this->enter()->assertRedirect('/management/customer-view');
        $this->assertAuthenticatedAs($this->manager);
        $this->get('/management/customer-view')->assertOk()->assertSee('Read-only customer view')->assertSee('Granted location')->assertDontSee('Hidden location')->assertSee('Return to management')->assertDontSee('Add location')->assertDontSee('View utility accounts');
        $this->get('/management/customer-view/team')->assertOk()->assertSee('Customer A')->assertDontSee('Send invitation')->assertDontSee('Manage access')->assertSee('Invitation actions are unavailable');
        $this->assertDatabaseHas('customer_view_sessions', ['manager_id' => $this->manager->id, 'user_id' => $this->customer->id, 'organization_id' => $this->organization, 'ended_at' => null]);
        $this->post('/workspace/'.$this->organization.'/team/invite', ['email' => 'other@example.test', 'role' => 'viewer'])->assertNotFound();
        $this->post('/management/customer-view/stop')->assertRedirect('/management');
        $this->assertNotNull(DB::table('customer_view_sessions')->value('ended_at'));
        $this->get('/management/customer-view')->assertNotFound();
        $this->assertAuthenticatedAs($this->manager);
    }

    public function test_view_rechecks_membership_and_manager_access_after_start(): void
    {
        $this->enter()->assertRedirect();
        DB::table('organization_memberships')->where('user_id', $this->customer->id)->update(['is_active' => false]);
        $this->get('/management/customer-view')->assertNotFound();
        DB::table('organization_memberships')->where('user_id', $this->customer->id)->update(['is_active' => true]);
        DB::table('users')->where('id', $this->manager->id)->update(['is_platform_staff' => false]);
        $this->get('/management/customer-view')->assertForbidden();
    }

    public function test_customer_view_rejects_outsiders_suspended_orgs_and_expires(): void
    {
        $outsider = User::factory()->create(['is_active' => true]);
        $this->enter(['user_id' => $outsider->id])->assertNotFound();
        $this->enter()->assertRedirect();
        $this->travel(61)->minutes();
        $this->get('/management/customer-view')->assertNotFound();
        $this->travelBack();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->enter()->assertNotFound();
    }

    public function test_customer_cannot_designate_managers_or_start_customer_view(): void
    {
        $this->actingAs($this->customer)->post('/management/users/'.$this->customer->id.'/manager', ['manager' => 1, 'previous' => 0])->assertForbidden();
        $this->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->customer->id, 'reason' => 'Customer cannot start manager preview.'])->assertForbidden();
        $this->assertDatabaseCount('customer_view_sessions', 0);
        $this->assertDatabaseCount('manager_access_changes', 0);
    }
}
