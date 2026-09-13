<?php

namespace App\Services\Telegram;

use App\Models\ExchangeQuoteResponse;

class ExchangePartnerReplyHandler
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /**
     * @return bool True if this update belonged to the exchange decline
     *              flow and was fully handled - the caller should not
     *              process it further.
     */
    public function handleUpdate(array $update): bool
    {
        if (! isset($update['callback_query'])) {
            return false;
        }

        $callbackQuery = $update['callback_query'];
        $callbackId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        if (! $callbackId || ! str_starts_with($data, 'exchange_decline:')) {
            return false;
        }

        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $responseId = (int) substr($data, strlen('exchange_decline:'));

        $response = $chatId
            ? ExchangeQuoteResponse::query()
                ->where('id', $responseId)
                ->where('status', ExchangeQuoteResponse::STATUS_PENDING)
                ->whereHas('organization', fn ($query) => $query->where('telegram_chat_id', (string) $chatId))
                ->first()
            : null;

        if ($response) {
            $response->update(['status' => ExchangeQuoteResponse::STATUS_DECLINED]);
        }

        $this->telegram->answerCallbackQuery($callbackId, __('exchange_quotes.telegram.declined_confirmation', [], 'hy'));

        return true;
    }
}
