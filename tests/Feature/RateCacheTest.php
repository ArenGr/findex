<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Services\Cache\OrgRatingsCache;
use App\Services\Cache\RateCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the Redis-tagged caching added to RateController: that the
 * visitor-agnostic dropdown queries and the paginated listing are actually
 * served from cache on a second hit, and that writes through the real
 * paths (not an explicit flush call) invalidate them via each model's
 * booted() hook - see CurrencyRate/MortgageOffer/Currency/Organization/
 * Branch/Review.
 */
class RateCacheTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $slug = 'cache-test-bank', bool $active = true): Organization
    {
        return Organization::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => $active,
        ]);
    }

    private function currency(string $code = 'USD'): Currency
    {
        return Currency::create(['code' => $code, 'name' => $code, 'symbol' => $code, 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_currency_list_is_served_from_cache_on_second_request(): void
    {
        $this->currency();

        $this->get('/en/rates'); // warms rates.currencies.active

        DB::enableQueryLog();
        $this->get('/en/rates');
        $currencyQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '`currencies`'));

        $this->assertCount(0, $currencyQueries, 'expected the currency list to be served from cache on the second request');
    }

    public function test_currency_cache_is_invalidated_when_a_new_currency_is_created(): void
    {
        $this->currency('USD');

        $this->get('/en/rates'); // warms the cache

        Currency::create(['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => 'GEL', 'sort_order' => 2, 'is_active' => true]);

        $response = $this->get('/en/rates');
        $this->assertTrue($response->original->getData()['currencies']->contains('code', 'GEL'));
    }

    public function test_paginated_listing_reflects_a_new_rate_written_after_the_cache_is_warmed(): void
    {
        $bank = $this->organization();
        $usd = $this->currency();

        $this->get('/en/rates'); // warms rates.listing.*

        // Simulates a scrape/manual entry writing a fresh rate - no
        // explicit cache-flush call here, relies entirely on
        // CurrencyRate::booted()'s static::saved hook.
        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $usd->id, 'rate_type' => 'cash',
            'buy_rate' => 380, 'sell_rate' => 385, 'scraped_at' => now(),
        ]);

        $response = $this->get('/en/rates');
        $this->assertSame(1, $response->original->getData()['rates']->total());
    }

    public function test_paginated_listing_stops_showing_an_organization_deactivated_after_the_cache_is_warmed(): void
    {
        $bank = $this->organization();
        $usd = $this->currency();
        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $usd->id, 'rate_type' => 'cash',
            'buy_rate' => 380, 'sell_rate' => 385, 'scraped_at' => now(),
        ]);

        $this->get('/en/rates'); // warms the cache with the bank visible

        $bank->update(['is_active' => false]);

        $response = $this->get('/en/rates');
        $this->assertSame(0, $response->original->getData()['rates']->total());
    }

    public function test_paginated_listing_reflects_a_new_review_via_the_org_ratings_tag(): void
    {
        $bank = $this->organization();
        $usd = $this->currency();
        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $usd->id, 'rate_type' => 'cash',
            'buy_rate' => 380, 'sell_rate' => 385, 'scraped_at' => now(),
        ]);

        $this->get('/en/rates'); // warms rates.listing.* under both tags

        Review::create([
            'organization_id' => $bank->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 5,
            'comment' => 'Great service',
        ]);

        $response = $this->get('/en/rates');
        $row = $response->original->getData()['rates']->first();
        $this->assertSame(1, $row->organization_reviews_count);
    }

    public function test_rate_cache_tag_flush_clears_only_its_own_entries(): void
    {
        Cache::tags([RateCache::TAG])->put('probe.rates', 'value', 60);
        Cache::tags([OrgRatingsCache::TAG])->put('probe.ratings', 'value', 60);

        RateCache::invalidate();

        $this->assertNull(Cache::tags([RateCache::TAG])->get('probe.rates'));
        $this->assertSame('value', Cache::tags([OrgRatingsCache::TAG])->get('probe.ratings'));
    }
}
