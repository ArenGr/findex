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

        // Scoped to the organization whose chat this callback came from -
        // callback_data is client-supplied, so without this any connected
        // partner could decline a competitor's response by guessing the
        // (sequential) id and knock them out of the customer's results.
        // A callback with no identifiable chat declines nothing.
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

        // Answered either way (and the update is still "handled"): Telegram
        // spins the button until it gets an answer, and a decline we
        // rejected shouldn't fall through to the general rates assistant.

        $this->telegram->answerCallbackQuery($callbackId, __('exchange_quotes.telegram.declined_confirmation', [], 'hy'));

        return true;
    }
}
