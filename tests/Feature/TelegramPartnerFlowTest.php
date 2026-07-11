<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Telegram\PartnerReplyHandler;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers PartnerReplyHandler: the /start <token> connect deep link (see
 * TourismController::index, which generates the token) and the "Not
 * Interested" inline-button callback. Giving an actual quote happens on the
 * secure web response page (see PartnerResponseController), not by typing a
 * reply in Telegram - see that controller's tests for the response flow.
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

    public function test_plain_message_with_no_start_command_is_left_unhandled(): void
    {
        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'message' => ['chat' => ['id' => 555], 'text' => 'USD'],
        ]);

        $this->assertFalse($handled);
    }

    private function pendingResponse(): QuoteResponse
    {
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

        return QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $this->organization()->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);
    }

    public function test_not_interested_callback_declines_a_pending_response_and_answers_the_query(): void
    {
        $response = $this->pendingResponse();

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('answerCallbackQuery')->once()->with('cbq-1', \Mockery::type('string'))->andReturn(['ok' => true]);
        });

        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-1', 'data' => 'decline:' . $response->id],
        ]);

        $this->assertTrue($handled);
        $this->assertSame(QuoteResponse::STATUS_DECLINED, $response->fresh()->status);
    }

    public function test_not_interested_callback_does_not_decline_an_already_responded_response(): void
    {
        $response = $this->pendingResponse();
        $response->update(['status' => QuoteResponse::STATUS_RESPONDED, 'responded_at' => now()]);

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('answerCallbackQuery')->once()->andReturn(['ok' => true]);
        });

        app(PartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-2', 'data' => 'decline:' . $response->id],
        ]);

        $this->assertSame(QuoteResponse::STATUS_RESPONDED, $response->fresh()->status);
    }

    public function test_callback_query_with_unrecognized_data_is_left_unhandled(): void
    {
        $handled = app(PartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-3', 'data' => 'something-else'],
        ]);

        $this->assertFalse($handled);
    }
}
