<?php

namespace App\Services\Telegram;

use GuzzleHttp\Client;

class TelegramClient
{
    private Client $httpClient;

    public function __construct(private readonly string $botToken)
    {
        $this->httpClient = new Client([
            'base_uri' => "https://api.telegram.org/bot{$this->botToken}/",
            'timeout' => 35,
            // Telegram returns a normal JSON body (ok: false, description: ...)
            // for logical failures like messaging a user who hasn't opened a
            // DM with the bot yet - callers need to inspect that, not a thrown
            // exception, so 4xx/5xx responses shouldn't blow up the request.
            'http_errors' => false,
        ]);
    }

    /**
     * Send a text message, optionally with an inline keyboard.
     *
     * @param array<int, array<int, array{text: string, callback_data: string}>>|null $inlineKeyboard
     */
    public function sendMessage(int|string $chatId, string $text, ?array $inlineKeyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        return $this->call('sendMessage', $payload);
    }

    /**
     * Acknowledge a button tap. Telegram shows a loading spinner on the
     * button until this is called, so it must happen even if we have
     * nothing to say (an empty $text just clears the spinner silently).
     *
     * With $showAlert, $text is shown as a popup visible only to the user
     * who tapped - nobody else in the chat sees it. Plain text only (no
     * HTML, no buttons), capped at 200 characters by Telegram.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        if ($showAlert) {
            $payload['show_alert'] = true;
        }

        return $this->call('answerCallbackQuery', $payload);
    }

    /**
     * Long-poll for new updates. Telegram holds the connection open for up
     * to $timeout seconds and returns as soon as an update arrives, so this
     * is cheap to call in a tight loop (no busy-waiting).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeout = 30): array
    {
        $response = $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
        ], requestTimeout: $timeout + 10);

        return $response['result'] ?? [];
    }

    private function call(string $method, array $payload, ?int $requestTimeout = null): array
    {
        $response = $this->httpClient->post($method, [
            'json' => $payload,
            'timeout' => $requestTimeout ?? 35,
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
