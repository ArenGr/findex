<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the aggregate, historical price-comparison card on the tourism
 * dashboard (see TourismController::priceBenchmark) - deliberately not a
 * live per-request comparison, to avoid encouraging orgs to anchor to each
 * other's bids on requests still open for replies.
 */
class TourismPriceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithSuggestion(string $name, string $countryCode, float $amount): Organization
    {
        $organization = Organization::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $organization->tourismDestinations()->create(['country_code' => $countryCode]);

        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => $countryCode, 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $response->suggestions()->create(['price_amount' => $amount, 'price_currency' => 'AMD']);

        return $organization;
    }

    public function test_shows_own_and_market_average_once_at_least_two_other_orgs_have_responded(): void
    {
        $mine = $this->orgWithSuggestion('Mine Agency', 'GE', 700000);
        $this->orgWithSuggestion('Rival A', 'GE', 500000);
        $this->orgWithSuggestion('Rival B', 'GE', 600000);
        $user = User::factory()->organization($mine)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.tourism.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('tourism.dashboard.benchmark_heading'));
        $response->assertSee('700,000'); // own average
        $response->assertSee('550,000'); // market average of the other two
        $response->assertSee('27%'); // (700000-550000)/550000
    }

    public function test_market_average_is_hidden_when_fewer_than_two_other_orgs_have_responded(): void
    {
        $mine = $this->orgWithSuggestion('Solo Mine Agency', 'GE', 700000);
        $this->orgWithSuggestion('Only Rival', 'GE', 500000);
        $user = User::factory()->organization($mine)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.tourism.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('tourism.dashboard.benchmark_no_market_data'));
        $response->assertDontSee('500,000');
    }

    public function test_a_destination_with_no_own_suggestions_does_not_appear_in_the_benchmark(): void
    {
        $mine = Organization::create([
            'name' => 'No Suggestions Agency', 'slug' => 'no-suggestions-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $mine->tourismDestinations()->create(['country_code' => 'GE']);
        $this->orgWithSuggestion('Rival A', 'GE', 500000);
        $this->orgWithSuggestion('Rival B', 'GE', 600000);
        $user = User::factory()->organization($mine)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.tourism.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertDontSee(__('tourism.dashboard.benchmark_heading'));
    }
}
