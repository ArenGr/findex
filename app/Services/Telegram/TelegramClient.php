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
     * as a normal text message) or an inline keyboard (buttons attached to
     * the message itself - a url button opens a link, a callback_data
     * button silently notifies our webhook instead of sending any text).
     * Telegram allows only one or the other per message.
     *
     * @param array<int, array<int, string>>|null $keyboard Rows of button labels.
     * @param array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null $inlineKeyboard Rows of inline buttons.
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

    /**
     * Acknowledge an inline-button tap (a "callback query"). Telegram shows
     * a loading spinner on the button until this is called, regardless of
     * whether the tap needs any visible response - $text, if given, pops up
     * as a small toast for the user.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->call('answerCallbackQuery', $payload);
    }

    /**
     * Long-poll for new updates. Telegram holds the connection open for up
     * to $timeout seconds and returns as soon as an update arrives, so this
     * is cheap to call in a tight loop (no busy-waiting).
     *
     * Only usable when no webhook is registered - Telegram delivers updates
     * via one channel or the other, never both. Intended for local
     * development (see the telegram:poll command); production registers a
     * webhook instead so nothing needs a supervised long-running process.
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

    /**
     * Register the URL Telegram should POST updates to. $secretToken, if
     * given, comes back on every webhook request as the
     * X-Telegram-Bot-Api-Secret-Token header, so the receiving route can
     * verify the request actually came from Telegram.
     */
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

        // Telegram signals logical failures (bad chat id, user never opened
        // a DM with the bot, etc.) via ok:false in an otherwise-normal JSON
        // body rather than an HTTP error status - without this, callers
        // that don't check `ok` themselves (e.g. CheckRateAlerts::notify)
        // would silently treat a failed send as delivered.
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
