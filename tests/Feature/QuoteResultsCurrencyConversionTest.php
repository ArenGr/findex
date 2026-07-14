<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteResultsCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_shows_an_approximate_conversion_for_a_foreign_priced_quote(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $rateOrg = Organization::create([
            'name' => 'Rate Bank', 'slug' => 'rate-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => true,
        ]);
        CurrencyRate::create([
            'organization_id' => $rateOrg->id, 'currency_id' => $usd->id, 'rate_type' => RateType::NON_CASH,
            'buy_rate' => 390, 'sell_rate' => 400, 'scraped_at' => now(),
        ]);

        $partner = Organization::create([
            'name' => 'Currency Test Agency', 'slug' => 'currency-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'hy',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);
        $quoteResponse = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $partner->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);
        $quoteResponse->suggestions()->create([
            'price_amount' => 100,
            'price_currency' => 'USD',
        ]);

        // Viewing in Armenian (default locale) - preferred currency AMD,
        // quote priced in USD, so a converted estimate should show.
        $response = $this->get($quoteRequest->signedResultsUrl());

        $response->assertOk();
        $response->assertSee('39,500');
    }
}
