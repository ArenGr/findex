<?php

namespace App\Services\Telegram;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TelegramClient
{
    private Client $httpClient;

    public function __construct(private readonly string $botToken)
    {
        $this->httpClient = new Client([
            'base_uri' => "https://api.telegram.org/bot{$this->botToken}/",
            'timeout' => 35,
            'http_errors' => false,
        ]);
    }

    /**
     * @param  array<int, array<int, string>>|null  $keyboard  Rows of button labels.
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard  Rows of inline buttons.
     */
    public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null, ?array $inlineKeyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        } elseif ($keyboard !== null) {
            $payload['reply_markup'] = json_encode([
                'keyboard' => array_map(fn ($row) => array_map(fn ($label) => ['text' => $label], $row), $keyboard),
                'resize_keyboard' => true,
            ]);
        }

        return $this->call('sendMessage', $payload);
    }

    // Acknowledge an inline-button tap (a "callback query").
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->call('answerCallbackQuery', $payload);
    }

    /**
     * Long-poll for new updates.
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

    // Register the URL Telegram should POST updates to.
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = ['url' => $url];

        if ($secretToken !== null) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->call('setWebhook', $payload);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', []);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', []);
    }

    private function call(string $method, array $payload, ?int $requestTimeout = null): array
    {
        $response = $this->httpClient->post($method, [
            'json' => $payload,
            'timeout' => $requestTimeout ?? 35,
        ]);

        $decoded = json_decode((string) $response->getBody(), true) ?? [];

        if (($decoded['ok'] ?? null) === false) {
            Log::warning("Telegram API call failed: {$method}", [
                'chat_id' => $payload['chat_id'] ?? null,
                'description' => $decoded['description'] ?? null,
                'error_code' => $decoded['error_code'] ?? null,
            ]);
        }

        return $decoded;
    }
}
