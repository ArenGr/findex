<?php

namespace App\Services\Viber;

use Illuminate\Support\Facades\Log;

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
