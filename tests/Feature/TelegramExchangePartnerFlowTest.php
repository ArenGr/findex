<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use App\Services\Telegram\ExchangePartnerReplyHandler;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers ExchangePartnerReplyHandler's "Not Interested" callback. The
 * /start <token> connect deep link is already covered by
 * TelegramPartnerFlowTest (PartnerReplyHandler::handleConnect is
 * type-agnostic and shared across both flows - see ExchangePartnerReplyHandler's
 * class doc comment for why this handler doesn't duplicate that logic).
 */
class TelegramExchangePartnerFlowTest extends TestCase
{
    use RefreshDatabase;

    private function pendingResponse(): ExchangeQuoteResponse
    {
        $organization = Organization::create([
            'name' => 'Test Exchange',
            'slug' => 'test-exchange-'.uniqid(),
            'type' => 'exchange',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '999',
        ]);

        $usd = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        $exchangeQuoteRequest = ExchangeQuoteRequest::create([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'hy',
            'currency_id' => $usd->id,
            'amount' => 1000,
            'rate_field' => 'buy_rate',
            'expires_at' => now()->addDays(7),
        ]);

        return ExchangeQuoteResponse::create([
            'exchange_quote_request_id' => $exchangeQuoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => ExchangeQuoteResponse::STATUS_PENDING,
            'posted_rate' => '384.5000',
        ]);
    }

    public function test_not_interested_callback_declines_a_pending_response_and_answers_the_query(): void
    {
        $response = $this->pendingResponse();

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('answerCallbackQuery')->once()->with('cbq-1', \Mockery::type('string'))->andReturn(['ok' => true]);
        });

        $handled = app(ExchangePartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-1', 'data' => 'exchange_decline:'.$response->id],
        ]);

        $this->assertTrue($handled);
        $this->assertSame(ExchangeQuoteResponse::STATUS_DECLINED, $response->fresh()->status);
    }

    public function test_not_interested_callback_does_not_decline_an_already_responded_response(): void
    {
        $response = $this->pendingResponse();
        $response->update(['status' => ExchangeQuoteResponse::STATUS_RESPONDED, 'offered_rate' => '386.0000', 'responded_at' => now()]);

        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('answerCallbackQuery')->once()->andReturn(['ok' => true]);
        });

        app(ExchangePartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-2', 'data' => 'exchange_decline:'.$response->id],
        ]);

        $this->assertSame(ExchangeQuoteResponse::STATUS_RESPONDED, $response->fresh()->status);
    }

    public function test_tourism_decline_prefix_is_left_unhandled_by_the_exchange_handler(): void
    {
        // Confirms the two prefixes ("decline:" vs "exchange_decline:")
        // don't collide - a tourism decline callback must NOT be picked up
        // here.
        $handled = app(ExchangePartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-3', 'data' => 'decline:123'],
        ]);

        $this->assertFalse($handled);
    }

    public function test_callback_query_with_unrecognized_data_is_left_unhandled(): void
    {
        $handled = app(ExchangePartnerReplyHandler::class)->handleUpdate([
            'callback_query' => ['id' => 'cbq-4', 'data' => 'something-else'],
        ]);

        $this->assertFalse($handled);
    }

    public function test_plain_message_is_left_unhandled(): void
    {
        $handled = app(ExchangePartnerReplyHandler::class)->handleUpdate([
            'message' => ['chat' => ['id' => 555], 'text' => 'hello'],
        ]);

        $this->assertFalse($handled);
    }
}
