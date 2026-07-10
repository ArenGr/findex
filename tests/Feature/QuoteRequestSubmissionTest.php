<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestSubmitted;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function tourismPartner(string $countryCode = 'GE'): Organization
    {
        $organization = Organization::create([
            'name' => 'Test Travel Co',
            'slug' => 'test-travel-co',
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '123456',
        ]);

        $organization->tourismDestinations()->create(['country_code' => $countryCode]);

        return $organization;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'destination_country' => 'GE',
            'hotel_name' => 'Test Hotel',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 1,
            'all_inclusive' => '1',
            'insurance' => '1',
            'notes' => 'Aisle seats please.',
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ], $overrides);
    }

    public function test_guest_can_submit_a_quote_request_and_is_emailed_a_signed_results_link(): void
    {
        Mail::fake();
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true, 'result' => ['message_id' => 999]]);
        });
        $this->tourismPartner();

        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload());

        $quoteRequest = QuoteRequest::sole();
        $response->assertRedirect($quoteRequest->signedResultsUrl());
        $this->assertNull($quoteRequest->user_id);
        $this->assertSame('Test Guest', $quoteRequest->guest_name);
        $this->assertSame('GE', $quoteRequest->destination_country);
        $this->assertSame(1, $quoteRequest->responses()->count());

        Mail::assertSent(QuoteRequestSubmitted::class, function ($mail) use ($quoteRequest) {
            return $mail->quoteRequest->is($quoteRequest) && $mail->hasTo('guest@example.com');
        });
    }

    public function test_authenticated_user_is_redirected_straight_to_results_without_a_signature(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);
        });
        $this->tourismPartner();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(
            route('tourism.request.store', ['locale' => 'en']),
            $this->validPayload(['guest_name' => null, 'guest_email' => null])
        );

        $quoteRequest = QuoteRequest::sole();
        $this->assertSame($user->id, $quoteRequest->user_id);
        $response->assertRedirect(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $quoteRequest]));
    }

    public function test_submission_fails_when_no_partner_serves_the_destination(): void
    {
        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload());

        $response->assertSessionHasErrors('destination_country');
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_honeypot_field_silently_discards_the_submission(): void
    {
        $this->tourismPartner();

        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), array_merge(
            $this->validPayload(),
            ['company' => 'Acme Corp']
        ));

        $response->assertRedirect(route('tourism.request', ['locale' => 'en']));
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_guest_submission_without_contact_details_fails_validation(): void
    {
        $this->tourismPartner();

        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload([
            'guest_name' => null,
            'guest_email' => null,
        ]));

        $response->assertSessionHasErrors(['guest_name', 'guest_email']);
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_submission_without_consent_fails_validation(): void
    {
        $this->tourismPartner();

        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload(['consent' => null]));

        $response->assertSessionHasErrors('consent');
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_quote_requests_are_rate_limited_per_ip(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
        });
        $this->tourismPartner();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload([
                'guest_email' => "guest{$i}@example.com",
            ]))->assertStatus(302);
        }

        $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload([
            'guest_email' => 'guest-throttled@example.com',
        ]))->assertStatus(429);

        $this->assertSame(5, QuoteRequest::count());
    }

    public function test_results_page_rejects_access_without_valid_signature_or_ownership(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
        });
        $this->tourismPartner();
        $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload());
        $quoteRequest = QuoteRequest::sole();

        $this->get(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $quoteRequest]))
            ->assertForbidden();
    }

    public function test_results_page_is_reachable_via_its_signed_link(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
        });
        $this->tourismPartner();
        $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload());
        $quoteRequest = QuoteRequest::sole();

        $this->get($quoteRequest->signedResultsUrl())->assertOk();
    }
}
