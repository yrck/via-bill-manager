<?php

namespace Tests\Feature;

use App\Billing\CustomerBills;
use App\Models\User;
use App\Services\BillIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BillIntakeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $organization;

    private int $membership;

    private int $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('bills');
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Fictional', 'key' => 'fictional', 'owner_user_id' => $this->user->id]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->user->id, 'role' => 'reviewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['name' => 'Test', 'key' => 'test', 'organization_id' => $this->organization]);
        $location = DB::table('locations')->insertGetId(['name' => 'Test', 'portfolio_id' => $portfolio]);
        DB::table('location_grants')->insert(['organization_membership_id' => $this->membership, 'location_id' => $location]);
        $this->account = DB::table('utility_accounts')->insertGetId(['location_id' => $location, 'reference' => '000123', 'supplier' => 'Example', 'commodity' => 'Electricity']);
        $this->actingAs($this->user);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['utility_account_id' => $this->account, 'invoice_number' => 'TEST-1', 'period' => '2026-06-01', 'issued_on' => '2026-06-11', 'service_start' => '2026-05-06', 'service_end' => '2026-06-05', 'due_on' => '2026-06-29', 'current_charges' => '100.01', 'balance_forward' => '-25.02', 'amount_due' => '74.99', 'usage_kwh' => '123.456', 'document' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\n% fictional source\n%%EOF")], $changes);
    }

    private function base(): string
    {
        return '/workspace/'.$this->organization.'/bills';
    }

    public function test_upload_preserves_credit_and_source_requires_review_and_download_is_private(): void
    {
        $this->get($this->base().'/upload')->assertOk();
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $bill = DB::table('expected_bills')->value('id');
        $this->assertDatabaseHas('statements', ['charges_cents' => 10001, 'balance_forward_cents' => -2502, 'amount_due_cents' => 7499, 'status' => 'review']);
        $this->assertDatabaseHas('bill_documents', ['uploaded_by' => $this->user->id, 'source' => 'customer_upload']);
        $this->get($this->base().'/'.$bill)->assertOk()->assertSee('USD 74.99')->assertSee('Download PDF')->assertSee('Not scheduled');
        $this->get($this->base().'/'.$bill.'/document')->assertOk()->assertDownload()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('customer_review_decisions', 0);
        $this->get('/workspace/'.$this->organization)->assertOk()->assertSee('Inspect statement');
        $this->post($this->base().'/'.$bill.'/review', ['version' => 0, 'decision' => 'verify', 'note' => 'Checked the source statement carefully.'])->assertSessionHasNoErrors();
        $this->get('/workspace/'.$this->organization)->assertOk()->assertDontSee('Inspect statement');
    }

    public function test_duplicates_and_reconciliation_fail_without_extra_files_or_records(): void
    {
        $this->post($this->base(), $this->data(['amount_due' => '75.00']))->assertSessionHasErrors('amount_due');
        $this->assertDatabaseCount('statements', 0);
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $this->post($this->base(), $this->data())->assertSessionHasErrors('document');
        $this->post($this->base(), $this->data(['invoice_number' => 'TEST-2', 'document' => UploadedFile::fake()->createWithContent('different.pdf', "%PDF-1.4\n% different source\n%%EOF")]))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('statements', 1);
        $this->assertCount(1, Storage::disk('bills')->allFiles());
    }

    public function test_invalid_file_dates_and_money_are_rejected(): void
    {
        $this->post($this->base(), $this->data(['document' => UploadedFile::fake()->createWithContent('fake.pdf', '<html>not a pdf</html>')]))->assertSessionHasErrors('document');
        $this->post($this->base(), $this->data(['service_start' => '2026-07-01']))->assertSessionHasErrors('service_end');
        $this->post($this->base(), $this->data(['current_charges' => '1e2']))->assertSessionHasErrors('current_charges');
        $this->post($this->base(), $this->data(['utility_account_id' => 999]))->assertNotFound();
        $this->assertDatabaseCount('statements', 0);
        $this->assertCount(0, Storage::disk('bills')->allFiles());
    }

    public function test_viewers_can_download_but_not_upload_and_revocations_deny_documents(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'viewer']);
        $this->get($this->base().'/'.$bill.'/document')->assertOk();
        $this->post($this->base(), $this->data())->assertNotFound();
        $this->get($this->base().'/upload')->assertNotFound();
        DB::table('location_grants')->delete();
        $this->get($this->base().'/'.$bill.'/document')->assertNotFound();
    }

    public function test_cross_account_and_manager_preview_document_boundaries(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        $other = DB::table('organizations')->insertGetId(['name' => 'Other', 'key' => 'other']);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $this->user->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->get('/workspace/'.$other.'/bills/'.$bill.'/document')->assertNotFound();
        $manager = User::factory()->create(['email' => 'employee@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->user->id, 'reason' => 'Investigating bill issue'])->assertRedirect();
        $this->get('/management/customer-view/bills/'.$bill.'/document')->assertOk();
        $this->post($this->base(), $this->data())->assertNotFound();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->get('/management/customer-view/bills/'.$bill.'/document')->assertNotFound();
    }

    public function test_trusted_console_intake_requires_explicit_test_account(): void
    {
        $data = $this->data();
        $file = $data['document'];
        $this->expectException(HttpException::class);
        app(BillIntake::class)->record($this->organization, $data, $file->getRealPath(), 'test.pdf', null);
    }

    private function correctionData(array $changes = []): array
    {
        return $this->data(array_replace([
            'document' => null, 'version' => 0, 'reason' => 'Corrected the printed charge transcription.',
            'current_charges' => '85.00', 'amount_due' => '59.98',
            'line_items' => [
                ['description' => 'Energy charge', 'category' => 'consumption', 'quantity' => '100.123456', 'unit' => 'kWh', 'rate' => '0.79900001', 'rate_unit' => 'USD/kWh', 'amount' => '80.00', 'source_reference' => 'Page 2'],
                ['description' => 'Sales tax', 'category' => 'tax', 'amount' => '5.00'],
            ],
        ], $changes));
    }

    public function test_correction_preserves_original_evidence_and_reviews_and_counts_only_current_values(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        $this->post($this->base().'/'.$bill.'/review', ['version' => 0, 'decision' => 'verify', 'note' => 'Checked original source values.'])->assertSessionHasNoErrors();
        $this->get($this->base().'/'.$bill.'/correct')->assertOk()->assertSee('Correct the record');
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData(['version' => 1]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('statements', 1);
        $this->assertDatabaseCount('statement_revisions', 2);
        $this->assertDatabaseCount('bill_documents', 1);
        $this->assertDatabaseHas('statements', ['charges_cents' => 8500, 'status' => 'review', 'review_version' => 2, 'revision_number' => 2]);
        $this->assertDatabaseHas('customer_review_decisions', ['revision_number' => 1, 'to_status' => 'verified']);
        $this->assertDatabaseHas('statement_revisions', ['number' => 2, 'actor_id' => $this->user->id]);
        $original = json_decode(DB::table('statement_revisions')->where('number', 1)->value('snapshot'), true);
        $this->assertSame(10001, $original['charges_cents']);
        $query = app(CustomerBills::class)->query($this->user, $this->organization);
        $this->assertSame(1, $query->count());
        $this->assertEquals(8500, $query->sum('s.charges_cents'));
        $this->get($this->base().'/'.$bill)->assertOk()->assertSee('USD 85.00')->assertSee('Energy charge')->assertSee('Version 2')->assertSee('Correct statement');
        $this->get($this->base().'/'.$bill.'?revision=1')->assertOk()->assertSee('USD 100.01')->assertSee('Historical version 1')->assertDontSee('Correct statement')->assertDontSee('Save as verified');
        $this->get($this->base().'/'.$bill.'/document?revision=1')->assertOk()->assertDownload();
        $this->get('/workspace/'.$this->organization)->assertOk()->assertSee('Inspect statement');
        $this->post($this->base().'/'.$bill.'/review', ['version' => 2, 'decision' => 'verify', 'note' => 'Checked revised source values.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_review_decisions', ['revision_number' => 2, 'to_status' => 'verified']);
    }

    public function test_replacement_pdf_and_line_items_remain_available_for_each_version(): void
    {
        $this->post($this->base(), $this->data(['line_items' => [['description' => 'Original charge', 'category' => 'other', 'amount' => '100.01']]]))->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        $originalDocument = DB::table('bill_documents')->first();
        $replacement = UploadedFile::fake()->createWithContent('replacement.pdf', "%PDF-1.4\n% revised source\n%%EOF");
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData(['document' => $replacement, 'invoice_number' => 'TEST-REVISED']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bill_documents', 2);
        $this->assertCount(2, Storage::disk('bills')->allFiles());
        $this->assertEquals($originalDocument->id, DB::table('statement_revisions')->where('number', 1)->value('document_id'));
        $this->assertNotEquals($originalDocument->id, DB::table('statement_revisions')->where('number', 2)->value('document_id'));
        $this->get($this->base().'/'.$bill.'?revision=1')->assertSee('Original charge')->assertDontSee('Energy charge');
        $this->get($this->base().'/'.$bill)->assertSee('replacement.pdf')->assertSee('Energy charge')->assertDontSee('Original charge');
        $this->get($this->base().'/'.$bill.'/document?revision=2')->assertOk()->assertDownload();
        $this->get($this->base().'/'.$bill.'/document?revision=999')->assertNotFound();
        $this->get($this->base().'/'.$bill.'?revision=999')->assertNotFound();
        $this->post($this->base(), $this->data(['period' => '2026-07-01', 'document' => UploadedFile::fake()->createWithContent('other.pdf', "%PDF-1.4\n% other source\n%%EOF")]))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('statements', 1);
    }

    public function test_stale_corrections_and_stale_review_cannot_overwrite_new_version(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData())->assertSessionHasNoErrors();
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData())->assertSessionHasErrors('version');
        $this->post($this->base().'/'.$bill.'/review', ['version' => 0, 'decision' => 'verify', 'note' => 'Old browser still open here.'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('statement_revisions', 2);
        $this->assertDatabaseCount('customer_review_decisions', 0);
        $this->assertDatabaseCount('statement_line_items', 2);
    }

    public function test_invalid_corrections_and_charge_totals_do_not_change_evidence(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        foreach ([
            ['reason' => 'short'], ['period' => '2026-07-01'], ['utility_account_id' => 9999],
            ['line_items' => [['description' => 'Bad total', 'category' => 'tax', 'amount' => '0.01']]],
            ['line_items' => [['description' => 'Bad category', 'category' => 'arbitrary', 'amount' => '85.00']]],
        ] as $changes) {
            $response = $this->post($this->base().'/'.$bill.'/correct', $this->correctionData($changes));
            isset($changes['utility_account_id']) ? $response->assertNotFound() : $response->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('statement_revisions', 1);
        $this->assertDatabaseCount('statement_line_items', 0);
        $this->assertDatabaseHas('statements', ['charges_cents' => 10001, 'revision_number' => 1]);
        $this->assertCount(1, Storage::disk('bills')->allFiles());
    }

    public function test_version_history_and_corrections_follow_property_and_organization_access(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        $other = DB::table('organizations')->insertGetId(['key' => 'other', 'name' => 'Other']);
        DB::table('organization_memberships')->insert(['organization_id' => $other, 'user_id' => $this->user->id, 'role' => 'reviewer']);
        $this->get('/workspace/'.$other.'/bills/'.$bill.'?revision=1')->assertNotFound();
        $this->post('/workspace/'.$other.'/bills/'.$bill.'/correct', $this->correctionData())->assertNotFound();
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'viewer']);
        $this->get($this->base().'/'.$bill.'?revision=1')->assertOk();
        $this->get($this->base().'/'.$bill.'/correct')->assertNotFound();
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData())->assertNotFound();
        DB::table('location_grants')->delete();
        $this->get($this->base().'/'.$bill.'?revision=1')->assertNotFound();
        $this->get($this->base().'/'.$bill.'/document?revision=1')->assertNotFound();
    }

    public function test_manager_can_read_old_versions_but_cannot_correct_as_customer(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $bill = DB::table('expected_bills')->value('id');
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData())->assertSessionHasNoErrors();
        $manager = User::factory()->create(['email' => 'employee@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->user->id, 'reason' => 'Investigating the correction'])->assertRedirect();
        $this->get('/management/customer-view/bills/'.$bill.'?revision=1')->assertOk()->assertSee('USD 100.01')->assertDontSee('Correct statement');
        $this->get('/management/customer-view/bills/'.$bill)->assertOk()->assertSee('USD 85.00')->assertDontSee('Correct statement');
        $this->get('/management/customer-view/bills/'.$bill.'/document?revision=1')->assertOk();
        $this->post($this->base().'/'.$bill.'/correct', $this->correctionData())->assertNotFound();
        DB::table('location_grants')->delete();
        $this->get('/management/customer-view/bills/'.$bill.'?revision=1')->assertNotFound();
        $this->get('/management/customer-view/bills/'.$bill.'/document?revision=1')->assertNotFound();
    }

    public function test_migration_preserves_existing_statement_and_pdf_as_first_revision(): void
    {
        $this->post($this->base(), $this->data())->assertSessionHasNoErrors();
        $migration = require database_path('migrations/2026_09_30_110000_add_statement_revisions.php');
        $migration->down();
        $migration->up();
        $revision = DB::table('statement_revisions')->first();
        $this->assertEquals(DB::table('bill_documents')->value('id'), $revision->document_id);
        $this->assertSame(10001, json_decode($revision->snapshot, true)['charges_cents']);
        $this->assertNull($revision->actor_id);
        $this->assertDatabaseCount('statements', 1);
        $this->assertCount(1, Storage::disk('bills')->allFiles());
    }
}
