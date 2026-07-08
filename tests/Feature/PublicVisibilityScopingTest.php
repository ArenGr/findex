<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a gap where unapproved/suspended (is_active =
 * false) organizations' data still reached the public site, defeating the
 * admin-approval workflow end to end.
 */
class PublicVisibilityScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_rates_page_excludes_inactive_organizations(): void
    {
        $active = Organization::create([
            'name' => 'Active Bank', 'slug' => 'active-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $inactive = Organization::create([
            'name' => 'Inactive Bank', 'slug' => 'inactive-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => false,
        ]);
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        CurrencyRate::create([
            'organization_id' => $active->id, 'currency_id' => $usd->id, 'rate_type' => 'cash',
            'buy_rate' => 380, 'sell_rate' => 385, 'scraped_at' => now(),
        ]);
        CurrencyRate::create([
            'organization_id' => $inactive->id, 'currency_id' => $usd->id, 'rate_type' => 'cash',
            'buy_rate' => 100, 'sell_rate' => 105, 'scraped_at' => now(),
        ]);

        $response = $this->get('/rates');

        $organizationIds = $response->original->getData()['rates']->pluck('organization_id')->all();
        $this->assertContains($active->id, $organizationIds);
        $this->assertNotContains($inactive->id, $organizationIds);
    }

    public function test_cannot_submit_a_review_for_an_inactive_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Pending Bank', 'slug' => 'pending-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => false,
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post("/en/organizations/{$organization->slug}/reviews", [
            'rating' => 5,
            'comment' => 'This organization is not even approved yet.',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('reviews', 0);
    }
}
