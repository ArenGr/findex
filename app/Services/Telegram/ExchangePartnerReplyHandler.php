<?php

namespace App\Services\Telegram;

use App\Models\ExchangeQuoteResponse;

/**
 * Handles only the "Not Interested" inline button on an exchange quote
 * notification (callback_data "exchange_decline:<id>") - a distinct prefix
 * from tourism's "decline:<id>" so the two don't collide on the same
 * numeric id space. The one-time Telegram "connect" deep link
 * (/start <token>) is already handled by PartnerReplyHandler::handleConnect()
 * for any organization type, so this handler doesn't need its own copy of
 * that logic. Actually giving a rate happens on the secure web response
 * page (see ExchangePartnerResponseController), not by typing a reply in
 * Telegram - same reasoning as PartnerReplyHandler.
 */
class ExchangePartnerReplyHandler
{
    public function __construct(private readonly TelegramClient $telegram)
    {
    }

    /**
     * @return bool True if this update belonged to the exchange decline
     *               flow and was fully handled - the caller should not
     *               process it further.
     */
    public function handleUpdate(array $update): bool
    {
        if (!isset($update['callback_query'])) {
            return false;
        }

        $callbackQuery = $update['callback_query'];
        $callbackId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        if (!$callbackId || !str_starts_with($data, 'exchange_decline:')) {
            return false;
        }

        $responseId = (int) substr($data, strlen('exchange_decline:'));
        $response = ExchangeQuoteResponse::query()
            ->where('id', $responseId)
            ->where('status', ExchangeQuoteResponse::STATUS_PENDING)
            ->first();

        if ($response) {
            $response->update(['status' => ExchangeQuoteResponse::STATUS_DECLINED]);
        }

        $this->telegram->answerCallbackQuery($callbackId, __('exchange_quotes.telegram.declined_confirmation', [], 'hy'));

        return true;
    }
}
