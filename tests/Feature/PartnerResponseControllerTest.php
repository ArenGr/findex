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
        $response = $this->pendingResponse([
            'status' => QuoteResponse::STATUS_RESPONDED,
            'price_amount' => 610,
            'price_currency' => 'USD',
            'responded_at' => now(),
        ]);

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

    public function test_submitting_a_valid_offer_stores_it_and_emails_the_requester(): void
    {
        Mail::fake();
        Storage::fake('public');
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'price_amount' => '610.50',
            'price_currency' => 'USD',
            'offered_hotel_name' => 'Grand Batumi Hotel',
            'flight_details' => 'Direct flight, Yerevan - Batumi',
            'inclusions' => 'Breakfast, airport transfer',
            'reply_text' => 'Happy to adjust dates if needed.',
            'attachment' => UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]));

        $response->refresh();
        $this->assertSame(QuoteResponse::STATUS_RESPONDED, $response->status);
        $this->assertSame('610.50', $response->price_amount);
        $this->assertSame('USD', $response->price_currency);
        $this->assertSame('Grand Batumi Hotel', $response->offered_hotel_name);
        $this->assertNotNull($response->attachment_path);
        Storage::disk('public')->assertExists($response->attachment_path);
        $this->assertNotNull($response->responded_at);

        Mail::assertSent(QuoteResponseReceived::class, fn ($mail) => $mail->quoteResponse->is($response));
    }

    public function test_submitting_without_a_price_is_rejected(): void
    {
        $response = $this->pendingResponse();

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'price_currency' => 'USD',
        ])->assertSessionHasErrors('price_amount');

        $this->assertSame(QuoteResponse::STATUS_PENDING, $response->fresh()->status);
    }

    public function test_submitting_against_an_already_responded_token_is_a_no_op(): void
    {
        Mail::fake();
        $response = $this->pendingResponse([
            'status' => QuoteResponse::STATUS_RESPONDED,
            'price_amount' => 500,
            'price_currency' => 'AMD',
            'responded_at' => now(),
        ]);

        $this->post(route('tourism.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'price_amount' => '999',
            'price_currency' => 'USD',
        ])->assertRedirect(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]));

        $this->assertSame('500.00', $response->fresh()->price_amount);
        Mail::assertNothingSent();
    }
}
