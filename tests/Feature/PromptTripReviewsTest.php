<?php

namespace Tests\Feature;

use App\Mail\TripReviewPrompt;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PromptTripReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function respondedTrip(array $quoteRequestOverrides = []): QuoteRequest
    {
        $organization = Organization::create([
            'name' => 'Review Prompt Agency', 'slug' => 'review-prompt-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $quoteRequest = QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->subDays(20), 'check_out' => now()->subDays(10),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->subDays(10),
        ], $quoteRequestOverrides));

        QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now()->subDays(18),
        ]);

        return $quoteRequest;
    }

    public function test_prompts_a_review_for_a_completed_responded_trip(): void
    {
        Mail::fake();
        $quoteRequest = $this->respondedTrip();

        Artisan::call('tourism:prompt-reviews');

        Mail::assertSent(TripReviewPrompt::class, fn ($mail) => $mail->hasTo('guest@example.com')
            && $mail->quoteRequest->is($quoteRequest));
        $this->assertNotNull($quoteRequest->fresh()->review_prompted_at);
    }

    public function test_does_not_prompt_for_a_trip_still_in_the_future(): void
    {
        Mail::fake();
        $this->respondedTrip(['check_out' => now()->addDays(5)]);

        Artisan::call('tourism:prompt-reviews');

        Mail::assertNothingSent();
    }

    public function test_does_not_prompt_for_a_trip_with_no_responses(): void
    {
        Mail::fake();
        QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->subDays(20), 'check_out' => now()->subDays(10),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->subDays(10),
        ]);

        Artisan::call('tourism:prompt-reviews');

        Mail::assertNothingSent();
    }

    public function test_does_not_re_prompt_an_already_prompted_trip(): void
    {
        Mail::fake();
        $quoteRequest = $this->respondedTrip();
        $quoteRequest->update(['review_prompted_at' => now()->subDay()]);

        Artisan::call('tourism:prompt-reviews');

        Mail::assertNothingSent();
    }

    public function test_logged_in_users_trip_uses_their_account_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'account-holder@example.com']);
        $quoteRequest = $this->respondedTrip(['user_id' => $user->id, 'guest_name' => null, 'guest_email' => null]);

        Artisan::call('tourism:prompt-reviews');

        Mail::assertSent(TripReviewPrompt::class, fn ($mail) => $mail->hasTo('account-holder@example.com'));
    }
}
