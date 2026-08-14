<?php

namespace Tests\Feature;

use App\Mail\ExchangeQuoteResponseReceived;
use App\Models\Currency;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the secure, no-login page an exchange office lands on from the
 * Telegram notification (see TelegramExchangeNotifier::notify). Same shape
 * as PartnerResponseControllerTest (travel).
 */
class ExchangePartnerResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function organization(array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Test Exchange',
            'slug' => 'test-exchange-'.uniqid(),
            'type' => 'exchange',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '999',
        ], $overrides));
    }

    private function usd(): Currency
    {
        return Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);
    }

    private function exchangeQuoteRequest(array $overrides = []): ExchangeQuoteRequest
    {
        return ExchangeQuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'currency_id' => $this->usd()->id,
            'amount' => 1000,
            'rate_field' => 'buy_rate',
            'expires_at' => now()->addDays(7),
        ], $overrides));
    }

    private function pendingResponse(array $overrides = []): ExchangeQuoteResponse
    {
        return ExchangeQuoteResponse::create(array_merge([
            'exchange_quote_request_id' => $this->exchangeQuoteRequest()->id,
            'organization_id' => $this->organization()->id,
            'response_token' => Str::random(40),
            'status' => ExchangeQuoteResponse::STATUS_PENDING,
            'posted_rate' => '384.5000',
        ], $overrides));
    }

    /** A reply the visitor went on to choose - the state outcomes apply to. */
    private function acceptedResponse(array $overrides = []): ExchangeQuoteResponse
    {
        return $this->pendingResponse(array_merge([
            'status' => ExchangeQuoteResponse::STATUS_ACCEPTED,
            'offer_letter' => 'A',
            'offered_rate' => '386.2000',
            'responded_at' => now(),
            'accepted_at' => now(),
        ], $overrides));
    }

    public function test_a_valid_pending_token_shows_the_offer_form(): void
    {
        $response = $this->pendingResponse();

        $this->get(route('exchange.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('exchange_quotes.respond.heading'))
            ->assertSee('384.50');
    }

    public function test_an_unknown_token_shows_a_friendly_not_found_message(): void
    {
        $this->get(route('exchange.respond', ['locale' => 'en', 'token' => 'does-not-exist']))
            ->assertOk()
            ->assertSee(__('exchange_quotes.respond.not_found_heading'));
    }

    public function test_a_declined_token_shows_the_declined_message(): void
    {
        $response = $this->pendingResponse(['status' => ExchangeQuoteResponse::STATUS_DECLINED]);

        $this->get(route('exchange.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('exchange_quotes.respond.declined_heading'));
    }

    public function test_a_token_for_an_expired_request_shows_the_expired_message(): void
    {
        $exchangeQuoteRequest = $this->exchangeQuoteRequest(['expires_at' => now()->subDay()]);
        $response = $this->pendingResponse(['exchange_quote_request_id' => $exchangeQuoteRequest->id]);

        $this->get(route('exchange.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee(__('exchange_quotes.respond.expired_heading'));
    }

    public function test_submitting_an_offer_at_or_above_the_posted_rate_stores_it_and_emails_the_requester(): void
    {
        Mail::fake();
        $response = $this->pendingResponse();

        $result = $this->post(route('exchange.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'offered_rate' => '386.00',
            'reply_text' => 'Valid until end of day.',
        ]);

        $result->assertRedirect(route('exchange.respond', ['locale' => 'en', 'token' => $response->response_token]));

        $response->refresh();
        $this->assertSame(ExchangeQuoteResponse::STATUS_RESPONDED, $response->status);
        $this->assertSame('386.0000', $response->offered_rate);
        $this->assertNotNull($response->responded_at);
        $this->assertTrue($response->has_improved_rate);

        Mail::assertQueued(ExchangeQuoteResponseReceived::class, function ($mail) use ($response) {
            return $mail->exchangeQuoteResponse->is($response) && $mail->hasTo('guest@example.com');
        });
    }

    public function test_offering_below_the_posted_rate_is_rejected(): void
    {
        $response = $this->pendingResponse();

        $result = $this->post(route('exchange.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'offered_rate' => '380.00',
        ]);

        $result->assertSessionHasErrors('offered_rate');
        $this->assertSame(ExchangeQuoteResponse::STATUS_PENDING, $response->fresh()->status);
    }

    public function test_confirming_the_posted_rate_as_is_is_not_flagged_as_improved(): void
    {
        Mail::fake();
        $response = $this->pendingResponse();

        $this->post(route('exchange.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'offered_rate' => '384.50',
        ]);

        $this->assertFalse($response->fresh()->has_improved_rate);
    }

    public function test_resubmitting_an_already_responded_token_is_a_no_op(): void
    {
        Mail::fake();
        $response = $this->pendingResponse([
            'status' => ExchangeQuoteResponse::STATUS_RESPONDED,
            'offered_rate' => '386.00',
            'responded_at' => now(),
        ]);

        $this->post(route('exchange.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'offered_rate' => '400.00',
        ]);

        $this->assertSame('386.0000', $response->fresh()->offered_rate);
        Mail::assertNothingQueued();
    }

    public function test_exchange_quote_response_submissions_are_rate_limited_per_ip(): void
    {
        $response = $this->pendingResponse();

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('exchange.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
                'offered_rate' => '385.00',
            ]);
            // Only the first actually transitions status - subsequent posts
            // hit the pending-status guard, not the rate limiter, until the
            // limiter itself kicks in. Reset back to pending so every loop
            // iteration actually exercises the throttle middleware.
            $response->update(['status' => ExchangeQuoteResponse::STATUS_PENDING]);
        }

        $this->post(route('exchange.respond.store', ['locale' => 'en', 'token' => $response->response_token]), [
            'offered_rate' => '385.00',
        ])->assertStatus(429);
    }

    /**
     * The only way Findex ever learns whether a request became a real
     * transaction. There is no affiliate link to follow and no payment passing
     * through us - the shop tells us, or nobody does.
     */
    public function test_the_office_can_report_what_happened_at_the_counter(): void
    {
        $response = $this->acceptedResponse();

        $this->post(route('exchange.respond.outcome', ['locale' => 'en', 'token' => $response->response_token]), [
            'outcome' => 'completed',
        ])->assertRedirect();

        $response->refresh();

        $this->assertSame('completed', $response->outcome);
        $this->assertNotNull($response->outcome_at);
        // Status is where the offer got to with us; outcome is what happened in
        // the shop. Reporting one must not overwrite the other.
        $this->assertSame('accepted', $response->status);
    }

    /** Revisable outcomes would make the conversion numbers worthless. */
    public function test_an_outcome_is_recorded_once_and_not_revised(): void
    {
        $response = $this->acceptedResponse();
        $url = route('exchange.respond.outcome', ['locale' => 'en', 'token' => $response->response_token]);

        $this->post($url, ['outcome' => 'completed'])->assertRedirect();
        $recordedAt = $response->refresh()->outcome_at;

        // A second press is not a mistake worth a red banner - it is ignored.
        $this->post($url, ['outcome' => 'no_show'])->assertRedirect();

        $this->assertSame('completed', $response->refresh()->outcome);
        $this->assertEquals($recordedAt, $response->outcome_at);
    }

    /** Nothing happened at a counter for an offer nobody chose. */
    public function test_an_unaccepted_offer_has_no_outcome_to_report(): void
    {
        $response = $this->acceptedResponse();
        $response->forceFill(['status' => 'responded', 'accepted_at' => null])->save();

        $this->post(route('exchange.respond.outcome', ['locale' => 'en', 'token' => $response->response_token]), [
            'outcome' => 'completed',
        ])->assertRedirect();

        $this->assertNull($response->refresh()->outcome);
    }

    public function test_an_invented_outcome_is_rejected(): void
    {
        $response = $this->acceptedResponse();

        $this->post(route('exchange.respond.outcome', ['locale' => 'en', 'token' => $response->response_token]), [
            'outcome' => 'paid_in_gold',
        ])->assertSessionHasErrors('outcome');

        $this->assertNull($response->refresh()->outcome);
    }

    /** The office is shown the code and asked the question, on one page. */
    public function test_the_partner_page_shows_the_code_and_asks_the_question(): void
    {
        $response = $this->acceptedResponse();

        $this->get(route('exchange.respond', ['locale' => 'en', 'token' => $response->response_token]))
            ->assertOk()
            ->assertSee($response->redemption_code)
            ->assertSee('Did the customer come?')
            ->assertSee('Exchange completed');
    }
}
