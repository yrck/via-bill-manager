<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['demo.enabled' => false, 'session.driver' => 'array']);
    }

    private function account(User $user, string $name, string $role = 'reviewer'): int
    {
        $id = DB::table('organizations')->insertGetId(['name' => $name, 'key' => $name, 'owner_user_id' => $user->id, 'is_active' => true]);
        DB::table('organization_memberships')->insert(['organization_id' => $id, 'user_id' => $user->id, 'role' => $role, 'is_active' => true]);

        return $id;
    }

    public function test_switcher_tracks_the_url_across_accounts_and_team_pages(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $first = $this->account($user, 'First Estates');
        $second = $this->account($user, 'Second Estates');
        $outsider = User::factory()->create(['is_active' => true]);
        $hidden = $this->account($outsider, 'Private Estates');

        $this->actingAs($user)->get('/workspace/'.$first)->assertOk()
            ->assertSee('Switch account: First Estates')->assertSee('Second Estates')
            ->assertSee(route('customer.workspace', $second))->assertDontSee('Private Estates');
        $this->get('/workspace/'.$second)->assertOk()->assertSee('Switch account: Second Estates');
        $this->get('/workspace/'.$first.'/team')->assertOk()->assertSee('Switch account: First Estates');
        $this->get('/workspace/'.$first)->assertOk()->assertSee('Switch account: First Estates');
        $this->get('/workspace/'.$hidden)->assertNotFound();
        $this->assertDatabaseCount('organization_memberships', 3);
        $this->assertDatabaseCount('location_grants', 0);
    }

    public function test_revoked_suspended_and_invalid_memberships_disappear_and_cannot_be_opened(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $first = $this->account($user, 'Active Estates');
        $revoked = $this->account($user, 'Revoked Estates');
        $suspended = $this->account($user, 'Suspended Estates');
        $this->actingAs($user)->get('/workspace/'.$first)->assertSee('Revoked Estates')->assertSee('Suspended Estates');
        DB::table('organization_memberships')->where('organization_id', $revoked)->update(['is_active' => false]);
        DB::table('organizations')->where('id', $suspended)->update(['is_active' => false]);
        $this->get('/workspace/'.$first)->assertOk()->assertDontSee('Revoked Estates')->assertDontSee('Suspended Estates');
        $this->get('/workspace/'.$revoked)->assertNotFound();
        $this->get('/workspace/'.$suspended)->assertNotFound();
        $this->get('/workspace')->assertOk()->assertSee('Active Estates')->assertDontSee('Revoked Estates');
    }

    public function test_signup_names_a_customer_account_and_unverified_users_do_not_get_switcher(): void
    {
        $this->get('/register')->assertOk()->assertSee('Account name')->assertSee('Use its link to join that account.');
        $user = User::factory()->unverified()->create(['is_active' => true]);
        $this->account($user, 'Unverified Estates');
        $this->actingAs($user)->get('/verify-email')->assertOk()->assertDontSee('account-switcher');
        $this->get('/workspace')->assertRedirect('/verify-email');
    }
}
