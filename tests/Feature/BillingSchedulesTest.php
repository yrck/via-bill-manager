<?php

namespace Tests\Feature;

use App\Billing\CustomerBills;
use App\Billing\CustomerOverview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $organization;

    private int $account;

    private int $location;

    private int $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 16)->startOfDay());
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        Storage::fake('bills');
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['key' => 'schedule-test', 'name' => 'Schedule test', 'owner_user_id' => $this->owner->id]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['key' => 'test', 'name' => 'Test', 'organization_id' => $this->organization]);
        $this->location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Scheduled facility']);
        DB::table('location_grants')->insert(['location_id' => $this->location, 'organization_membership_id' => $this->membership]);
        $this->account = DB::table('utility_accounts')->insertGetId(['location_id' => $this->location, 'reference' => '001', 'supplier' => 'Fictional Utility', 'commodity' => 'Electricity']);
        $this->actingAs($this->owner);
    }

    private function url(): string
    {
        return '/workspace/'.$this->organization.'/accounts/'.$this->account.'/schedule';
    }

    private function data(array $changes = []): array
    {
        return array_replace(['effective_month' => '2026-09-01', 'mode' => 'monthly', 'receipt_day' => 10, 'month_offset' => 0, 'grace_days' => 5, 'reason' => 'Confirmed the monthly receipt schedule.', 'version' => DB::table('billing_schedules')->max('id') ?? 0], $changes);
    }

    private function overview(string $period = '2026-09-01'): array
    {
        return app(CustomerOverview::class)->data($this->owner, $this->organization, $period);
    }

    public function test_unconfigured_accounts_remain_unknown_and_reads_never_generate_expectations(): void
    {
        $this->get('/workspace/'.$this->organization)->assertOk()->assertSee('Completeness is unknown')->assertSee('1 accounts without a schedule');
        $this->get($this->url())->assertOk()->assertSee('coverage unknown');
        $this->artisan('billing:refresh-schedules')->assertSuccessful();
        $this->assertDatabaseCount('expected_bills', 0);
        $this->assertSame(1, $this->overview()['unknownCount']);
    }

    public function test_owner_schedule_generates_once_and_grace_boundary_matches_register_and_dashboard(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('expected_bills', 2);
        $this->assertDatabaseHas('expected_bills', ['period' => '2026-09-01', 'expected_by' => '2026-09-10', 'missing_after' => '2026-09-15', 'expectation_source' => 'schedule']);
        $this->artisan('billing:refresh-schedules')->assertSuccessful();
        $this->assertDatabaseCount('expected_bills', 2);
        $this->assertSame(1, $this->overview()['missingCount']);
        $this->get('/workspace/'.$this->organization.'/bills?status=missing&period=2026-09-01')->assertOk()->assertViewHas('bills', fn ($bills) => $bills->total() === 1);
        $this->get('/workspace/'.$this->organization)->assertOk()->assertSee('0 of 1 scheduled bills received')->assertSee('Inspect expectation');
        $this->travelTo(now()->setDate(2026, 9, 15));
        $this->assertSame(0, $this->overview()['missingCount']);
        $this->assertSame(1, $this->overview()['awaitingCount']);
        $this->get('/workspace/'.$this->organization.'/bills?status=awaiting&period=2026-09-01')->assertViewHas('bills', fn ($bills) => $bills->total() === 1);
    }

    public function test_next_month_receipt_clamps_to_leap_month_end_and_grace_crosses_months(): void
    {
        $this->travelTo(now()->setDate(2028, 2, 29));
        $this->post($this->url(), $this->data(['effective_month' => '2028-01-01', 'receipt_day' => 31, 'month_offset' => 1, 'grace_days' => 2]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expected_bills', ['period' => '2028-01-01', 'expected_by' => '2028-02-29', 'missing_after' => '2028-03-02']);
        $this->travelTo(now()->setDate(2028, 3, 2));
        $this->assertSame(1, $this->overview('2028-01-01')['awaitingCount']);
        $this->travelTo(now()->setDate(2028, 3, 3));
        $this->assertSame(1, $this->overview('2028-01-01')['missingCount']);
    }

    public function test_closing_and_resuming_keep_history_and_past_expectations(): void
    {
        $this->post($this->url(), $this->data(['effective_month' => '2026-08-01']))->assertSessionHasNoErrors();
        $this->post($this->url(), $this->data(['mode' => 'not_expected']))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->overview()['excludedCount']);
        $this->assertSame(0, $this->overview()['missingCount']);
        $this->assertSame(1, $this->overview('2026-08-01')['missingCount']);
        $this->get('/workspace/'.$this->organization.'/bills?period=2026-09-01')->assertViewHas('bills', fn ($bills) => $bills->total() === 0);
        $this->post($this->url(), $this->data(['effective_month' => '2026-10-01']))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->overview('2026-10-01')['scheduledCount']);
        $this->assertSame(1, $this->overview()['excludedCount']);
        $this->assertDatabaseCount('billing_schedules', 3);
        $this->assertDatabaseHas('billing_schedules', ['actor_id' => $this->owner->id, 'mode' => 'not_expected']);
    }

    public function test_late_receipt_satisfies_one_expectation_and_closure_never_hides_received_bill(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->post('/workspace/'.$this->organization.'/bills', [
            'utility_account_id' => $this->account, 'invoice_number' => 'TEST-001', 'period' => '2026-09-01', 'issued_on' => '2026-09-11',
            'service_start' => '2026-08-01', 'service_end' => '2026-08-31', 'due_on' => '2026-09-29',
            'current_charges' => '10.00', 'balance_forward' => '0.00', 'amount_due' => '10.00', 'usage_kwh' => '50.000',
            'document' => UploadedFile::fake()->createWithContent('fictional.pdf', "%PDF-1.4\n% fictional\n%%EOF"),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expected_bills', 2);
        $this->assertSame(1, $this->overview()['expectedReceived']);
        $this->assertSame(0, $this->overview()['missingCount']);
        $this->assertSame(1, $this->overview()['reviewCount']);
        $this->post($this->url(), $this->data(['mode' => 'not_expected']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('statements', 1);
        $this->get('/workspace/'.$this->organization.'/bills?period=2026-09-01')->assertViewHas('bills', fn ($bills) => $bills->total() === 1);
        $bill = DB::table('statements')->value('expected_bill_id');
        $this->get('/workspace/'.$this->organization.'/bills/'.$bill)->assertOk()->assertSee('Not expected under the current schedule');
    }

    public function test_stale_and_invalid_edits_do_not_rewrite_schedule(): void
    {
        $first = $this->data();
        $this->post($this->url(), $first)->assertSessionHasNoErrors();
        $this->post($this->url(), $first)->assertSessionHasErrors('version');
        $this->post($this->url(), $this->data(['receipt_day' => 32]))->assertSessionHasErrors('receipt_day');
        $this->post($this->url(), $this->data(['effective_month' => '2026-09-02']))->assertSessionHasErrors('effective_month');
        $this->assertDatabaseCount('billing_schedules', 1);
    }

    public function test_nonowners_and_foreign_accounts_are_denied_and_revoked_grants_remove_counts(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $member = User::factory()->create(['is_active' => true]);
        $membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $member->id, 'role' => 'reviewer', 'is_active' => true]);
        DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $this->location]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($member)->get($this->url())->assertOk()->assertDontSee('Save billing schedule');
        $this->post($this->url(), $this->data())->assertNotFound();
        $other = DB::table('organizations')->insertGetId(['key' => 'other', 'name' => 'Other', 'owner_user_id' => $member->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $member->id, 'role' => 'reviewer']);
        $this->post('/workspace/'.$other.'/accounts/'.$this->account.'/schedule', $this->data())->assertNotFound();
        DB::table('location_grants')->where('organization_membership_id', $membership)->delete();
        $this->get($this->url())->assertNotFound();
        $this->assertSame(0, app(CustomerOverview::class)->data($member, $this->organization, '2026-09-01')['scheduledCount']);
        $this->assertSame(0, app(CustomerBills::class)->query($member, $this->organization)->count());
    }

    public function test_refresh_skips_suspended_organizations_and_missing_generation_is_explicit(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 12, 16));
        $this->assertSame(1, $this->overview('2026-12-01')['pendingCount']);
        $this->get('/workspace/'.$this->organization)->assertSee('Coverage pending refresh');
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->artisan('billing:refresh-schedules')->assertSuccessful();
        $this->assertDatabaseCount('expected_bills', 2);
        $this->post($this->url(), $this->data())->assertNotFound();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => true]);
        $this->artisan('billing:refresh-schedules')->assertSuccessful();
        $this->assertSame(0, $this->overview('2026-12-01')['pendingCount']);
        $this->assertSame(1, $this->overview('2026-12-01')['missingCount']);
    }

    public function test_future_change_does_not_change_current_rule_and_same_month_override_is_recorded(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->post($this->url(), $this->data(['effective_month' => '2026-10-01', 'receipt_day' => 20]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expected_bills', ['period' => '2026-09-01', 'expected_by' => '2026-09-10']);
        $this->assertDatabaseHas('expected_bills', ['period' => '2026-10-01', 'expected_by' => '2026-10-20']);
        $this->post($this->url(), $this->data(['receipt_day' => 12]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expected_bills', ['period' => '2026-09-01', 'expected_by' => '2026-09-12']);
        $this->assertDatabaseHas('expected_bills', ['period' => '2026-10-01', 'expected_by' => '2026-10-20']);
        $this->assertDatabaseCount('billing_schedules', 3);
    }

    public function test_manager_preview_uses_customer_scope_without_schedule_write_access(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $manager = User::factory()->create(['email' => 'manager@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->owner->id, 'reason' => 'Checking collection coverage'])->assertRedirect();
        $this->get('/management/customer-view')->assertOk()->assertSee('0 of 1 scheduled bills received');
        $this->post($this->url(), $this->data())->assertNotFound();
        DB::table('location_grants')->delete();
        $this->get('/management/customer-view')->assertOk()->assertDontSee('Scheduled facility')->assertSee('Completeness is unknown');
        $this->assertDatabaseCount('billing_schedules', 1);
    }
}
