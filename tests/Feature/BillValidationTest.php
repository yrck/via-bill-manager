<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BillValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $organization;

    private int $account;

    private int $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['key' => 'validation', 'name' => 'Fictional validation', 'owner_user_id' => $this->user->id]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->user->id, 'role' => 'reviewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['key' => 'validation', 'name' => 'Validation', 'organization_id' => $this->organization]);
        $location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Fictional site']);
        DB::table('location_grants')->insert(['location_id' => $location, 'organization_membership_id' => $this->membership]);
        $this->account = DB::table('utility_accounts')->insertGetId(['location_id' => $location, 'reference' => 'VAL-001', 'commodity' => 'Electricity', 'supplier' => 'Fictional Utility']);
        $this->actingAs($this->user);
    }

    private function bill(string $month, string $start, string $end, array $changes = []): int
    {
        $bill = DB::table('expected_bills')->insertGetId(['utility_account_id' => $this->account, 'period' => $month, 'expected_by' => $month]);
        $values = array_replace(['expected_bill_id' => $bill, 'charges_cents' => 30000, 'currency' => 'USD', 'usage_kwh' => 300, 'service_start' => $start, 'service_end' => $end, 'received_on' => '2026-10-01', 'status' => 'review', 'title' => 'Fictional source', 'evidence' => 'Entered manually', 'owner' => 'Unassigned'], $changes);
        $id = DB::table('statements')->insertGetId($values);
        DB::table('statement_revisions')->insert(['statement_id' => $id, 'number' => 1, 'snapshot' => json_encode($values), 'reason' => 'Initial fictional statement', 'created_at' => now()]);

        return $bill;
    }

    private function evaluate(): void
    {
        app(BillValidation::class)->evaluateAccount($this->organization, $this->account);
    }

    private function findings(int $bill): array
    {
        $id = DB::table('statements')->where('expected_bill_id', $bill)->value('validation_run_id');

        return array_column(json_decode(DB::table('bill_validation_runs')->where('id', $id)->value('findings'), true), null, 'rule');
    }

    public function test_initial_history_is_insufficient_and_refresh_is_idempotent(): void
    {
        $bill = $this->bill('2026-08-01', '2026-07-01', '2026-07-31');
        $this->evaluate();
        $this->evaluate();
        $this->assertDatabaseCount('bill_validation_runs', 1);
        $this->assertSame('clear', $this->findings($bill)['Service duration']['status']);
        $this->assertSame('insufficient', $this->findings($bill)['Usage per day']['status']);
        $this->get('/workspace/'.$this->organization.'/bills/'.$bill)->assertOk()->assertSee('Insufficient data / history')->assertSee('electricity-monthly-v1');
    }

    public function test_daily_normalization_threshold_and_period_continuity(): void
    {
        $this->bill('2026-08-01', '2026-07-01', '2026-07-30');
        $bill = $this->bill('2026-09-01', '2026-07-31', '2026-08-19', ['usage_kwh' => 260, 'charges_cents' => 26000]);
        $this->evaluate();
        $this->assertSame('clear', $this->findings($bill)['Service duration']['status']);
        $this->assertSame('clear', $this->findings($bill)['Service continuity']['status']);
        $this->assertSame('clear', $this->findings($bill)['Usage per day']['status']);
        DB::table('statements')->where('expected_bill_id', $bill)->update(['usage_kwh' => 261, 'charges_cents' => 13999]);
        $this->evaluate();
        $this->assertSame('warning', $this->findings($bill)['Usage per day']['status']);
        $this->assertSame('warning', $this->findings($bill)['Current charges per day']['status']);
    }

    public function test_gap_overlap_duration_and_absent_adjacent_month(): void
    {
        $this->bill('2026-07-01', '2026-06-01', '2026-06-30');
        $gap = $this->bill('2026-08-01', '2026-07-03', '2026-07-10');
        $overlap = $this->bill('2026-09-01', '2026-07-10', '2026-08-31');
        $missing = $this->bill('2026-11-01', '2026-10-01', '2026-10-31');
        $this->evaluate();
        $this->assertStringContainsString('2 uncovered days', $this->findings($gap)['Service continuity']['explanation']);
        $this->assertSame('warning', $this->findings($gap)['Service duration']['status']);
        $this->assertStringContainsString('overlap', $this->findings($overlap)['Service continuity']['explanation']);
        $this->assertSame('warning', $this->findings($overlap)['Service duration']['status']);
        $this->assertSame('insufficient', $this->findings($missing)['Service continuity']['status']);
    }

    public function test_zero_credit_and_missing_dates_are_not_silent_passes(): void
    {
        $first = $this->bill('2026-08-01', '2026-07-01', '2026-07-31', ['usage_kwh' => 0, 'charges_cents' => -100]);
        $next = $this->bill('2026-09-01', '2026-08-01', '2026-08-31');
        $this->evaluate();
        $this->assertSame('insufficient', $this->findings($next)['Usage per day']['status']);
        $this->assertSame('insufficient', $this->findings($next)['Current charges per day']['status']);
        DB::table('statements')->where('expected_bill_id', $first)->update(['service_start' => null]);
        $this->evaluate();
        $this->assertSame('insufficient', $this->findings($first)['Service duration']['status']);
        $this->assertSame('insufficient', $this->findings($next)['Service continuity']['status']);
    }

    public function test_baseline_correction_preserves_evidence_and_rejects_stale_investigation(): void
    {
        $first = $this->bill('2026-08-01', '2026-07-01', '2026-07-30');
        $next = $this->bill('2026-09-01', '2026-07-31', '2026-08-29');
        $this->evaluate();
        $original = DB::table('statements')->where('expected_bill_id', $next)->value('validation_run_id');
        $data = ['version' => 0, 'source_revision' => 1, 'validation_run_id' => $original, 'action' => 'open', 'note' => 'Investigate these usage values.', 'next_action' => 'Contact provider', 'due_on' => '2026-10-10'];
        $url = '/workspace/'.$this->organization.'/bills/'.$next;
        $this->post($url.'/exception', $data)->assertSessionHasNoErrors();
        $evidence = DB::table('bill_validation_runs')->where('id', $original)->value('inputs');
        DB::table('statements')->where('expected_bill_id', $first)->update(['revision_number' => 2, 'usage_kwh' => 100]);
        $this->evaluate();
        $this->assertDatabaseCount('bill_validation_runs', 4);
        $this->assertSame($evidence, DB::table('bill_validation_runs')->where('id', $original)->value('inputs'));
        $this->assertSame('warning', $this->findings($next)['Usage per day']['status']);
        $this->get($url)->assertOk()->assertSee('validation updated');
        $this->post($url.'/exception', array_replace($data, ['version' => 1, 'action' => 'resolve']))->assertSessionHasErrors('exception');
        $this->assertDatabaseHas('bill_exceptions', ['status' => 'open', 'source_validation_run_id' => $original]);
        $this->assertDatabaseCount('customer_review_decisions', 0);
    }

    public function test_scoped_refresh_and_manager_preview(): void
    {
        $bill = $this->bill('2026-08-01', '2026-07-01', '2026-07-31');
        $url = '/workspace/'.$this->organization.'/bills/'.$bill;
        $this->post($url.'/validate')->assertSessionHasNoErrors();
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'viewer']);
        $this->post($url.'/validate')->assertNotFound();
        $this->get($url)->assertOk()->assertDontSee('Refresh validation');
        $manager = User::factory()->create(['is_active' => true, 'is_platform_staff' => true, 'email' => 'manager@nu-devco.com']);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->user->id, 'reason' => 'Inspecting validation evidence'])->assertRedirect();
        $this->get('/management/customer-view/bills/'.$bill)->assertOk()->assertSee('Validation evidence')->assertDontSee('Refresh validation');
        DB::table('location_grants')->delete();
        $this->get('/management/customer-view/bills/'.$bill)->assertNotFound();
        $this->assertDatabaseCount('bill_validation_runs', 1);
    }
}
