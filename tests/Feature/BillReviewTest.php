<?php

namespace Tests\Feature;

use App\Livewire\BillDetail;
use App\Livewire\Bills;
use App\Livewire\Dashboard;
use Database\Seeders\DemoPortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BillReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['demo.enabled' => true]);
        $this->withoutVite();
        $this->seed(DemoPortfolioSeeder::class);
    }

    public function test_register_filters_account_status_and_location(): void
    {
        $this->get('/bills')->assertOk()->assertSee('24 of 24 records');
        Livewire::test(Bills::class)->set('status', 'missing')->assertSee('3 of 24 records')->assertDontSee('DEMO-201')
            ->set('scope', 'South Campus')->assertSee('DEMO-206')->assertDontSee('DEMO-306')
            ->set('search', 'nothing-matches')->assertSee('No bills match');
        Livewire::test(Bills::class)->set('search', 'example gas')->assertSee('12 of 24 records');
    }

    public function test_review_persists_note_preserves_finding_and_reconciles_dashboard(): void
    {
        $this->get('/bills/7')->assertOk()->assertSee('No source bill attached')->assertSee('24,100 kWh');
        Livewire::test(BillDetail::class, ['expectedBill' => 7])
            ->set('note', 'Fictional occupancy increase explains the usage variance.')
            ->call('decide', 'verify')->assertHasNoErrors()->assertSee('Demo review saved')->assertSee('Reopen review');
        $this->assertDatabaseHas('statements', ['expected_bill_id' => 7, 'status' => 'verified', 'review_version' => 1]);
        $this->assertDatabaseHas('demo_review_decisions', ['from_status' => 'review', 'to_status' => 'verified', 'note' => 'Fictional occupancy increase explains the usage variance.']);
        $this->get('/bills/7')->assertSee('Fictional occupancy increase')->assertSee('24,100 kWh');
        Livewire::test(Dashboard::class)->call('setFilter', 'review')->assertViewHas('review', 2)->assertViewHas('verified', 19)->assertViewHas('due', 2)->assertSee('$42,860')->assertDontSee('Usage increased 31%');
        // Due-date exposure is retained after verification; payment status is unknown.
        Livewire::test(Dashboard::class)->call('setFilter', 'due')->assertSee('Statement verified');
    }

    public function test_reopening_preserves_both_decisions_and_restores_review_queue(): void
    {
        Livewire::test(BillDetail::class, ['expectedBill' => 7])
            ->set('note', 'Fictional variance accepted after review.')->call('decide', 'verify')
            ->set('note', 'Recheck the fictional occupancy assumption.')->call('decide', 'reopen')->assertHasNoErrors()
            ->assertSee('Review reopened')->assertSee('Fictional variance accepted after review.');
        $this->assertDatabaseCount('demo_review_decisions', 2);
        $this->assertDatabaseHas('statements', ['expected_bill_id' => 7, 'status' => 'review', 'review_version' => 2]);
        Livewire::test(Dashboard::class)->assertViewHas('review', 3)->assertViewHas('verified', 18);
    }

    public function test_reopening_a_seed_verified_statement_uses_an_accurate_queue_title(): void
    {
        Livewire::test(BillDetail::class, ['expectedBill' => 1])->set('note', 'Recheck this fictional statement before closing.')->call('decide', 'reopen')->assertHasNoErrors();
        Livewire::test(Dashboard::class)->call('setFilter', 'review')->assertSee('Statement reopened for review')->assertViewHas('review', 4);
    }

    public function test_invalid_notes_and_actions_do_not_change_state(): void
    {
        Livewire::test(BillDetail::class, ['expectedBill' => 7])->set('note', '    ')->call('decide', 'verify')->assertHasErrors('note')
            ->set('note', str_repeat('x', 2001))->call('decide', 'verify')->assertHasErrors('note')
            ->set('note', 'Fictional valid note for testing.')->call('decide', 'pay')->assertHasErrors('decision');
        $this->assertDatabaseCount('demo_review_decisions', 0);
        $this->assertDatabaseHas('statements', ['expected_bill_id' => 7, 'status' => 'review', 'review_version' => 0]);
    }

    public function test_stale_tab_cannot_overwrite_a_newer_decision_even_after_reopen(): void
    {
        $stale = Livewire::test(BillDetail::class, ['expectedBill' => 7]);
        Livewire::test(BillDetail::class, ['expectedBill' => 7])
            ->set('note', 'First fictional reviewer accepts variance.')->call('decide', 'verify')
            ->set('note', 'First fictional reviewer reopens variance.')->call('decide', 'reopen');
        $stale->set('note', 'Older tab tries to accept this variance.')->call('decide', 'verify')->assertHasErrors('decision')->assertSee('Reload the page');
        $this->assertDatabaseCount('demo_review_decisions', 2);
        $this->assertDatabaseHas('statements', ['expected_bill_id' => 7, 'status' => 'review', 'review_version' => 2]);
    }

    public function test_missing_statement_cannot_be_verified_and_unknown_id_is_not_found(): void
    {
        $this->get('/bills/12')->assertOk()->assertSee('Statement not received')->assertDontSee('Save as verified');
        Livewire::test(BillDetail::class, ['expectedBill' => 12])->set('note', 'Cannot review a statement not received.')->call('decide', 'verify')->assertStatus(404);
        $this->get('/bills/9999')->assertNotFound();
        $this->assertDatabaseCount('demo_review_decisions', 0);
    }

    public function test_demo_boundary_is_rechecked_on_read_and_write(): void
    {
        $component = Livewire::test(BillDetail::class, ['expectedBill' => 7])->set('note', 'Attempt a review after the scope changed.');
        $other = DB::table('portfolios')->insertGetId(['key' => 'other', 'name' => 'Other portfolio']);
        DB::table('locations')->where('name', 'South Campus')->update(['portfolio_id' => $other]);
        $this->get('/bills/7')->assertNotFound();
        $component->call('decide', 'verify')->assertStatus(404);
        $this->assertDatabaseCount('demo_review_decisions', 0);
    }

    public function test_organization_owned_portfolio_cannot_be_exposed_by_anonymous_demo(): void
    {
        $component = Livewire::test(BillDetail::class, ['expectedBill' => 7])->set('note', 'Attempt review after ownership changes.');
        $organization = DB::table('organizations')->insertGetId(['name' => 'Owned portfolio', 'key' => 'owned']);
        DB::table('portfolios')->update(['organization_id' => $organization]);
        $this->get('/bills')->assertOk()->assertSee('0 of 0 records')->assertDontSee('DEMO-201');
        $this->get('/portfolio')->assertOk()->assertDontSee('North Campus');
        $this->get('/bills/7')->assertNotFound();
        $component->call('decide', 'verify')->assertStatus(404);
        $this->assertDatabaseCount('demo_review_decisions', 0);
    }

    public function test_routes_and_write_action_require_local_demo_opt_in(): void
    {
        $component = Livewire::test(BillDetail::class, ['expectedBill' => 7])->set('note', 'Attempt review after demo gets disabled.');
        config(['demo.enabled' => false]);
        $this->get('/bills')->assertNotFound();
        $this->get('/bills/7')->assertNotFound();
        $component->call('decide', 'verify')->assertStatus(404);
        config(['demo.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->get('/bills')->assertNotFound();
        $this->get('/bills/7')->assertNotFound();
        $this->assertDatabaseCount('demo_review_decisions', 0);
    }
}
