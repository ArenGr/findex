<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers QuoteRequest::closes_in (the "closes in 3 days" countdown) and
 * that it actually renders on the pages a customer sees their trip
 * requests on.
 */
class QuoteRequestCountdownTest extends TestCase
{
    use RefreshDatabase;

    private function quoteRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'all_inclusive' => false,
            'insurance' => false,
            'expires_at' => now()->addDays(5),
        ], $overrides));
    }

    public function test_closes_in_is_present_for_an_open_request(): void
    {
        $quoteRequest = $this->quoteRequest();

        $this->assertNotNull($quoteRequest->closes_in);
        $this->assertTrue($quoteRequest->is_open);
    }

    public function test_closes_in_is_null_for_an_expired_request(): void
    {
        $quoteRequest = $this->quoteRequest(['expires_at' => now()->subDay()]);

        $this->assertNull($quoteRequest->closes_in);
        $this->assertFalse($quoteRequest->is_open);
    }

    public function test_my_trips_page_shows_the_countdown_for_an_open_request(): void
    {
        $user = User::factory()->create();
        $quoteRequest = $this->quoteRequest(['user_id' => $user->id, 'guest_name' => null, 'guest_email' => null]);

        $response = $this->actingAs($user)->get(route('tourism.mine', ['locale' => 'en']));

        $response->assertSee($quoteRequest->closes_in);
    }

    public function test_results_page_shows_the_countdown_for_an_open_request(): void
    {
        $quoteRequest = $this->quoteRequest();

        $response = $this->get($quoteRequest->signedResultsUrl());

        $response->assertSee($quoteRequest->closes_in);
    }
}
