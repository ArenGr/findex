<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestLinkResent;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the "resend my results link" flow (QuoteRequestController::resend)
 * - a guest's only way back into a lost confirmation email, since they have
 * no account to log back into.
 */
class ResendQuoteLinkTest extends TestCase
{
    use RefreshDatabase;

    private function guestQuoteRequest(array $overrides = []): QuoteRequest
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
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    public function test_resends_the_link_when_an_open_guest_request_matches(): void
    {
        Mail::fake();
        $quoteRequest = $this->guestQuoteRequest();

        $response = $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        $response->assertRedirect(route('tourism.resend', ['locale' => 'en']));
        $response->assertSessionHas('status', 'resend-requested');

        Mail::assertSent(QuoteRequestLinkResent::class, function ($mail) use ($quoteRequest) {
            return $mail->hasTo('guest@example.com')
                && $mail->quoteRequests->count() === 1
                && $mail->quoteRequests->first()->is($quoteRequest);
        });
    }

    public function test_shows_the_same_generic_status_when_no_request_matches(): void
    {
        Mail::fake();

        $response = $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'nobody@example.com',
        ]);

        $response->assertRedirect(route('tourism.resend', ['locale' => 'en']));
        $response->assertSessionHas('status', 'resend-requested');
        Mail::assertNothingSent();
    }

    public function test_expired_requests_are_not_included(): void
    {
        Mail::fake();
        $this->guestQuoteRequest(['expires_at' => now()->subDay()]);

        $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        Mail::assertNothingSent();
    }

    public function test_all_open_requests_for_the_email_are_included_in_one_email(): void
    {
        Mail::fake();
        $first = $this->guestQuoteRequest(['destination_country' => 'GE']);
        $second = $this->guestQuoteRequest(['destination_country' => 'EG']);

        $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        Mail::assertSent(QuoteRequestLinkResent::class, function ($mail) use ($first, $second) {
            $ids = $mail->quoteRequests->pluck('id')->all();

            return in_array($first->id, $ids, true) && in_array($second->id, $ids, true);
        });
    }

    public function test_a_logged_in_users_request_is_not_matched_by_this_guest_flow(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'guest@example.com']);
        $this->guestQuoteRequest(['user_id' => $user->id, 'guest_name' => null, 'guest_email' => null]);

        $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ]);

        Mail::assertNothingSent();
    }

    public function test_honeypot_field_silently_discards_the_submission(): void
    {
        Mail::fake();
        $this->guestQuoteRequest();

        $response = $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
            'company' => 'Acme Corp',
        ]);

        $response->assertRedirect(route('tourism.resend', ['locale' => 'en']));
        Mail::assertNothingSent();
    }

    public function test_resend_requests_are_rate_limited_per_ip(): void
    {
        Mail::fake();
        $this->guestQuoteRequest();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('tourism.resend.send', ['locale' => 'en']), [
                'email' => 'guest@example.com',
            ])->assertStatus(302);
        }

        $this->post(route('tourism.resend.send', ['locale' => 'en']), [
            'email' => 'guest@example.com',
        ])->assertStatus(429);
    }
}
