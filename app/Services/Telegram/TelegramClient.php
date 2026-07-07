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
     * Send a text message, optionally with a persistent reply keyboard (the
     * button row that sits above the text box - tapping one sends its label
     * as a normal text message, unlike an inline keyboard's silent callback).
     *
     * @param array<int, array<int, string>>|null $keyboard Rows of button labels.
     */
    public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode([
                'keyboard' => array_map(fn ($row) => array_map(fn ($label) => ['text' => $label], $row), $keyboard),
                'resize_keyboard' => true,
            ]);
        }

        return $this->call('sendMessage', $payload);
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
