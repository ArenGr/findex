<?php

namespace Tests\Feature;

use App\Mail\ExchangeQuoteLinkResent;
use App\Models\Currency;
use App\Models\ExchangeQuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the "resend my results link" flow
 * (ExchangeQuoteController::resend) - same shape as ResendQuoteLinkTest
 * (travel).
 */
class ExchangeResendLinkTest extends TestCase
{
    use RefreshDatabase;

    private function guestExchangeQuoteRequest(array $overrides = []): ExchangeQuoteRequest
    {
        $usd = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        return ExchangeQuoteRequest::create(array_merge([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'currency_id' => $usd->id,
            'amount' => 1000,
            'rate_field' => 'buy_rate',
            'expires_at' => now()->addDays(7),
        ], $overrides));
    }

    public function test_resends_the_link_when_an_open_guest_request_matches(): void
    {
        Mail::fake();
        $exchangeQuoteRequest = $this->guestExchangeQuoteRequest();

        $response = $this->post(route('exchange.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        $response->assertRedirect(route('exchange.resend', ['locale' => 'en']));
        $response->assertSessionHas('status', 'resend-requested');

        Mail::assertQueued(ExchangeQuoteLinkResent::class, function ($mail) use ($exchangeQuoteRequest) {
            return $mail->hasTo('guest@example.com')
                && $mail->exchangeQuoteRequests->count() === 1
                && $mail->exchangeQuoteRequests->first()->is($exchangeQuoteRequest);
        });
    }

    public function test_shows_the_same_generic_status_when_no_request_matches(): void
    {
        Mail::fake();

        $response = $this->post(route('exchange.resend.send', ['locale' => 'en']), [
            'email' => 'nobody@example.com',
        ]);

        $response->assertRedirect(route('exchange.resend', ['locale' => 'en']));
        $response->assertSessionHas('status', 'resend-requested');
        Mail::assertNothingQueued();
    }

    public function test_expired_requests_are_not_included(): void
    {
        Mail::fake();
        $this->guestExchangeQuoteRequest(['expires_at' => now()->subDay()]);

        $this->post(route('exchange.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_a_logged_in_users_request_is_not_matched_by_this_guest_flow(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'guest@example.com']);
        $this->guestExchangeQuoteRequest(['user_id' => $user->id, 'guest_name' => null, 'guest_email' => null]);

        $this->post(route('exchange.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_honeypot_field_silently_discards_the_submission(): void
    {
        Mail::fake();
        $this->guestExchangeQuoteRequest();

        $response = $this->post(route('exchange.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
            'company' => 'Acme Corp',
        ]);

        $response->assertRedirect(route('exchange.resend', ['locale' => 'en']));
        Mail::assertNothingQueued();
    }
}
