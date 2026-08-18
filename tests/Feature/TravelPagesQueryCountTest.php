<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the pages that show many agencies at once against N+1 queries.
 *
 * The assertions are deliberately "adding more agencies must not add more
 * queries" rather than a fixed budget - a fixed number would fail on any
 * unrelated change and teach everyone to just raise it.
 */
class TravelPagesQueryCountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Real rate data, so CurrencyConverter's cache behaves the way it does
     * in production. Without a Currency row its lookup returns null, and
     * Laravel's Cache::remember treats a cached null as a miss - so the
     * lookup would repeat per offer here and mask the thing this test is
     * actually watching for.
     */
    private function seedRates(): void
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $bank = Organization::create([
            'name' => 'Rate Bank', 'slug' => 'rate-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $usd->id, 'rate_type' => RateType::NON_CASH,
            'buy_rate' => 390, 'sell_rate' => 400, 'scraped_at' => now(),
        ]);
    }

    private function requestWithOffers(User $owner, int $agencies): QuoteRequest
    {
        $quoteRequest = QuoteRequest::create([
            'user_id' => $owner->id,
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        for ($i = 0; $i < $agencies; $i++) {
            $name = 'Query Agency '.Str::random(8);

            $organization = Organization::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'type' => 'tourism',
                'country_code' => 'AM',
                'is_active' => true,
                'telegram_chat_id' => '1',
            ]);

            $response = QuoteResponse::create([
                'quote_request_id' => $quoteRequest->id,
                'organization_id' => $organization->id,
                'response_token' => Str::random(40),
                'status' => QuoteResponse::STATUS_RESPONDED,
                'responded_at' => now(),
                'valid_until' => now()->addDays(3),
            ]);

            $response->suggestions()->create([
                'price_amount' => 400000 + $i,
                'price_currency' => 'AMD',
                'offered_hotel_name' => 'Hotel '.$i,
                'hotel_stars' => 4,
            ]);
        }

        return $quoteRequest;
    }

    private function queriesFor(string $url): int
    {
        // A warm-up request first, then measure the second. Several
        // process-lifetime caches (feature toggles, currency rates) are
        // populated by whichever request happens to run first, and counting
        // that one against a later warm one would report a difference that
        // has nothing to do with how many agencies are on the page.
        $this->get($url)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    public function test_the_offers_page_does_not_query_per_agency(): void
    {
        $this->seedRates();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $small = $this->requestWithOffers($owner, 2);
        $large = $this->requestWithOffers($owner, 8);

        $this->assertSame(
            $this->queriesFor(route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $small->id])),
            $this->queriesFor(route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $large->id])),
        );
    }

    public function test_the_comparison_page_does_not_query_per_agency(): void
    {
        $this->seedRates();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $small = $this->requestWithOffers($owner, 2);
        $large = $this->requestWithOffers($owner, 8);

        $this->assertSame(
            $this->queriesFor(route('tourism.compare', ['locale' => 'en', 'quoteRequest' => $small->id])),
            $this->queriesFor(route('tourism.compare', ['locale' => 'en', 'quoteRequest' => $large->id])),
        );
    }

    public function test_the_requests_list_does_not_query_per_request(): void
    {
        $this->seedRates();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->requestWithOffers($owner, 2);
        $before = $this->queriesFor(route('tourism.mine', ['locale' => 'en']));

        $this->requestWithOffers($owner, 2);
        $this->requestWithOffers($owner, 2);
        $this->requestWithOffers($owner, 2);

        $this->assertSame($before, $this->queriesFor(route('tourism.mine', ['locale' => 'en'])));
    }

    public function test_the_status_page_does_not_query_per_agency(): void
    {
        $this->seedRates();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $small = $this->requestWithOffers($owner, 2);
        $large = $this->requestWithOffers($owner, 8);

        $this->assertSame(
            $this->queriesFor(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $small->id])),
            $this->queriesFor(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $large->id])),
        );
    }
}
