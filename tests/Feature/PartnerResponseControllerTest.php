<?php

namespace Tests\Feature;

use App\Mail\QuoteResponseReceived;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the secure, no-login page a partner lands on from the Telegram
 * notification (see TelegramPartnerNotifier::notify). The response_token
 * is the only credential - there is no login, matching the MVP requirement
 * that partners only ever interact when they receive a notification.
 */
class PartnerResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function organization(array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Test Travel Co',
            'slug' => 'test-travel-co-' . uniqid(),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '999',
        ], $overrides));
    }

    private function quoteRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'destination_country' => 'GE',
            'hotel_name' => 'Test Hotel',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'all_inclusive' => false,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    private function pendingResponse(array $overrides = []): QuoteResponse
    {
        return QuoteResponse::create(array_merge([
            'quote_request_id' => $this->quoteRequest()->id,
            'organization_id' => $this->organization()->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ], $overrides));
    }

    private function respondedResponse(array $suggestionOverrides = []): QuoteResponse
    {
        $response = $this->pendingResponse([
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);
        $response->suggestions()->create(array_merge([
            'price_amount' => 500,
            'price_currency' => 'AMD',
        ], $suggestionOverrides));

        return $response;
    }

    public function test_a_valid_pending_token_shows_the_offer_form(): void
    {
        $response = $this->pendingResponse();

        $this->get(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('tourism.respond.heading'));
    }

    public function test_an_unknown_token_shows_a_friendly_not_found_message(): void
    {
        $this->get(route('tourism.respond', ['locale' => 'en', 'token' => 'does-not-exist']))
            ->assertOk()
            ->assertSee(__('tourism.respond.not_found_heading'));
    }

    public function test_an_already_responded_token_shows_the_submitted_offer_instead_of_the_form(): void
    {
        $response = $this->respondedResponse(['price_amount' => 610, 'price_currency' => 'USD']);

        $this->get(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('tourism.respond.success_heading'))
            ->assertDontSee(__('tourism.respond.submit_button'));
    }

    public function test_a_declined_token_shows_the_declined_message(): void
    {
        $response = $this->pendingResponse(['status' => QuoteResponse::STATUS_DECLINED]);

        $this->get(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('tourism.respond.declined_heading'));
    }

    public function test_a_token_for_an_expired_request_shows_the_expired_message(): void
    {
        $quoteRequest = $this->quoteRequest(['expires_at' => now()->subDay()]);
        $response = $this->pendingResponse(['quote_request_id' => $quoteRequest->id]);

        $this->get(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('tourism.respond.expired_heading'));
    }

    public function test_submitting_a_single_suggestion_stores_it_and_emails_the_requester(): void
    {
        Mail::fake();
        Storage::fake('public');
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'reply_text' => 'Happy to adjust dates if needed.',
            'suggestions' => [
                [
                    'price_amount' => '610.50',
                    'price_currency' => 'USD',
                    'offered_hotel_name' => 'Grand Batumi Hotel',
                    'flight_details' => 'Direct flight, Yerevan - Batumi',
                    'inclusions' => 'Breakfast, airport transfer',
                    'attachment' => UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf'),
                ],
            ],
        ])->assertRedirect(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]));

        $response->refresh();
        $this->assertSame(QuoteResponse::STATUS_RESPONDED, $response->status);
        $this->assertSame('Happy to adjust dates if needed.', $response->reply_text);
        $this->assertNotNull($response->responded_at);

        $this->assertCount(1, $response->suggestions);
        $suggestion = $response->suggestions->first();
        $this->assertSame('610.50', $suggestion->price_amount);
        $this->assertSame('USD', $suggestion->price_currency);
        $this->assertSame('Grand Batumi Hotel', $suggestion->offered_hotel_name);
        $this->assertNotNull($suggestion->attachment_path);
        Storage::disk('public')->assertExists($suggestion->attachment_path);

        Mail::assertSent(QuoteResponseReceived::class, fn ($mail) => $mail->quoteResponse->is($response));
    }

    public function test_submitting_a_suggestion_with_a_promo_code_stores_it(): void
    {
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'suggestions' => [
                ['price_amount' => '400', 'price_currency' => 'USD', 'promo_code' => 'SUMMER10', 'promo_note' => '10% off in person'],
            ],
        ])->assertRedirect();

        $suggestion = $response->fresh()->suggestions->first();
        $this->assertSame('SUMMER10', $suggestion->promo_code);
        $this->assertSame('10% off in person', $suggestion->promo_note);
        $this->assertFalse($suggestion->is_claimed);
    }

    public function test_submitting_multiple_suggestions_stores_all_of_them(): void
    {
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'suggestions' => [
                ['price_amount' => '400', 'price_currency' => 'USD', 'offered_hotel_name' => 'Budget Hotel'],
                ['price_amount' => '700', 'price_currency' => 'USD', 'offered_hotel_name' => 'Premium Resort'],
                ['price_amount' => '550', 'price_currency' => 'EUR', 'offered_hotel_name' => 'Mid-range Hotel'],
            ],
        ])->assertRedirect();

        $response->refresh();
        $this->assertSame(QuoteResponse::STATUS_RESPONDED, $response->status);
        $this->assertCount(3, $response->suggestions);
        $this->assertEqualsCanonicalizing(
            ['Budget Hotel', 'Premium Resort', 'Mid-range Hotel'],
            $response->suggestions->pluck('offered_hotel_name')->all()
        );
    }

    public function test_submitting_more_than_the_max_suggestions_is_rejected(): void
    {
        $response = $this->pendingResponse();

        $suggestions = array_fill(0, QuoteResponse::MAX_SUGGESTIONS + 1, [
            'price_amount' => '100', 'price_currency' => 'USD',
        ]);

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'suggestions' => $suggestions,
        ])->assertSessionHasErrors('suggestions');

        $this->assertSame(QuoteResponse::STATUS_PENDING, $response->fresh()->status);
    }

    public function test_submitting_without_any_suggestions_is_rejected(): void
    {
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'reply_text' => 'No suggestions attached.',
        ])->assertSessionHasErrors('suggestions');

        $this->assertSame(QuoteResponse::STATUS_PENDING, $response->fresh()->status);
    }

    public function test_submitting_a_suggestion_without_a_price_is_rejected(): void
    {
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'suggestions' => [
                ['price_currency' => 'USD'],
            ],
        ])->assertSessionHasErrors('suggestions.0.price_amount');

        $this->assertSame(QuoteResponse::STATUS_PENDING, $response->fresh()->status);
    }

    public function test_submitting_against_an_already_responded_token_is_a_no_op(): void
    {
        Mail::fake();
        $response = $this->respondedResponse(['price_amount' => 500, 'price_currency' => 'AMD']);

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'suggestions' => [
                ['price_amount' => '999', 'price_currency' => 'USD'],
            ],
        ])->assertRedirect(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]));

        $this->assertCount(1, $response->fresh()->suggestions);
        $this->assertSame('500.00', $response->fresh()->suggestions->first()->price_amount);
        Mail::assertNothingSent();
    }
}
