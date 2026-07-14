<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationBadgesTest extends TestCase
{
    use RefreshDatabase;

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Badge Test Bank', 'slug' => 'badge-test-bank-' . uniqid(), 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
    }

    public function test_org_with_enough_high_ratings_is_top_rated(): void
    {
        $organization = $this->organization();
        foreach (range(1, 3) as $i) {
            Review::create([
                'organization_id' => $organization->id,
                'user_id' => User::factory()->create()->id,
                'rating' => 5,
                'comment' => 'Great service ' . $i,
            ]);
        }

        $this->assertTrue($organization->isTopRated());
    }

    public function test_org_with_too_few_reviews_is_not_top_rated_even_with_perfect_rating(): void
    {
        $organization = $this->organization();
        Review::create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 5,
            'comment' => 'Great!',
        ]);

        $this->assertFalse($organization->isTopRated());
    }

    public function test_org_with_low_average_rating_is_not_top_rated(): void
    {
        $organization = $this->organization();
        foreach (range(1, 3) as $i) {
            Review::create([
                'organization_id' => $organization->id,
                'user_id' => User::factory()->create()->id,
                'rating' => 3,
                'comment' => 'Okay ' . $i,
            ]);
        }

        $this->assertFalse($organization->isTopRated());
    }

    public function test_org_with_enough_fast_responses_is_a_fast_responder(): void
    {
        $organization = $this->organization();
        foreach (range(1, 3) as $i) {
            $this->respondedQuoteResponse($organization, now()->subHours(3), now()->subHours(1));
        }

        $this->assertTrue($organization->isFastResponder());
    }

    public function test_org_with_slow_responses_is_not_a_fast_responder(): void
    {
        $organization = $this->organization();
        foreach (range(1, 3) as $i) {
            $this->respondedQuoteResponse($organization, now()->subHours(30), now()->subHours(2));
        }

        $this->assertFalse($organization->isFastResponder());
    }

    public function test_org_with_too_few_responses_is_not_a_fast_responder(): void
    {
        $organization = $this->organization();
        $this->respondedQuoteResponse($organization, now()->subHours(2), now()->subHour());

        $this->assertFalse($organization->isFastResponder());
    }

    public function test_public_profile_page_shows_badges(): void
    {
        $organization = $this->organization();
        foreach (range(1, 3) as $i) {
            Review::create([
                'organization_id' => $organization->id,
                'user_id' => User::factory()->create()->id,
                'rating' => 5,
                'comment' => 'Great service ' . $i,
            ]);
        }

        $this->get(route('organizations.show', ['locale' => 'en', 'organization' => $organization]))
            ->assertSee(__('organizations.badge_top_rated'));
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
        // created_at isn't fillable on QuoteResponse (only touched through
        // normal Eloquent timestamps in real usage) - forceFill it after
        // creation to control the response-time gap for this test.
        $response->forceFill(['created_at' => $createdAt])->save();

        return $response;
    }
}
