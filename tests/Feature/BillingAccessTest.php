<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use Database\Seeders\DemoPortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BillingAccessTest extends TestCase
{
    use RefreshDatabase;

    private BillingAccess $access;

    private User $viewer;

    private User $reviewer;

    private int $organization;

    private int $otherOrganization;

    private int $viewerMembership;

    private int $reviewerMembership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['demo.enabled' => true]);
        $this->seed(DemoPortfolioSeeder::class);
        $this->access = app(BillingAccess::class);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Fictional A', 'key' => 'test-a']);
        $this->otherOrganization = DB::table('organizations')->insertGetId(['name' => 'Fictional B', 'key' => 'test-b']);
        DB::table('portfolios')->update(['organization_id' => $this->organization]);
        $otherPortfolio = DB::table('portfolios')->insertGetId(['name' => 'Other', 'key' => 'other', 'organization_id' => $this->otherOrganization]);
        $unownedPortfolio = DB::table('portfolios')->insertGetId(['name' => 'Unowned', 'key' => 'unowned']);
        DB::table('locations')->where('name', 'South Campus')->update(['portfolio_id' => $otherPortfolio]);
        DB::table('locations')->where('name', 'East Campus')->update(['portfolio_id' => $unownedPortfolio]);
        $this->viewer = User::factory()->create(['is_active' => true]);
        $this->reviewer = User::factory()->create(['is_active' => true]);
        $this->viewerMembership = $this->membership($this->viewer, $this->organization, 'viewer');
        $this->reviewerMembership = $this->membership($this->reviewer, $this->organization, 'reviewer');
        $this->grant($this->viewerMembership, 'North Campus');
        $this->grant($this->reviewerMembership, 'North Campus');
    }

    private function membership(User $user, int $organization, string $role, bool $active = true): int
    {
        return DB::table('organization_memberships')->insertGetId([
            'user_id' => $user->id, 'organization_id' => $organization, 'role' => $role, 'is_active' => $active,
        ]);
    }

    private function grant(int $membership, string $location): void
    {
        DB::table('location_grants')->insert([
            'organization_membership_id' => $membership,
            'location_id' => DB::table('locations')->where('name', $location)->value('id'),
        ]);
    }

    public function test_scoped_lists_and_totals_exclude_ungranted_properties(): void
    {
        $this->assertSame(['North Campus'], $this->access->locations($this->viewer, $this->organization)->pluck('locations.name')->all());
        $this->assertSame(6, $this->access->accounts($this->viewer, $this->organization)->count());
        $this->assertSame(6, $this->access->expectedBills($this->viewer, $this->organization)->count());
        $this->assertSame(6, $this->access->statements($this->viewer, $this->organization)->count());
        $this->assertEquals(1010000, $this->access->statements($this->viewer, $this->organization)->sum('charges_cents'));
        $this->assertFalse($this->access->canViewBill($this->viewer, $this->organization, 19));
    }

    public function test_viewers_cannot_review_and_reviewers_need_explicit_property_access(): void
    {
        $this->assertTrue(Gate::forUser($this->viewer)->allows('view-bill', [$this->organization, 1]));
        $this->assertFalse(Gate::forUser($this->viewer)->allows('review-bill', [$this->organization, 1]));
        $this->assertTrue(Gate::forUser($this->reviewer)->allows('review-bill', [$this->organization, 1]));
        $this->assertFalse(Gate::forUser($this->reviewer)->allows('review-bill', [$this->organization, 19]));
        $this->assertFalse(Gate::forUser($this->reviewer)->allows('view-bill', [$this->organization, 9999]));
    }

    public function test_anonymous_unprovisioned_and_inactive_identities_have_no_access(): void
    {
        $newUser = User::factory()->create();
        $this->assertFalse($newUser->fresh()->is_active);
        $membership = $this->membership($newUser, $this->organization, 'reviewer');
        $this->grant($membership, 'North Campus');
        foreach ([null, new User, $newUser, User::factory()->create(['is_active' => true])] as $user) {
            $this->assertSame(0, $this->access->expectedBills($user, $this->organization)->count());
            $this->assertFalse($this->access->canReviewBill($user, $this->organization, 1));
        }
        $this->assertFalse(Gate::forUser(null)->allows('view-bill', [$this->organization, 1]));
    }

    public function test_cross_organization_and_unowned_grants_never_leak_records(): void
    {
        // Even malformed grants cannot bridge an organization or unowned portfolio.
        $this->grant($this->reviewerMembership, 'South Campus');
        $this->grant($this->reviewerMembership, 'East Campus');
        $this->assertSame(6, $this->access->expectedBills($this->reviewer, $this->organization)->count());
        $this->assertFalse($this->access->canViewBill($this->reviewer, $this->organization, 7));
        $this->assertFalse($this->access->canViewBill($this->reviewer, $this->organization, 13));
        $this->assertFalse($this->access->canViewBill($this->reviewer, $this->otherOrganization, 7));
        $this->assertFalse($this->access->canViewBill($this->reviewer, 9999, 1));
    }

    public function test_membership_in_second_organization_does_not_transfer_first_organization_grants(): void
    {
        $second = $this->membership($this->reviewer, $this->otherOrganization, 'reviewer');
        $this->assertSame(0, $this->access->statements($this->reviewer, $this->otherOrganization)->count());
        $this->grant($second, 'South Campus');
        $this->assertSame(6, $this->access->expectedBills($this->reviewer, $this->otherOrganization)->count());
        $this->assertSame(5, $this->access->statements($this->reviewer, $this->otherOrganization)->count());
        $this->assertTrue($this->access->canViewBill($this->reviewer, $this->otherOrganization, 12));
        $this->assertFalse($this->access->canReviewBill($this->reviewer, $this->otherOrganization, 12));
        $this->assertFalse($this->access->canViewBill($this->reviewer, $this->otherOrganization, 1));
    }

    public function test_revocation_is_effective_with_an_already_loaded_user_and_query(): void
    {
        $query = $this->access->statements($this->reviewer, $this->organization);
        $this->assertSame(6, $query->count());
        DB::table('users')->where('id', $this->reviewer->id)->update(['is_active' => false]);
        $this->assertSame(0, $query->count());
        $this->assertFalse(Gate::forUser($this->reviewer)->allows('review-bill', [$this->organization, 1]));
        DB::table('users')->where('id', $this->reviewer->id)->update(['is_active' => true]);
        DB::table('organization_memberships')->where('id', $this->reviewerMembership)->update(['is_active' => false]);
        $this->assertSame(0, $query->count());
        DB::table('organization_memberships')->where('id', $this->reviewerMembership)->update(['is_active' => true]);
        DB::table('location_grants')->where('organization_membership_id', $this->reviewerMembership)->delete();
        $this->assertSame(0, $query->count());
    }

    public function test_role_downgrade_and_property_move_take_effect_immediately(): void
    {
        DB::table('organization_memberships')->where('id', $this->reviewerMembership)->update(['role' => 'viewer']);
        $this->assertTrue($this->access->canViewBill($this->reviewer, $this->organization, 1));
        $this->assertFalse($this->access->canReviewBill($this->reviewer, $this->organization, 1));
        DB::table('portfolios')->where('organization_id', $this->organization)->update(['organization_id' => $this->otherOrganization]);
        $this->assertSame(0, $this->access->statements($this->reviewer, $this->organization)->count());
        $this->assertSame(0, $this->access->statements($this->reviewer, $this->otherOrganization)->count());
    }

    public function test_membership_defaults_to_inactive_and_does_not_imply_all_properties(): void
    {
        $newUser = User::factory()->create(['is_active' => true]);
        $membership = DB::table('organization_memberships')->insertGetId(['user_id' => $newUser->id, 'organization_id' => $this->organization]);
        $this->grant($membership, 'North Campus');
        $this->assertSame(0, $this->access->locations($newUser, $this->organization)->count());
        DB::table('organization_memberships')->where('id', $membership)->update(['is_active' => true]);
        $this->assertSame(1, $this->access->locations($newUser, $this->organization)->count());
        $this->assertFalse($this->access->canReviewBill($newUser, $this->organization, 1));
    }
}
