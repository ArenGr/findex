<?php

namespace App\Services\Viber;

use Illuminate\Support\Facades\Log;

/**
 * Stand-in for a real Viber Business Messages API client
 * (https://developers.viber.com/docs/api/rest-bot-api/), which needs a
 * registered Viber Public Account and an API key - unlike Telegram, there's
 * no self-service bot creation for Viber, so this can't be wired up for
 * real until those are obtained.
 *
 * Mirrors TelegramClient's sendMessage() shape (an ['ok' => bool] result)
 * so CheckRateAlerts::notify() can treat both channels the same way, and so
 * swapping this out for a real Guzzle-backed client later is a drop-in
 * replacement, not a rewrite of the calling code.
 */
class ViberClient
{
    /**
     * @return array{ok: bool}
     */
    public function sendMessage(string $chatId, string $text): array
    {
        Log::info('Viber send (simulated - no real API client configured yet)', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return ['ok' => true];
    }
}
