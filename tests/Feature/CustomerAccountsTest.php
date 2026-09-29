<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use Database\Seeders\DemoPortfolioSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        config(['demo.enabled' => false, 'cache.default' => 'array', 'session.driver' => 'array']);
    }

    private function registration(array $extra = []): array
    {
        return array_replace(['name' => 'Example Customer', 'organization' => 'Example Organization', 'email' => 'customer@example.test', 'password' => 'fictional-password-123', 'password_confirmation' => 'fictional-password-123'], $extra);
    }

    public function test_registration_creates_isolated_organization_ignores_privilege_fields_and_requires_verification(): void
    {
        $this->post('/register', $this->registration(['email' => ' Customer@Example.test ', 'organization_id' => 999, 'role' => 'admin', 'is_platform_staff' => true, 'email_verified_at' => now()]))->assertRedirect('/verify-email');
        $user = User::where('email', 'customer@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('fictional-password-123', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->is_platform_staff);
        $this->assertDatabaseHas('organizations', ['name' => 'Example Organization', 'owner_user_id' => $user->id]);
        $this->assertDatabaseHas('organization_memberships', ['user_id' => $user->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->assertDatabaseCount('location_grants', 0);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/workspace')->assertRedirect('/verify-email');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/workspace');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $organization = DB::table('organizations')->first();
        $this->get('/workspace/'.$organization->id)->assertOk()->assertSee('Your workspace is ready.');
    }

    public function test_bad_verification_link_does_not_verify_user(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => true]);
        $this->actingAs($user)->get('/verify-email/'.$user->id.'/invalid?signature=invalid')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_registration_validation_does_not_leave_partial_organizations(): void
    {
        $this->post('/register', $this->registration(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
        User::factory()->create(['email' => 'customer@example.test']);
        $this->post('/register', $this->registration())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_login_logout_inactive_accounts_and_login_throttle(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.test', 'password' => 'fictional-password-123', 'is_active' => true]);
        $this->post('/login', ['email' => 'CUSTOMER@example.test', 'password' => 'fictional-password-123'])->assertRedirect('/workspace');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'fictional-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'Too many attempts. Please try again in a minute.']);
    }

    public function test_customer_isolation_membership_revocation_and_account_suspension(): void
    {
        $this->post('/register', $this->registration());
        $owner = User::first();
        $owner->markEmailAsVerified();
        $organization = DB::table('organizations')->first();
        $outsider = User::factory()->create(['is_active' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($outsider)->get('/workspace/'.$organization->id)->assertNotFound();
        $this->get('/workspace')->assertDontSee('Example Organization');
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($owner)->get('/workspace/'.$organization->id)->assertOk();
        DB::table('organizations')->where('id', $organization->id)->update(['is_active' => false]);
        $this->get('/workspace/'.$organization->id)->assertNotFound();
        $this->assertSame(0, app(BillingAccess::class)->statements($owner, $organization->id)->count());
        DB::table('organizations')->where('id', $organization->id)->update(['is_active' => true]);
        DB::table('organization_memberships')->where('user_id', $owner->id)->update(['is_active' => false]);
        $this->get('/workspace/'.$organization->id)->assertNotFound();
        DB::table('users')->where('id', $owner->id)->update(['is_active' => false]);
        $this->get('/workspace')->assertForbidden();
    }

    public function test_password_recovery_has_generic_response_and_single_use_token(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.test', 'is_active' => true]);
        $message = 'If an account matches that email, a password reset link has been sent.';
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $message);
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertSessionHas('status', $message);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'new-fictional-password-123', 'password_confirmation' => 'new-fictional-password-123'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_livewire_demo_updates_remain_blocked_after_demo_is_disabled(): void
    {
        config(['demo.enabled' => true]);
        $this->seed(DemoPortfolioSeeder::class);
        $response = $this->get('/')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        config(['demo.enabled' => false]);
        $this->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'setFilter', 'params' => ['review']]]]],
        ], ['X-Livewire' => 'true'])->assertNotFound();
    }

    public function test_customer_auth_is_available_without_demo_but_demo_remains_hidden_in_production(): void
    {
        $this->app->instance('env', 'production');
        config(['demo.enabled' => true]);
        $this->get('/register')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/workspace')->assertRedirect('/login');
        $this->get('/')->assertNotFound();
        $this->get('/bills')->assertNotFound();
    }
}
