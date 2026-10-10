<?php

namespace Tests\Feature;

use App\Billing\BillReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $organization;

    private int $membership;

    private int $location;

    private int $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->travelTo(now()->setDate(2026, 10, 10)->startOfDay());
        $this->user = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['key' => 'report', 'name' => 'Fictional report', 'owner_user_id' => $this->user->id]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->user->id, 'role' => 'viewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['key' => 'report', 'name' => 'Report', 'organization_id' => $this->organization]);
        $this->location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Fictional site']);
        DB::table('location_grants')->insert(['location_id' => $this->location, 'organization_membership_id' => $this->membership]);
        $this->account = DB::table('utility_accounts')->insertGetId(['location_id' => $this->location, 'reference' => '001234567890123456', 'commodity' => 'Electricity', 'supplier' => 'Fictional Utility']);
        $this->actingAs($this->user);
    }

    private function url(): string
    {
        return '/workspace/'.$this->organization.'/reports/bills';
    }

    private function bill(string $month, ?int $charges, array $changes = []): int
    {
        $bill = DB::table('expected_bills')->insertGetId(['utility_account_id' => $this->account, 'period' => $month, 'expected_by' => $month, 'expectation_source' => 'schedule']);
        if ($charges !== null) {
            DB::table('statements')->insert(array_replace(['expected_bill_id' => $bill, 'charges_cents' => $charges, 'balance_forward_cents' => 10000, 'amount_due_cents' => $charges + 10000, 'currency' => 'USD', 'usage_kwh' => 300, 'service_start' => $month, 'service_end' => $month, 'received_on' => '2026-10-01', 'status' => 'review', 'title' => 'Fictional source', 'evidence' => 'Entered manually', 'owner' => 'Unassigned'], $changes));
        }

        return $bill;
    }

    private function csv(string $query = ''): array
    {
        $response = $this->get($this->url().'.csv'.$query)->assertOk()->assertDownload()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($response->streamedContent(), 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_screen_and_csv_reconcile_credits_and_unknown_amounts(): void
    {
        $this->bill('2026-08-01', 10001);
        $this->bill('2026-09-01', -250);
        $this->bill('2026-10-01', null);
        $this->get($this->url())->assertOk()->assertSee('USD 97.51')->assertSee('1 amounts unknown')->assertViewHas('rows', fn ($rows) => $rows->count() === 3);
        $rows = $this->csv();
        $this->assertCount(4, $rows);
        $this->assertSame(['100.01', '-2.50', ''], array_column(array_slice($rows, 1), 15));
        $this->assertSame(['100.00', '100.00', ''], array_column(array_slice($rows, 1), 16));
        $this->assertSame('', $rows[3][17]);
        $this->assertSame("'001234567890123456", $rows[1][8]);
        $this->assertSame('2026-01-01', $rows[1][1]);
        $this->assertSame('2026-10-01', $rows[1][2]);
    }

    public function test_filters_grace_boundary_and_exclusions_match_both_outputs(): void
    {
        $received = $this->bill('2026-08-01', 900, ['status' => 'verified']);
        $missing = $this->bill('2026-09-01', null);
        $awaiting = $this->bill('2026-10-01', null);
        DB::table('expected_bills')->where('id', $awaiting)->update(['missing_after' => '2026-10-10']);
        foreach (['verified' => $received, 'missing' => $missing, 'awaiting' => $awaiting] as $status => $id) {
            $query = '?from=2026-08-01&to=2026-10-01&location='.$this->location.'&status='.$status;
            $this->get($this->url().$query)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$id]);
            $csv = $this->csv($query);
            $this->assertCount(2, $csv);
            $this->assertSame((string) $id, $csv[1][5]);
        }
        DB::table('expected_bills')->whereIn('id', [$received, $missing])->update(['is_excluded' => true]);
        $this->get($this->url())->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$received, $awaiting]);
        $this->assertCount(2, $this->csv('?from=2026-08-01&to=2026-08-01'));
    }

    public function test_only_current_projection_counts_and_currencies_are_separate(): void
    {
        $first = $this->bill('2026-08-01', 100);
        $this->bill('2026-09-01', 200, ['currency' => 'CAD']);
        $statement = DB::table('statements')->where('expected_bill_id', $first)->first();
        DB::table('statement_revisions')->insert(['statement_id' => $statement->id, 'number' => 1, 'snapshot' => json_encode($statement), 'reason' => 'Original retained', 'created_at' => now()]);
        DB::table('statements')->where('id', $statement->id)->update(['charges_cents' => 300, 'revision_number' => 2]);
        $this->get($this->url())->assertOk()->assertSee('USD 3.00')->assertSee('CAD 2.00')->assertViewHas('rows', fn ($rows) => $rows->count() === 2);
        $csv = $this->csv();
        $this->assertCount(3, $csv);
        $this->assertSame('2', $csv[1][12]);
        $this->assertSame('3.00', $csv[1][15]);
    }

    public function test_text_is_csv_quoted_and_formula_safe_without_changing_negative_money(): void
    {
        $this->bill('2026-09-01', -123, ['invoice_number' => ' =HYPERLINK("example")']);
        DB::table('locations')->where('id', $this->location)->update(['name' => "Site, with \"quotes\"\nand a newline"]);
        DB::table('utility_accounts')->where('id', $this->account)->update(['supplier' => '@SUM(1,2)']);
        $csv = $this->csv();
        $this->assertSame("Site, with \"quotes\"\nand a newline", $csv[1][7]);
        $this->assertSame("'@SUM(1,2)", $csv[1][9]);
        $this->assertSame("' =HYPERLINK(\"example\")", $csv[1][13]);
        $this->assertSame('-1.23', $csv[1][15]);
    }

    public function test_foreign_and_revoked_scope_never_leaks_in_reports_or_exports(): void
    {
        $this->bill('2026-09-01', 123);
        $other = DB::table('organizations')->insertGetId(['key' => 'other-report', 'name' => 'Other', 'owner_user_id' => $this->user->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $this->user->id, 'role' => 'viewer', 'is_active' => true]);
        $this->get('/workspace/'.$other.'/reports/bills')->assertOk()->assertDontSee('Fictional site');
        $this->get('/workspace/'.$other.'/reports/bills.csv?location='.$this->location)->assertNotFound();
        DB::table('location_grants')->delete();
        $this->get($this->url())->assertOk()->assertDontSee('Fictional site');
        $this->assertCount(1, $this->csv());
        $this->get($this->url().'?location='.$this->location)->assertNotFound();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->get($this->url().'.csv')->assertNotFound();
    }

    public function test_manager_preview_exports_selected_customer_scope_and_rechecks_grants(): void
    {
        $this->bill('2026-09-01', 123);
        $manager = User::factory()->create(['email' => 'manager@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->user->id, 'reason' => 'Reviewing scoped billing reports'])->assertRedirect();
        $this->get('/management/customer-view/reports/bills')->assertOk()->assertSee('USD 1.23');
        $this->assertStringContainsString('Fictional site', $this->get('/management/customer-view/reports/bills.csv')->assertOk()->streamedContent());
        DB::table('location_grants')->delete();
        $this->assertStringNotContainsString('Fictional site', $this->get('/management/customer-view/reports/bills.csv')->assertOk()->streamedContent());
        DB::table('organization_memberships')->where('id', $this->membership)->update(['is_active' => false]);
        $this->get('/management/customer-view/reports/bills.csv')->assertNotFound();
    }

    public function test_invalid_filters_and_oversized_reports_are_rejected_not_truncated(): void
    {
        $this->get($this->url().'?from=2026-09-02')->assertSessionHasErrors('from');
        $this->get($this->url().'.csv?from=2026-10-01&to=2026-09-01')->assertSessionHasErrors('to');
        $records = [];
        for ($i = 0; $i <= BillReport::LIMIT; $i++) {
            $month = today()->startOfMonth()->subMonths($i)->toDateString();
            $records[] = ['utility_account_id' => $this->account, 'period' => $month, 'expected_by' => $month];
        }
        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('expected_bills')->insert($chunk);
        }
        foreach (['', '.csv'] as $suffix) {
            $this->get($this->url().$suffix.'?from=1600-01-01&to=2026-10-01')->assertSessionHasErrors('from');
        }
    }
}
