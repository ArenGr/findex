<?php

namespace Tests\Feature;

use App\Mail\QuoteResponseReceived;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Telegram\PartnerReplyHandler;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers PartnerReplyHandler: the /start <token> connect deep link (see
 * TourismController::index, which generates the token) and capturing a
 * partner's in-chat reply against the QuoteResponse it belongs to (see
 * SendQuoteRequestToPartnersJob, which records telegram_message_id).
 */
class TelegramPartnerFlowTest extends TestCase
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
        ], $overrides));
    }

    public function test_start_command_with_valid_token_connects_the_organization(): void
    {
        $organization = $this->organization(['telegram_connect_token' => 'valid-token']);
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);
        });

        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'message' => ['chat' => ['id' => 555], 'text' => '/start valid-token'],
        ]);

        $this->assertTrue($handled);
        $organization->refresh();
        $this->assertSame('555', $organization->telegram_chat_id);
        $this->assertNull($organization->telegram_connect_token);
    }

    public function test_start_command_with_unknown_token_does_not_connect_any_organization(): void
    {
        $organization = $this->organization(['telegram_connect_token' => 'real-token']);
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);
        });

        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'message' => ['chat' => ['id' => 555], 'text' => '/start bogus-token'],
        ]);

        $this->assertTrue($handled);
        $this->assertNull($organization->refresh()->telegram_chat_id);
    }

    public function test_reply_to_a_known_message_records_it_and_notifies_the_requester(): void
    {
        Mail::fake();
        $organization = $this->organization(['telegram_chat_id' => '555']);
        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'hy',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'expires_at' => now()->addDays(14),
        ]);
        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'telegram_message_id' => 424242,
        ]);

        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'message' => [
                'chat' => ['id' => 555],
                'text' => '$850 per person, all-inclusive.',
                'reply_to_message' => ['message_id' => 424242],
            ],
        ]);

        $this->assertTrue($handled);
        $response->refresh();
        $this->assertSame('$850 per person, all-inclusive.', $response->reply_text);
        $this->assertNotNull($response->responded_at);
        $this->assertTrue($response->has_replied);

        Mail::assertSent(QuoteResponseReceived::class, function ($mail) use ($response) {
            return $mail->quoteResponse->is($response) && $mail->hasTo('guest@example.com');
        });
    }

    public function test_reply_to_an_unknown_message_is_not_handled(): void
    {
        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'message' => [
                'chat' => ['id' => 555],
                'text' => 'Some unrelated reply',
                'reply_to_message' => ['message_id' => 999999],
            ],
        ]);

        $this->assertFalse($handled);
        $this->assertSame(0, QuoteResponse::count());
    }

    public function test_plain_message_with_no_start_command_or_reply_is_left_unhandled(): void
    {
        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'message' => ['chat' => ['id' => 555], 'text' => 'USD'],
        ]);

        $this->assertFalse($handled);
    }
}
