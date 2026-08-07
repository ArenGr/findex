<?php

namespace App\Services\Telegram;

use App\Models\Organization;
use App\Models\QuoteResponse;
use App\Models\User;

/**
 * Handles the update shapes that belong to the tourism quote-request flow
 * before the general RatesBotHandler ever sees them: a partner tapping
 * their one-time "connect" deep link (/start <token>), and a partner
 * tapping the "Not Interested" inline button on a quote-request
 * notification. Actually giving a quote happens on the secure web response
 * page (see PartnerResponseController), not by typing a reply in Telegram -
 * Telegram here is purely a notification channel plus a one-tap decline.
 *
 * The connect-token deep link is shared with rate alerts (see
 * RateAlertController) - a customer connecting Telegram to receive rate
 * alerts taps the exact same /start <token> link shape as a partner
 * organization, just against users.telegram_connect_token instead of
 * organizations.telegram_connect_token.
 */
class PartnerReplyHandler
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /**
     * @return bool True if this update belonged to the partner flow and was
     *              fully handled - the caller should not process it further.
     */
    public function handleUpdate(array $update): bool
    {
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        $message = $update['message'] ?? null;

        if (! is_array($message)) {
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

        if ($organization) {
            $organization->update([
                'telegram_chat_id' => (string) $chatId,
                'telegram_connect_token' => null,
            ]);

            $this->telegram->sendMessage($chatId, __('tourism.telegram.connected_confirmation', [], 'hy'));

            return;
        }

        // Partner orgs are always Armenian businesses, so their confirmation
        // is hardcoded to 'hy' above - a customer connecting for rate alerts
        // could be browsing in any of the site's locales, snapshotted onto
        // their account when the connect link was generated (see
        // RateAlertController), so the reply matches what they were reading.
        $user = User::query()->where('telegram_connect_token', $token)->first();

        if ($user) {
            $user->update([
                'telegram_chat_id' => (string) $chatId,
                'telegram_connect_token' => null,
            ]);

            $this->telegram->sendMessage($chatId, __('alerts.telegram_connect.connected_confirmation', [], $user->locale ?? 'en'));

            return;
        }

        $this->telegram->sendMessage($chatId, __('tourism.telegram.invalid_connect_token', [], 'hy'));
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

        if (! $callbackId || ! str_starts_with($data, 'decline:')) {
            return false;
        }

        // Scoped to the organization whose chat this callback came from -
        // callback_data is client-supplied, so without this any connected
        // partner could decline a competitor's response by guessing the
        // (sequential) id and knock them out of the traveler's results.
        // A callback with no identifiable chat declines nothing.
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $responseId = (int) substr($data, strlen('decline:'));

        $response = $chatId
            ? QuoteResponse::query()
                ->where('id', $responseId)
                ->where('status', QuoteResponse::STATUS_PENDING)
                ->whereHas('organization', fn ($query) => $query->where('telegram_chat_id', (string) $chatId))
                ->first()
            : null;

        if ($response) {
            $response->update(['status' => QuoteResponse::STATUS_DECLINED]);
        }

        // Answered either way (and the update is still "handled"): Telegram
        // spins the button until it gets an answer, and a decline we
        // rejected shouldn't fall through to the general rates assistant.

        $this->telegram->answerCallbackQuery($callbackId, __('tourism.telegram.declined_confirmation', [], 'hy'));

        return true;
    }
}
