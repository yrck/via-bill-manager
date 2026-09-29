<?php

namespace Tests\Feature;

use App\Access\BillingAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerLocationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'demo.enabled' => true]);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->organization = DB::table('organizations')->insertGetId(['name' => 'Test Customer', 'key' => 'test', 'owner_user_id' => $this->owner->id]);
        DB::table('organization_memberships')->insert(['organization_id' => $this->organization, 'user_id' => $this->owner->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->actingAs($this->owner);
    }

    private function base(): string
    {
        return '/workspace/'.$this->organization.'/locations';
    }

    private function location(): int
    {
        $this->post($this->base(), ['name' => 'Customer Secret Location', 'organization_id' => 999, 'portfolio_id' => 999])->assertSessionHasNoErrors();

        return DB::table('locations')->value('id');
    }

    private function account(): array
    {
        return ['reference' => '000123-AB', 'supplier' => 'Example Energy', 'commodity' => 'Electricity'];
    }

    public function test_owner_can_create_location_and_utility_account_and_demo_excludes_both(): void
    {
        $this->get($this->base().'/create')->assertOk();
        $id = $this->location();
        $this->assertSame([$id], app(BillingAccess::class)->locations($this->owner, $this->organization)->pluck('locations.id')->all());
        $this->assertDatabaseHas('portfolios', ['organization_id' => $this->organization]);
        $this->get($this->base().'/'.$id)->assertOk()->assertSee('Add a utility account');
        $this->post($this->base().'/'.$id.'/accounts', $this->account() + ['location_id' => 999])->assertRedirect($this->base().'/'.$id)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('utility_accounts', $this->account() + ['location_id' => $id]);
        $this->get($this->base().'/'.$id)->assertSee('000123-AB')->assertSee('Example Energy');
        $this->get('/portfolio')->assertOk()->assertDontSee('Customer Secret Location')->assertDontSee('000123-AB');
        $this->assertDatabaseCount('expected_bills', 0);
    }

    public function test_duplicates_and_invalid_input_do_not_create_partial_records(): void
    {
        $this->post($this->base(), ['name' => '   '])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('portfolios', 0);
        $id = $this->location();
        $this->post($this->base(), ['name' => ' customer secret location '])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('locations', 1);
        $this->assertDatabaseCount('location_grants', 1);
        $this->post($this->base().'/'.$id.'/accounts', $this->account())->assertSessionHasNoErrors();
        $this->post($this->base().'/'.$id.'/accounts', array_replace($this->account(), ['reference' => ' 000123-ab ']))->assertSessionHasErrors('reference');
        $this->post($this->base().'/'.$id.'/accounts', array_replace($this->account(), ['commodity' => 'invalid']))->assertSessionHasErrors('commodity');
        $this->assertDatabaseCount('utility_accounts', 1);
    }

    public function test_teammates_need_explicit_grants_and_cannot_create_records_even_as_reviewers(): void
    {
        $id = $this->location();
        $member = User::factory()->create(['is_active' => true]);
        $membership = DB::table('organization_memberships')->insertGetId(['organization_id' => $this->organization, 'user_id' => $member->id, 'role' => 'reviewer', 'is_active' => true]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($member)->get($this->base().'/'.$id)->assertNotFound();
        $this->get($this->base().'/create')->assertNotFound();
        $this->post($this->base(), ['name' => 'Forbidden'])->assertNotFound();
        DB::table('location_grants')->insert(['organization_membership_id' => $membership, 'location_id' => $id]);
        $this->get($this->base().'/'.$id)->assertOk()->assertDontSee('Add a utility account');
        $this->post($this->base().'/'.$id.'/accounts', $this->account())->assertNotFound();
        DB::table('location_grants')->where('organization_membership_id', $membership)->delete();
        $this->get($this->base().'/'.$id)->assertNotFound();
        $this->assertDatabaseCount('locations', 1);
        $this->assertDatabaseCount('utility_accounts', 0);
    }

    public function test_foreign_location_and_suspended_account_are_inaccessible(): void
    {
        $id = $this->location();
        $foreignPortfolio = DB::table('portfolios')->insertGetId(['key' => 'foreign', 'name' => 'Foreign']);
        $foreignLocation = DB::table('locations')->insertGetId(['portfolio_id' => $foreignPortfolio, 'name' => 'Foreign']);
        $this->get($this->base().'/'.$foreignLocation)->assertNotFound();
        $this->post($this->base().'/'.$foreignLocation.'/accounts', $this->account())->assertNotFound();
        DB::table('organizations')->where('id', $this->organization)->update(['is_active' => false]);
        $this->get($this->base().'/'.$id)->assertNotFound();
        $this->post($this->base(), ['name' => 'Forbidden'])->assertNotFound();
        $this->post($this->base().'/'.$id.'/accounts', $this->account())->assertNotFound();
        $this->assertDatabaseCount('utility_accounts', 0);
    }

    public function test_guests_and_unverified_owners_cannot_create_locations(): void
    {
        $this->post('/logout');
        $this->post($this->base(), ['name' => 'Forbidden'])->assertRedirect('/login');
        $this->owner->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->owner)->post($this->base(), ['name' => 'Forbidden'])->assertRedirect('/verify-email');
        $this->assertDatabaseCount('locations', 0);
    }
}
