<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillExceptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $organization;

    private int $membership;

    private int $location;

    private int $bill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['key' => 'case-test', 'name' => 'Case test', 'owner_user_id' => $this->owner->id]);
        $this->membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
        $portfolio = DB::table('portfolios')->insertGetId(['key' => 'case-test', 'name' => 'Test', 'organization_id' => $this->organization]);
        $this->location = DB::table('locations')->insertGetId(['portfolio_id' => $portfolio, 'name' => 'Fictional facility']);
        DB::table('location_grants')->insert(['location_id' => $this->location, 'organization_membership_id' => $this->membership]);
        $account = DB::table('utility_accounts')->insertGetId(['location_id' => $this->location, 'reference' => 'CASE-001', 'supplier' => 'Fictional Utility', 'commodity' => 'Electricity']);
        $this->bill = DB::table('expected_bills')->insertGetId(['utility_account_id' => $account, 'period' => '2026-09-01', 'expected_by' => '2026-09-10']);
        $this->actingAs($this->owner);
    }

    private function url(): string
    {
        return '/workspace/'.$this->organization.'/bills/'.$this->bill;
    }

    private function data(array $changes = []): array
    {
        return array_replace(['version' => 0, 'source_revision' => 0, 'action' => 'open', 'note' => 'Requested the missing statement from the provider.', 'assignee_id' => $this->owner->id, 'next_action' => 'Follow up with provider', 'due_on' => '2026-09-20'], $changes);
    }

    public function test_missing_statement_workflow_preserves_assignment_notes_and_reopen_history(): void
    {
        $this->post($this->url().'/exception', $this->data())->assertSessionHasNoErrors()->assertRedirect($this->url());
        $this->get($this->url())->assertOk()->assertSee('Follow up with provider')->assertSee('Save investigation');
        $this->post($this->url().'/exception', $this->data(['version' => 1, 'action' => 'update', 'next_action' => 'Call billing department']))->assertSessionHasNoErrors();
        $this->post($this->url().'/exception', $this->data(['version' => 2, 'action' => 'resolve', 'next_action' => null, 'due_on' => null, 'note' => 'Provider confirmed no service occurred this month.']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bill_exceptions', ['status' => 'resolved', 'next_action' => 'Call billing department', 'version' => 3]);
        $this->post($this->url().'/exception', $this->data(['version' => 3, 'action' => 'reopen', 'note' => 'New service evidence requires further investigation.']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bill_exceptions', 1);
        $this->assertDatabaseCount('bill_exception_events', 4);
        $this->assertDatabaseCount('statements', 0);
        $event = DB::table('bill_exception_events')->where('version', 3)->first();
        $this->assertSame('open', json_decode($event->before_state, true)['status']);
        $this->assertSame('resolved', json_decode($event->after_state, true)['status']);
        $this->assertSame($this->owner->id, $event->actor_id);
    }

    public function test_stale_duplicate_invalid_transition_and_changed_source_are_rejected(): void
    {
        $this->post($this->url().'/exception', $this->data())->assertSessionHasNoErrors();
        $this->post($this->url().'/exception', $this->data())->assertSessionHasErrors('exception');
        $this->post($this->url().'/exception', $this->data(['version' => 1, 'action' => 'reopen']))->assertSessionHasErrors('exception');
        $this->statement();
        $this->get($this->url())->assertOk()->assertSee('Source statement changed');
        $this->post($this->url().'/exception', $this->data(['version' => 1, 'action' => 'resolve']))->assertSessionHasErrors('exception');
        $this->post($this->url().'/exception', $this->data(['version' => 1, 'source_revision' => 1, 'action' => 'resolve']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('statements', ['status' => 'review', 'review_version' => 0]);
        $this->assertDatabaseCount('customer_review_decisions', 0);
        DB::table('statements')->update(['revision_number' => 2]);
        $this->get($this->url())->assertSee('Source statement changed');
        $this->assertDatabaseHas('bill_exceptions', ['status' => 'resolved', 'source_revision' => 1]);
        $this->assertDatabaseCount('bill_exception_events', 2);
    }

    private function statement(): void
    {
        DB::table('statements')->insert(['expected_bill_id' => $this->bill, 'charges_cents' => 1000, 'currency' => 'USD', 'received_on' => '2026-09-15', 'due_on' => '2026-09-30', 'status' => 'review', 'title' => 'Fictional statement', 'evidence' => 'Manual intake', 'owner' => 'Unassigned']);
    }

    public function test_required_evidence_and_followup_and_separate_verification(): void
    {
        foreach (['note', 'next_action', 'due_on'] as $field) {
            $this->post($this->url().'/exception', $this->data([$field => null]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('bill_exceptions', 0);
        $this->statement();
        $this->post($this->url().'/exception', $this->data(['source_revision' => 1]))->assertSessionHasNoErrors();
        $this->post($this->url().'/review', ['version' => 0, 'decision' => 'verify', 'note' => 'Confirmed the printed bill values against the source.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bill_exceptions', ['status' => 'open', 'version' => 1]);
        $this->post($this->url().'/exception', $this->data(['source_revision' => 1, 'version' => 1, 'action' => 'resolve']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('statements', ['status' => 'verified', 'review_version' => 1]);
        $this->assertDatabaseCount('customer_review_decisions', 1);
    }

    public function test_assignee_must_be_verified_active_reviewer_with_property_access(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $other->id, 'role' => 'reviewer', 'is_active' => true]);
        $data = $this->data(['assignee_id' => $other->id]);
        $this->post($this->url().'/exception', $data)->assertSessionHasErrors('assignee_id');
        DB::table('location_grants')->insert(['location_id' => $this->location, 'organization_membership_id' => $membership]);
        foreach ([['email_verified_at' => null], ['email_verified_at' => now(), 'is_active' => false]] as $change) {
            DB::table('users')->where('id', $other->id)->update($change);
            $this->post($this->url().'/exception', $data)->assertSessionHasErrors('assignee_id');
        }
        DB::table('users')->where('id', $other->id)->update(['is_active' => true]);
        DB::table('organization_memberships')->where('id', $membership)->update(['role' => 'viewer']);
        $this->post($this->url().'/exception', $data)->assertSessionHasErrors('assignee_id');
        DB::table('organization_memberships')->where('id', $membership)->update(['role' => 'reviewer']);
        $this->post($this->url().'/exception', $data)->assertSessionHasNoErrors();
        DB::table('location_grants')->where('organization_membership_id', $membership)->delete();
        $this->get($this->url())->assertSee('No longer eligible');
        $this->post($this->url().'/exception', $this->data(['version' => 1, 'action' => 'update', 'assignee_id' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bill_exceptions', ['assignee_id' => null]);
    }

    public function test_queue_filters_and_schedule_exclusion_preserve_investigation_access(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        $this->post($this->url().'/exception', $this->data())->assertSessionHasNoErrors();
        $queue = '/workspace/'.$this->organization.'/exceptions';
        $this->get($queue.'?assignment=mine&overdue=1')->assertOk()->assertSee('CASE-001')->assertSee('Overdue');
        $this->get($queue.'?assignment=unassigned')->assertDontSee('CASE-001');
        $this->get($queue.'?state=resolved')->assertDontSee('CASE-001');
        DB::table('expected_bills')->update(['is_excluded' => true]);
        $this->get($queue)->assertSee('CASE-001');
        $this->get($this->url())->assertOk()->assertSee('Not expected')->assertSee('Follow up with provider');
        $this->travelTo(now()->setDate(2026, 9, 20));
        $this->get($queue.'?overdue=1')->assertDontSee('CASE-001');
    }

    public function test_viewers_revoked_grants_suspension_and_foreign_organizations_cannot_write(): void
    {
        $this->post($this->url().'/exception', $this->data())->assertSessionHasNoErrors();
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'viewer']);
        $this->get($this->url())->assertOk()->assertDontSee('Save investigation');
        $this->post($this->url().'/exception', $this->data())->assertNotFound();
        DB::table('organization_memberships')->where('id', $this->membership)->update(['role' => 'reviewer']);
        $foreign = DB::table('organizations')->insertGetId(['key' => 'foreign', 'name' => 'Foreign', 'owner_user_id' => $this->owner->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $foreign, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->post('/workspace/'.$foreign.'/bills/'.$this->bill.'/exception', $this->data())->assertNotFound();
        $this->get('/workspace/'.$foreign.'/exceptions')->assertOk()->assertDontSee('CASE-001');
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->post($this->url().'/exception', $this->data())->assertNotFound();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => true]);
        DB::table('location_grants')->delete();
        $this->get($this->url())->assertNotFound();
        $this->get('/workspace/'.$this->organization.'/exceptions')->assertDontSee('CASE-001');
        $this->post($this->url().'/exception', $this->data())->assertNotFound();
        $this->assertDatabaseCount('bill_exception_events', 1);
    }

    public function test_manager_preview_is_read_only_and_rechecks_property_grants(): void
    {
        $this->post($this->url().'/exception', $this->data())->assertSessionHasNoErrors();
        $manager = User::factory()->create(['email' => 'manager@nu-devco.com', 'is_active' => true, 'is_platform_staff' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($manager)->post('/management/customers/'.$this->organization.'/view', ['user_id' => $this->owner->id, 'reason' => 'Checking the collection investigation'])->assertRedirect();
        $this->get('/management/customer-view/exceptions?assignment=mine')->assertOk()->assertSee('CASE-001');
        $this->get('/management/customer-view/bills/'.$this->bill)->assertOk()->assertSee('Follow up with provider')->assertDontSee('Save investigation');
        $this->post($this->url().'/exception', $this->data())->assertNotFound();
        DB::table('location_grants')->delete();
        $this->get('/management/customer-view/exceptions')->assertOk()->assertDontSee('CASE-001');
        $this->get('/management/customer-view/bills/'.$this->bill)->assertNotFound();
    }
}
