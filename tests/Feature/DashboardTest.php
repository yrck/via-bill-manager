<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use Database\Seeders\DemoPortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->withoutVite();
        $this->seed(DemoPortfolioSeeder::class);
    }

    public function test_dashboard_discloses_demo_and_reconciles_coverage(): void
    {
        $this->get('/')->assertOk()->assertSee('DEMO ENVIRONMENT')->assertSee('$42,860')->assertSee('21 of 24 expected statements received');
    }

    public function test_filters_and_scope_reconcile_without_inventing_missing_amounts(): void
    {
        Livewire::test(Dashboard::class)->call('setFilter', 'missing')->assertSee('September statement missing')->assertDontSee('Usage increased 31%')
            ->set('scope', 'North Campus')->assertSee('Nothing needs attention')->assertSee('$10,100')->assertSee('6 of 6 expected statements received');
    }

    public function test_due_filter_and_evidence_are_functional(): void
    {
        Livewire::test(Dashboard::class)->call('setFilter', 'due')->assertSee('Usage increased 31%')->assertSee('Estimated meter reading')->assertDontSee('Charge total needs checking')
            ->call('inspect', 7)->assertSee('24,100 kWh')->set('scope', 'East Campus')->assertSet('selected', null)->assertDontSee('24,100 kWh');
    }

    public function test_out_of_scope_detail_is_rejected(): void
    {
        Livewire::test(Dashboard::class)->set('scope', 'North Campus')->call('inspect', 7)->assertStatus(404);
    }

    public function test_demo_is_disabled_when_not_opted_in(): void
    {
        config(['demo.enabled' => false]);
        $this->get('/')->assertNotFound();
    }

    public function test_production_does_not_expose_demo_even_with_flag(): void
    {
        $this->app->instance('env', 'production');
        $this->get('/')->assertNotFound();
    }

    public function test_database_changes_drive_totals_and_future_expectations_are_not_missing(): void
    {
        DB::table('statements')->where('expected_bill_id', 1)->update(['charges_cents' => 220000]);
        DB::table('expected_bills')->where('id', 12)->update(['expected_by' => '2026-09-30']);
        $this->get('/')->assertOk()->assertSee('$42,960')->assertSee('21 of 24 expected statements received')->assertSee('Not yet expected');
        Livewire::test(Dashboard::class)->call('setFilter', 'missing')->assertViewHas('missing', 2)->assertViewHas('work', fn ($work) => $work->count() === 2);
    }

    public function test_seeding_is_repeatable_and_preserves_local_edits(): void
    {
        DB::table('statements')->where('expected_bill_id', 1)->update(['charges_cents' => 220000]);
        $this->seed(DemoPortfolioSeeder::class);
        $this->assertDatabaseCount('locations', 4);
        $this->assertDatabaseCount('utility_accounts', 24);
        $this->assertDatabaseCount('expected_bills', 24);
        $this->assertDatabaseCount('statements', 21);
        $this->assertDatabaseHas('statements', ['expected_bill_id' => 1, 'charges_cents' => 220000]);
    }

    public function test_portfolio_lists_synthetic_accounts_and_obeys_demo_gate(): void
    {
        $this->get('/portfolio')->assertOk()->assertSee('DEMO-101')->assertSee('West Campus')->assertSee('24 utility accounts');
        config(['demo.enabled' => false]);
        $this->get('/portfolio')->assertNotFound();
    }

    public function test_seeder_cannot_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        app(DemoPortfolioSeeder::class)->run();
    }

    public function test_due_date_includes_verified_statements_without_implying_payment(): void
    {
        DB::table('statements')->where('expected_bill_id', 1)->update(['due_on' => '2026-10-01']);
        Livewire::test(Dashboard::class)->call('setFilter', 'due')->assertViewHas('due', 3)->assertSee('Statement verified')->assertSee('Payment status unavailable');
    }
}
