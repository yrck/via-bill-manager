<?php

namespace Tests\Feature;

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
}
