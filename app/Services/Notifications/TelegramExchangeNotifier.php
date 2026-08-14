<?php

namespace App\Services\Notifications;

use App\Models\ExchangeQuoteResponse;
use App\Services\Telegram\TelegramClient;
use Illuminate\Support\Facades\Log;

class TelegramExchangeNotifier implements ExchangeNotifierInterface
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function notify(ExchangeQuoteResponse $response): bool
    {
        $organization = $response->organization;

        if (! $organization->telegram_chat_id) {
            return false;
        }

        $result = $this->telegram->sendMessage(
            $organization->telegram_chat_id,
            $this->buildMessage($response),
            inlineKeyboard: [[
                ['text' => __('exchange_quotes.telegram.view_and_respond_button', [], 'hy'), 'url' => $response->secureRespondUrl()],
                ['text' => __('exchange_quotes.telegram.not_interested_button', [], 'hy'), 'callback_data' => 'exchange_decline:'.$response->id],
            ]]
        );

        if (($result['ok'] ?? null) === false) {
            Log::warning('Exchange quote Telegram notification failed', [
                'exchange_quote_response_id' => $response->id,
                'organization_id' => $organization->id,
                'description' => $result['description'] ?? null,
            ]);

            return false;
        }

        $response->update(['telegram_message_id' => $result['result']['message_id'] ?? null]);

        return true;
    }

    public function notifyAccepted(ExchangeQuoteResponse $response): bool
    {
        $organization = $response->organization;

        if (! $organization->telegram_chat_id) {
            return false;
        }

        $request = $response->exchangeQuoteRequest;

        $result = $this->telegram->sendMessage(
            $organization->telegram_chat_id,
            __('exchange_quotes.telegram.accepted_message', [
                'code' => $response->redemption_code,
                'amount' => number_format((float) $request->amount, 2),
                'currency' => $request->currency->code,
                'rate' => number_format((float) $response->offered_rate, 2),
            ], 'hy'),
            inlineKeyboard: [[
                ['text' => __('exchange_quotes.telegram.view_and_respond_button', [], 'hy'), 'url' => $response->secureRespondUrl()],
            ]]
        );

        if (($result['ok'] ?? null) === false) {
            Log::warning('Exchange quote acceptance notification failed', [
                'exchange_quote_response_id' => $response->id,
                'organization_id' => $organization->id,
                'description' => $result['description'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    public function remind(ExchangeQuoteResponse $response): bool
    {
        $organization = $response->organization;

        if (! $organization->telegram_chat_id) {
            return false;
        }

        $result = $this->telegram->sendMessage(
            $organization->telegram_chat_id,
            __('exchange_quotes.telegram.reminder_message', [], 'hy'),
            inlineKeyboard: [[
                ['text' => __('exchange_quotes.telegram.view_and_respond_button', [], 'hy'), 'url' => $response->secureRespondUrl()],
                ['text' => __('exchange_quotes.telegram.not_interested_button', [], 'hy'), 'callback_data' => 'exchange_decline:'.$response->id],
            ]]
        );

        if (($result['ok'] ?? null) === false) {
            Log::warning('Exchange quote Telegram reminder failed', [
                'exchange_quote_response_id' => $response->id,
                'organization_id' => $organization->id,
                'description' => $result['description'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Written in Armenian regardless of the requester's own site language -
     * this goes to local exchange-office staff, not the customer who filed
     * the request (same reasoning as TelegramPartnerNotifier).
     */
    private function buildMessage(ExchangeQuoteResponse $response): string
    {
        $request = $response->exchangeQuoteRequest;
        $currency = $request->currency;

        return __('exchange_quotes.telegram.request_message', [
            'amount' => number_format((float) $request->amount, 2),
            'currency' => $currency->code,
            'direction' => __('exchange_quotes.telegram.direction_'.$request->rate_field, [], 'hy'),
            'rate' => number_format((float) $response->posted_rate, 2),
        ], 'hy');
    }
}
