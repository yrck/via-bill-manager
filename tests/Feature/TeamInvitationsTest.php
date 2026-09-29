<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $organization;

    private int $location;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Notification::fake();
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Example Team', 'key' => 'example', 'owner_user_id' => $this->owner->id]);
        $membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['organization_id' => $this->organization, 'name' => 'Example', 'key' => 'example']);
        $this->location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Example Location']);
        DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $this->location]);
    }

    private function signIn(User $user): static
    {
        $this->app['session']->forget('password_hash_web');

        return $this->actingAs($user);
    }

    private function invite(): void
    {
        $this->signIn($this->owner)->post('/workspace/'.$this->organization.'/team/invite', [
            'email' => ' Teammate@Example.test ', 'role' => 'viewer', 'locations' => [$this->location],
        ])->assertSessionHasNoErrors();
        Notification::assertSentOnDemand(OrganizationInvitation::class, function ($notification, $channels, $notifiable) {
            $this->token = $notification->token;

            return $notifiable->routes['mail'] === 'teammate@example.test';
        });
    }

    public function test_existing_user_joins_with_only_selected_role_and_properties_and_link_is_single_use(): void
    {
        $this->invite();
        $this->get('/workspace/'.$this->organization.'/team')->assertOk()->assertSee('Pending invitations')->assertSee('teammate@example.test');
        $this->assertDatabaseHas('organization_invitations', ['token_hash' => hash('sha256', $this->token), 'email' => 'teammate@example.test']);
        $this->assertStringNotContainsString($this->token, json_encode(DB::table('organization_invitations')->first()));
        $teammate = User::factory()->create(['email' => 'teammate@example.test', 'is_active' => true]);
        $this->signIn($teammate)->post('/invitations/'.$this->token.'/accept', ['role' => 'reviewer', 'organization_id' => 999])->assertRedirect('/workspace/'.$this->organization);
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $this->organization, 'user_id' => $teammate->id, 'role' => 'viewer']);
        $this->assertSame([$this->location], app(BillingAccess::class)->locations($teammate, $this->organization)->pluck('locations.id')->all());
        $this->assertSame(0, app(BillingAccess::class)->locations($teammate, $this->organization, true)->count());
        $this->post('/invitations/'.$this->token.'/accept')->assertNotFound();
        $this->get('/workspace/'.$this->organization.'/team')->assertNotFound();
        $this->get('/workspace/'.$this->organization)->assertOk();
    }

    public function test_invited_registration_creates_login_without_another_organization_and_requires_verification(): void
    {
        $this->invite();
        $this->post('/logout');
        $this->get('/invitations/'.$this->token)->assertOk()->assertSee('Example Team');
        $this->post('/invitations/'.$this->token.'/register', ['name' => 'New Teammate', 'email' => 'attacker@example.test', 'password' => 'fictional-invite-pass', 'password_confirmation' => 'fictional-invite-pass'])->assertRedirect('/verify-email');
        $user = User::where('email', 'teammate@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('organization_memberships', 1);
        $this->post('/invitations/'.$this->token.'/accept')->assertRedirect('/verify-email');
        $user->markEmailAsVerified();
        $this->signIn($user)->get('/workspace')->assertRedirect('/invitations/'.$this->token);
        $this->get('/invitations/'.$this->token)->assertOk();
        $this->post('/invitations/'.$this->token.'/accept')->assertRedirect('/workspace/'.$this->organization);
        $this->assertDatabaseCount('organization_memberships', 2);
    }

    public function test_wrong_email_cannot_accept_and_guests_cannot_accept(): void
    {
        $this->invite();
        $this->post('/logout');
        $this->post('/invitations/'.$this->token.'/accept')->assertRedirect('/login');
        $other = User::factory()->create(['is_active' => true]);
        $this->signIn($other)->post('/invitations/'.$this->token.'/accept')->assertForbidden();
        $this->assertDatabaseCount('organization_memberships', 1);
    }

    public function test_nonowners_cannot_list_invite_or_cancel_and_foreign_properties_are_rejected(): void
    {
        $this->invite();
        $id = DB::table('organization_invitations')->value('id');
        $other = User::factory()->create(['is_active' => true]);
        DB::table('organization_memberships')->insert(['organization_id' => $this->organization, 'user_id' => $other->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->signIn($other)->get('/workspace/'.$this->organization.'/team')->assertNotFound();
        $this->post('/workspace/'.$this->organization.'/team/invite', ['email' => 'other@example.test', 'role' => 'viewer'])->assertNotFound();
        $this->post('/workspace/'.$this->organization.'/team/invitations/'.$id.'/cancel')->assertNotFound();
        $this->signIn($this->owner)->post('/workspace/'.$this->organization.'/team/invite', ['email' => 'other@example.test', 'role' => 'viewer', 'locations' => [9999]])->assertSessionHasErrors('locations');
        $this->post('/workspace/'.$this->organization.'/team/invite', ['email' => 'other@example.test', 'role' => 'owner'])->assertSessionHasErrors('role');
    }

    public function test_cancelled_links_cannot_be_accepted(): void
    {
        $this->invite();
        $id = DB::table('organization_invitations')->value('id');
        $this->post('/workspace/'.$this->organization.'/team/invitations/'.$id.'/cancel')->assertSessionHasNoErrors();
        $this->get('/invitations/'.$this->token)->assertNotFound();

    }

    public function test_expired_invitation_is_rejected_without_creating_membership(): void
    {
        $this->invite();
        $user = User::factory()->create(['email' => 'teammate@example.test', 'is_active' => true]);
        $this->travel(8)->days();
        $this->signIn($user)->post('/invitations/'.$this->token.'/accept')->assertNotFound();
        $this->assertDatabaseCount('organization_memberships', 1);
    }

    public function test_existing_customer_keeps_their_own_organization_when_accepting_another(): void
    {
        $this->invite();
        $user = User::factory()->create(['email' => 'teammate@example.test', 'is_active' => true]);
        $other = DB::table('organizations')->insertGetId(['name' => 'Existing Customer', 'key' => 'existing', 'owner_user_id' => $user->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $user->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->signIn($user)->post('/invitations/'.$this->token.'/accept')->assertRedirect('/workspace/'.$this->organization);
        $this->get('/workspace')->assertOk()->assertSee('Existing Customer')->assertSee('Example Team');
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $other, 'user_id' => $user->id, 'role' => 'reviewer']);
    }

    public function test_duplicate_invites_are_rejected_and_existing_members_cannot_be_reactivated_by_invite(): void
    {
        $this->invite();
        $this->post('/workspace/'.$this->organization.'/team/invite', ['email' => 'teammate@example.test', 'role' => 'reviewer'])->assertSessionHasErrors('email');
        $user = User::factory()->create(['email' => 'teammate@example.test', 'is_active' => true]);
        DB::table('organization_memberships')->insert(['organization_id' => $this->organization, 'user_id' => $user->id, 'role' => 'viewer', 'is_active' => false]);
        $this->signIn($user)->post('/invitations/'.$this->token.'/accept')->assertSessionHasErrors('invitation');
        $this->assertDatabaseHas('organization_memberships', ['user_id' => $user->id, 'is_active' => false]);
    }

    public function test_changed_property_grants_or_suspended_organization_invalidate_acceptance(): void
    {
        $this->invite();
        $user = User::factory()->create(['email' => 'teammate@example.test', 'is_active' => true]);
        DB::table('location_grants')->delete();
        $this->signIn($user)->post('/invitations/'.$this->token.'/accept')->assertSessionHasErrors('invitation');
        $this->assertDatabaseCount('organization_memberships', 1);
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->get('/invitations/'.$this->token)->assertNotFound();
    }
}
