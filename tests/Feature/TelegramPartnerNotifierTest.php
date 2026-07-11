<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Notifications\TelegramPartnerNotifier;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the Telegram-specific implementation of PartnerNotifierInterface:
 * message content, the inline "View & Respond" / "Not Interested" keyboard,
 * and how send success/failure map onto the QuoteResponse row. Partner
 * matching/fan-out is covered separately in SendQuoteRequestToPartnersJobTest
 * against the interface, not this concrete class.
 */
class TelegramPartnerNotifierTest extends TestCase
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

    private function response(Organization $organization, QuoteRequest $quoteRequest): QuoteResponse
    {
        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);

        $response->setRelation('organization', $organization);
        $response->setRelation('quoteRequest', $quoteRequest);

        return $response;
    }

    public function test_notify_sends_a_message_with_the_view_and_respond_and_not_interested_buttons(): void
    {
        $organization = $this->organization();
        $response = $this->response($organization, $this->quoteRequest());

        $this->mock(TelegramClient::class, function ($mock) use ($organization, $response) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(
                    $organization->telegram_chat_id,
                    \Mockery::type('string'),
                    null,
                    \Mockery::on(function ($inlineKeyboard) use ($response) {
                        $buttons = $inlineKeyboard[0] ?? [];

                        return ($buttons[0]['url'] ?? null) === $response->secureRespondUrl()
                            && ($buttons[1]['callback_data'] ?? null) === 'decline:' . $response->id;
                    })
                )
                ->andReturn(['ok' => true, 'result' => ['message_id' => 4321]]);
        });

        $result = app(TelegramPartnerNotifier::class)->notify($response);

        $this->assertTrue($result);
        $this->assertSame(4321, $response->fresh()->telegram_message_id);
    }

    public function test_notify_returns_false_when_the_organization_has_no_telegram_chat_id(): void
    {
        $organization = $this->organization(['telegram_chat_id' => null]);
        $response = $this->response($organization, $this->quoteRequest());

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldNotReceive('sendMessage');
        });

        $this->assertFalse(app(TelegramPartnerNotifier::class)->notify($response));
    }

    public function test_notify_returns_false_when_telegram_reports_a_failure(): void
    {
        $organization = $this->organization();
        $response = $this->response($organization, $this->quoteRequest());

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => false, 'description' => 'chat not found']);
        });

        $this->assertFalse(app(TelegramPartnerNotifier::class)->notify($response));
        $this->assertNull($response->fresh()->telegram_message_id);
    }

    public function test_message_is_written_in_armenian_regardless_of_ambient_locale(): void
    {
        app()->setLocale('en');
        $organization = $this->organization();
        $quoteRequest = $this->quoteRequest(['locale' => 'en', 'destination_country' => 'GE']);
        $response = $this->response($organization, $quoteRequest);

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(\Mockery::any(), \Mockery::on(fn ($text) => str_contains($text, 'Ուղղություն')), \Mockery::any(), \Mockery::any())
                ->andReturn(['ok' => true]);
        });

        app(TelegramPartnerNotifier::class)->notify($response);
    }
}
