<?php

namespace App\Services\Telegram;

use App\Mail\QuoteResponseReceived;
use App\Models\Organization;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Handles the two update shapes that belong to the tourism quote-request
 * flow before the general RatesBotHandler ever sees them: a partner tapping
 * their one-time "connect" deep link (/start <token>), and a partner
 * replying (in-chat, via Telegram's own reply-to feature) to one of our
 * outbound quote-request messages.
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

        $repliedToId = $message['reply_to_message']['message_id'] ?? null;

        if ($repliedToId !== null && $text !== '') {
            return $this->handleReply((int) $repliedToId, $text);
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

    private function handleReply(int $repliedToMessageId, string $text): bool
    {
        $response = QuoteResponse::query()->where('telegram_message_id', $repliedToMessageId)->first();

        if (!$response) {
            return false;
        }

        $response->update([
            'reply_text' => $text,
            'responded_at' => now(),
        ]);

        $response->load(['quoteRequest', 'organization']);
        $requesterEmail = $response->quoteRequest->requester_email;

        if ($requesterEmail) {
            Mail::to($requesterEmail)
                ->locale($response->quoteRequest->locale)
                ->send(new QuoteResponseReceived($response, $response->quoteRequest->signedResultsUrl()));
        }

        return true;
    }
}
