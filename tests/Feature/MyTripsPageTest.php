<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the "My Trips" account page (QuoteRequestController::mine) - the
 * one place a logged-in user can see every quote request they've filed,
 * without which being signed in bought nothing over a guest submission.
 */
class MyTripsPageTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $countryCode = 'GE'): Organization
    {
        $organization = Organization::create([
            'name' => 'Test Travel Co',
            'slug' => 'test-travel-co-' . uniqid(),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '123456',
        ]);

        $organization->tourismDestinations()->create(['country_code' => $countryCode]);

        return $organization;
    }

    private function quoteRequestFor(User $user, array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'user_id' => $user->id,
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tourism.mine', ['locale' => 'en']))
            ->assertRedirect(route('login', ['locale' => 'en']));
    }

    public function test_user_only_sees_their_own_quote_requests(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $partner = $this->partner();

        $mine = $this->quoteRequestFor($user, ['hotel_name' => 'My Hotel']);
        $this->quoteRequestFor($otherUser, ['hotel_name' => 'Someone Elses Hotel']);

        $response = $this->actingAs($user)->get(route('tourism.mine', ['locale' => 'en']));

        $response->assertOk();
        $response->assertViewHas('quoteRequests', function ($quoteRequests) use ($mine) {
            return $quoteRequests->count() === 1 && $quoteRequests->first()->is($mine);
        });
    }

    public function test_reply_progress_counts_are_correct(): void
    {
        $user = User::factory()->create();
        $partnerA = $this->partner();
        $partnerB = $this->partner();

        $quoteRequest = $this->quoteRequestFor($user);
        QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $partnerA->id,
            'reply_text' => 'Our offer...',
            'responded_at' => now(),
        ]);
        QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $partnerB->id,
        ]);

        $response = $this->actingAs($user)->get(route('tourism.mine', ['locale' => 'en']));

        $response->assertViewHas('quoteRequests', function ($quoteRequests) {
            $quoteRequest = $quoteRequests->first();

            return $quoteRequest->responses_count === 2 && $quoteRequest->replied_responses_count === 1;
        });
    }

    public function test_empty_state_shown_when_no_requests_filed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tourism.mine', ['locale' => 'en']));

        $response->assertOk();
        $response->assertViewHas('quoteRequests', fn ($quoteRequests) => $quoteRequests->isEmpty());
    }
}
