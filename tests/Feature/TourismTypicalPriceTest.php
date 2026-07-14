<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the "typical price for this trip" teaser on the request form (see
 * QuoteRequestController::typicalPrices) - shown before a visitor commits
 * to filling out the form, built from historical responded suggestions
 * across every organization.
 */
class TourismTypicalPriceTest extends TestCase
{
    use RefreshDatabase;

    private function respondedSuggestion(string $countryCode, float $amount): void
    {
        $organization = Organization::create([
            'name' => 'Typical Price Agency ' . uniqid(), 'slug' => 'typical-price-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);

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
    }

    public function test_shows_a_typical_price_once_enough_orgs_have_responded(): void
    {
        $this->respondedSuggestion('GE', 500000);
        $this->respondedSuggestion('GE', 600000);
        $this->respondedSuggestion('GE', 700000);

        $response = $this->get(route('tourism.request', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('\u0022GE\u0022:600000', false);
    }

    public function test_hides_the_teaser_when_fewer_than_two_orgs_have_responded(): void
    {
        $organization = Organization::create([
            'name' => 'Solo Agency', 'slug' => 'solo-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
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
        $response->suggestions()->create(['price_amount' => 500000, 'price_currency' => 'AMD']);
        $response->suggestions()->create(['price_amount' => 700000, 'price_currency' => 'AMD']);

        $page = $this->get(route('tourism.request', ['locale' => 'en']));

        $page->assertOk();
        $page->assertSee('\u0022GE\u0022:null', false);
    }

    public function test_request_form_loads_with_no_historical_data_at_all(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('tourism.request.heading'));
    }
}
