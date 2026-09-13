<?php

namespace App\Services\Telegram;

use App\Models\Organization;
use App\Models\QuoteResponse;
use App\Models\User;

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

    private function handleCallbackQuery(array $callbackQuery): bool
    {
        $callbackId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        if (! $callbackId || ! str_starts_with($data, 'decline:')) {
            return false;
        }

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

        $this->telegram->answerCallbackQuery($callbackId, __('tourism.telegram.declined_confirmation', [], 'hy'));

        return true;
    }
}
