<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerBillsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $organization;

    private int $membership;

    private int $location;

    private int $bill;

    private int $missing;

    private int $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'demo.enabled' => false]);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Customer', 'key' => 'customer', 'owner_user_id' => $this->user->id]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->user->id, 'role' => 'reviewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['name' => 'Customer', 'key' => 'customer', 'organization_id' => $this->organization]);
        $this->location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Allowed property']);
        $hiddenLocation = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Hidden property']);
        DB::table('location_grants')->insert(['organization_membership_id' => $this->membership, 'location_id' => $this->location]);
        $this->bill = $this->bill($this->location, 'Visible account', true);
        $this->missing = $this->bill($this->location, 'Missing account', false);
        $this->hidden = $this->bill($hiddenLocation, 'Secret account', true);
        $this->actingAs($this->user);
    }

    private function bill(int $location, string $reference, bool $received): int
    {
        $account = DB::table('utility_accounts')->insertGetId(['location_id' => $location, 'reference' => $reference, 'supplier' => 'Test Provider', 'commodity' => 'Electricity']);
        $bill = DB::table('expected_bills')->insertGetId(['utility_account_id' => $account, 'period' => today()->startOfMonth()->toDateString(), 'expected_by' => today()->subDay()->toDateString()]);
        if ($received) {
            DB::table('statements')->insert(['expected_bill_id' => $bill, 'charges_cents' => 12345, 'currency' => 'USD', 'received_on' => today()->toDateString(), 'status' => 'review', 'title' => 'Check charges', 'evidence' => 'Original finding remains visible.', 'owner' => 'Unassigned']);
        }

        return $bill;
    }

    private function base(): string
    {
        return '/workspace/'.$this->organization.'/bills';
    }

    private function decision(array $changes = []): array
    {
        return array_replace(['version' => 0, 'decision' => 'verify', 'note' => 'Checked the charges against the source.'], $changes);
    }

    public function test_scoped_register_filters_detail_and_missing_bills(): void
    {
        $this->get($this->base())->assertOk()->assertSee('Visible account')->assertSee('Missing account')->assertDontSee('Secret account')->assertDontSee('Hidden property');
        $this->get($this->base().'?status=review')->assertSee('Visible account')->assertDontSee('Missing account');
        $this->get($this->base().'?status=missing')->assertSee('Missing account')->assertDontSee('Visible account');
        $this->get($this->base().'?q=Visible')->assertSee('Visible account')->assertDontSee('Missing account')->assertDontSee('Secret account');
        $this->get($this->base().'?status=unknown')->assertSessionHasErrors('status');
        $this->get($this->base().'?location=99999')->assertNotFound();
        $this->get($this->base().'/'.$this->bill)->assertOk()->assertSee('USD 123.45')->assertSee('Save as verified');
        $this->get($this->base().'/'.$this->missing)->assertOk()->assertSee('Awaiting a statement')->assertDontSee('Save as verified');
        $this->post($this->base().'/'.$this->missing.'/review', $this->decision())->assertNotFound();
        $this->get($this->base().'/'.$this->hidden)->assertNotFound();
        $this->post($this->base().'/'.$this->hidden.'/review', $this->decision())->assertNotFound();
    }

    public function test_review_and_reopen_preserve_evidence_record_actor_and_reject_stale_writes(): void
    {
        $url = $this->base().'/'.$this->bill.'/review';
        $this->post($url, $this->decision() + ['actor_id' => 999])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_review_decisions', ['actor_id' => $this->user->id, 'actor_name' => $this->user->name, 'version' => 1, 'to_status' => 'verified']);
        $this->assertDatabaseHas('statements', ['expected_bill_id' => $this->bill, 'status' => 'verified', 'evidence' => 'Original finding remains visible.']);
        $this->post($url, $this->decision())->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('customer_review_decisions', 1);
        $this->post($url, $this->decision(['version' => 1, 'decision' => 'reopen']))->assertSessionHasNoErrors();
        $this->get($this->base().'/'.$this->bill)->assertSee($this->user->name)->assertSee('Checked the charges against the source.');
        $this->assertDatabaseCount('customer_review_decisions', 2);
        $this->assertDatabaseCount('demo_review_decisions', 0);
    }

    public function test_viewer_downgrade_revocation_and_suspension_block_writes(): void
    {
        $url = $this->base().'/'.$this->bill.'/review';
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'viewer']);
        $this->get($this->base().'/'.$this->bill)->assertOk()->assertDontSee('Save as verified');
        $this->post($url, $this->decision())->assertNotFound();
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'reviewer']);
        DB::table('location_grants')->where('organization_membership_id', $this->membership)->delete();
        $this->post($url, $this->decision())->assertNotFound();
        $this->get($this->base().'/'.$this->bill)->assertNotFound();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->get($this->base())->assertNotFound();
        $this->assertDatabaseCount('customer_review_decisions', 0);
    }

    public function test_cross_account_and_unverified_access_and_invalid_notes_are_rejected(): void
    {
        $this->post($this->base().'/'.$this->bill.'/review', $this->decision(['note' => '   ']))->assertSessionHasErrors('note');
        $other = DB::table('organizations')->insertGetId(['name' => 'Other', 'key' => 'other']);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $this->user->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->get('/workspace/'.$other.'/bills/'.$this->bill)->assertNotFound();
        $this->post('/workspace/'.$other.'/bills/'.$this->bill.'/review', $this->decision())->assertNotFound();
        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->get($this->base())->assertRedirect('/verify-email');
        $this->post($this->base().'/'.$this->bill.'/review', $this->decision())->assertRedirect('/verify-email');
        $this->assertDatabaseCount('customer_review_decisions', 0);
    }

    public function test_manager_preview_uses_target_grants_without_write_authority(): void
    {
        $manager = User::factory()->create(['email' => 'manager@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->user->id, 'reason' => 'Review customer issue.'])->assertRedirect();
        $this->get('/management/customer-view/bills')->assertOk()->assertSee('Visible account')->assertDontSee('Secret account');
        $this->get('/management/customer-view/bills/'.$this->bill)->assertOk()->assertSee('Read-only customer view')->assertDontSee('Save as verified');
        $this->get('/management/customer-view/bills/'.$this->hidden)->assertNotFound();
        $this->post($this->base().'/'.$this->bill.'/review', $this->decision())->assertNotFound();
        DB::table('location_grants')->where('organization_membership_id', $this->membership)->delete();
        $this->get('/management/customer-view/bills/'.$this->bill)->assertNotFound();
        $this->assertDatabaseCount('customer_review_decisions', 0);
    }
}
