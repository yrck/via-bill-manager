<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private int $organization;

    private int $membership;

    private int $location;

    private int $foreignLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->member = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Test Customer', 'key' => 'test', 'owner_user_id' => $this->owner->id]);
        $ownerMembership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->member->id, 'role' => 'viewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['name' => 'Test', 'key' => 'test', 'organization_id' => $this->organization]);
        $this->location = DB::table('locations')->insertGetId(['name' => 'Allowed Location', 'portfolio_id' => $portfolio]);
        DB::table('location_grants')->insert(['organization_membership_id' => $ownerMembership, 'location_id' => $this->location]);
        $foreignPortfolio = DB::table('portfolios')->insertGetId(['name' => 'Foreign', 'key' => 'foreign']);
        $this->foreignLocation = DB::table('locations')->insertGetId(['name' => 'Foreign Location', 'portfolio_id' => $foreignPortfolio]);
        $this->actingAs($this->owner);
    }

    private function url(?int $membership = null): string
    {
        return '/workspace/'.$this->organization.'/team/members/'.($membership ?? $this->membership);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['version' => 0, 'role' => 'reviewer', 'is_active' => 1, 'locations' => [$this->location], 'reason' => 'Assign responsibility for property bills.'], $changes);
    }

    public function test_owner_changes_role_and_properties_with_actor_history(): void
    {
        $this->get($this->url())->assertOk()->assertSee('Allowed Location')->assertDontSee('Foreign Location');
        $this->put($this->url(), $this->data())->assertRedirect($this->url())->assertSessionHasNoErrors();
        $this->assertSame(1, app(BillingAccess::class)->locations($this->member, $this->organization, true)->count());
        $this->assertDatabaseHas('organization_memberships', ['id' => $this->membership, 'role' => 'reviewer', 'access_version' => 1]);
        $this->assertDatabaseHas('membership_access_changes', ['actor_id' => $this->owner->id, 'organization_membership_id' => $this->membership, 'version' => 1]);
        $this->get($this->url())->assertSee('Assign responsibility for property bills.');
        $this->put($this->url(), $this->data(['version' => 1, 'role' => 'viewer', 'locations' => []]))->assertSessionHasNoErrors();
        $this->assertSame(0, app(BillingAccess::class)->locations($this->member, $this->organization)->count());
    }

    public function test_deactivation_removes_grants_and_restoration_requires_new_selection_without_affecting_other_accounts(): void
    {
        $other = DB::table('organizations')->insertGetId(['name' => 'Other', 'key' => 'other', 'owner_user_id' => $this->member->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $this->member->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->put($this->url(), $this->data(['version' => 1, 'is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('location_grants', ['organization_membership_id' => $this->membership]);
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $other, 'user_id' => $this->member->id, 'is_active' => true]);
        $this->assertTrue($this->member->fresh()->is_active);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($this->member)->get('/workspace/'.$this->organization)->assertNotFound();
        $this->get('/workspace/'.$other)->assertOk();
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($this->owner)->put($this->url(), $this->data(['version' => 2, 'locations' => []]))->assertSessionHasNoErrors();
        $this->assertSame(0, app(BillingAccess::class)->locations($this->member, $this->organization)->count());
    }

    public function test_stale_updates_and_foreign_properties_are_rejected_atomically(): void
    {
        $this->put($this->url(), $this->data(['locations' => [$this->foreignLocation]]))->assertSessionHasErrors('locations');
        $this->assertDatabaseCount('membership_access_changes', 0);
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->put($this->url(), $this->data(['is_active' => 0]))->assertSessionHasErrors('version');
        $this->assertDatabaseHas('organization_memberships', ['id' => $this->membership, 'is_active' => true, 'access_version' => 1]);
        $this->assertDatabaseCount('membership_access_changes', 1);
    }

    public function test_owner_nonowner_foreign_member_and_suspended_account_boundaries(): void
    {
        $ownerMembership = DB::table('organization_memberships')->where('user_id', $this->owner->id)->value('id');
        $this->put($this->url($ownerMembership), $this->data())->assertForbidden();
        $this->get($this->url($ownerMembership))->assertForbidden();
        $other = DB::table('organizations')->insertGetId(['name' => 'Other', 'key' => 'other']);
        $foreign = DB::table('organization_memberships')->insertGetId(['organization_id' => $other, 'user_id' => $this->member->id, 'role' => 'viewer', 'is_active' => true]);
        $this->put($this->url($foreign), $this->data())->assertNotFound();
        $this->get($this->url($foreign))->assertNotFound();
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($this->member)->put($this->url(), $this->data())->assertNotFound();
        $this->get($this->url())->assertNotFound();
        $this->app['session']->forget('password_hash_web');
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->actingAs($this->owner)->put($this->url(), $this->data())->assertNotFound();
        $this->assertDatabaseCount('membership_access_changes', 0);
    }

    public function test_noop_does_not_duplicate_history_and_revoked_owner_cannot_write(): void
    {
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->put($this->url(), $this->data(['version' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('membership_access_changes', 1);
        DB::table('organization_memberships')->where('organization_id', $this->organization)->where('user_id', $this->owner->id)->update(['is_active' => false]);
        $this->put($this->url(), $this->data(['version' => 1, 'is_active' => 0]))->assertNotFound();
        $this->assertDatabaseHas('organization_memberships', ['id' => $this->membership, 'is_active' => true]);
    }

    public function test_validation_rejects_privilege_escalation_and_empty_reasons(): void
    {
        $this->put($this->url(), $this->data(['role' => 'owner']))->assertSessionHasErrors('role');
        $this->put($this->url(), $this->data(['reason' => '           ']))->assertSessionHasErrors('reason');
        $this->put($this->url(), $this->data(['locations' => [$this->location, $this->location]]))->assertSessionHasErrors('locations.0');
        $this->assertDatabaseCount('membership_access_changes', 0);
    }
}
