<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the plain-TTL (no tags) caching added for org-dashboard stats and
 * the tourism price aggregate - unlike RateCacheTest's tag-based
 * invalidation, these only need to prove a second call is served from
 * cache and that per-organization keys don't leak between organizations.
 */
class OrgStatsCacheTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $slug): Organization
    {
        return Organization::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
    }

    private function respondedQuoteResponse(Organization $organization, \DateTimeInterface $createdAt, \DateTimeInterface $respondedAt): QuoteResponse
    {
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
            'responded_at' => $respondedAt,
        ]);
        $response->forceFill(['created_at' => $createdAt])->save();

        return $response;
    }

    public function test_avg_response_time_is_served_from_cache_on_second_call(): void
    {
        $organization = $this->organization('cache-stats-agency');
        $this->respondedQuoteResponse($organization, now()->subHours(3), now()->subHours(1));

        $organization->avgQuoteResponseTimeHours(); // warms the cache

        DB::enableQueryLog();
        $organization->avgQuoteResponseTimeHours();
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'quote_responses'));

        $this->assertCount(0, $queries, 'expected the second call to be served from cache');
    }

    public function test_avg_response_time_is_scoped_per_organization(): void
    {
        $fast = $this->organization('fast-agency');
        $this->respondedQuoteResponse($fast, now()->subHours(2), now()->subHour());

        $slow = $this->organization('slow-agency');
        $this->respondedQuoteResponse($slow, now()->subHours(30), now()->subHours(2));

        $fastAvg = $fast->avgQuoteResponseTimeHours();
        $slowAvg = $slow->avgQuoteResponseTimeHours();

        $this->assertNotEquals($fastAvg, $slowAvg, 'each organization\'s cache key must be independent');
        $this->assertSame(1.0, $fastAvg);
        $this->assertSame(28.0, $slowAvg);
    }

    public function test_typical_price_teaser_is_served_from_cache_on_second_request(): void
    {
        foreach ([500000, 600000, 700000] as $amount) {
            $organization = Organization::create([
                'name' => 'Cache Price Agency '.uniqid(), 'slug' => 'cache-price-agency-'.uniqid(), 'type' => 'tourism',
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
            $response->suggestions()->create(['price_amount' => $amount, 'price_currency' => 'AMD']);
        }

        $this->get(route('tourism.request', ['locale' => 'en'])); // warms tourism.price_data.*

        DB::enableQueryLog();
        $this->get(route('tourism.request', ['locale' => 'en']));
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'quote_suggestions'));

        $this->assertCount(0, $queries, 'expected the second request to be served from cache');
    }
}
