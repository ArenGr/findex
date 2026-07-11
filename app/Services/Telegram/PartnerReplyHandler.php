<?php

namespace App\Services\Telegram;

use App\Models\Organization;
use App\Models\QuoteResponse;

/**
 * Handles the update shapes that belong to the tourism quote-request flow
 * before the general RatesBotHandler ever sees them: a partner tapping
 * their one-time "connect" deep link (/start <token>), and a partner
 * tapping the "Not Interested" inline button on a quote-request
 * notification. Actually giving a quote happens on the secure web response
 * page (see PartnerResponseController), not by typing a reply in Telegram -
 * Telegram here is purely a notification channel plus a one-tap decline.
 */
class PartnerReplyHandler
{
    public function __construct(private readonly TelegramClient $telegram)
    {
    }

    /**
     * @return bool True if this update belonged to the partner flow and was
     *               fully handled - the caller should not process it further.
     */
    public function handleUpdate(array $update): bool
    {
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        $message = $update['message'] ?? null;

        if (!is_array($message)) {
            return false;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId !== null && str_starts_with($text, '/start ')) {
            $this->handleConnect($chatId, trim(substr($text, 7)));

            return true;
        }

        return false;
    }

    private function handleConnect(int|string $chatId, string $token): void
    {
        $organization = Organization::query()->where('telegram_connect_token', $token)->first();

        if (!$organization) {
            $this->telegram->sendMessage($chatId, __('tourism.telegram.invalid_connect_token', [], 'hy'));

            return;
        }

        $organization->update([
            'telegram_chat_id' => (string) $chatId,
            'telegram_connect_token' => null,
        ]);

        $this->telegram->sendMessage($chatId, __('tourism.telegram.connected_confirmation', [], 'hy'));
    }

    /**
     * "Not Interested" is a one-tap decline: no page visit, no typing -
     * just answer the callback (Telegram shows a loading spinner on the
     * button until we do) and mark the response so it drops off the
     * traveler's "waiting" list instead of hanging forever.
     */
    private function handleCallbackQuery(array $callbackQuery): bool
    {
        $callbackId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        if (!$callbackId || !str_starts_with($data, 'decline:')) {
            return false;
        }

        $responseId = (int) substr($data, strlen('decline:'));
        $response = QuoteResponse::query()
            ->where('id', $responseId)
            ->where('status', QuoteResponse::STATUS_PENDING)
            ->first();

        if ($response) {
            $response->update(['status' => QuoteResponse::STATUS_DECLINED]);
        }

        $this->telegram->answerCallbackQuery($callbackId, __('tourism.telegram.declined_confirmation', [], 'hy'));

        return true;
    }
}
