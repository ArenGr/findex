<?php

namespace App\Services\Telegram;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;

class RatesBotHandler
{
    public function __construct(private readonly TelegramClient $telegram)
    {
    }

    /**
     * Dispatch one Telegram update (a message or a button tap) to the right handler.
     */
    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        if (isset($update['message']['text'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function handleMessage(array $message): void
    {
        $userId = $message['from']['id'];
        $isGroup = $message['chat']['type'] !== 'private';

        $sent = $this->telegram->sendMessage(
            $userId,
            "Welcome to Findex! 👋\nTap below to see today's best exchange rates.",
            [[['text' => '💱 Exchange Rates', 'callback_data' => 'menu:rates']]]
        );

        // Telegram won't let a bot DM someone who has never opened a chat
        // with it - that's the only case where anything gets posted to the
        // group at all, and only this one-time prompt, never the menu/rates.
        if (!($sent['ok'] ?? false) && $isGroup) {
            $this->telegram->sendMessage($message['chat']['id'], $this->startBotPrompt());
        }
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';

        if ($data === 'menu:rates') {
            $userId = $callbackQuery['from']['id'];
            $isGroup = $callbackQuery['message']['chat']['type'] !== 'private';

            $sent = $this->sendCurrencyMenu($userId);

            if (!$sent && $isGroup) {
                $this->telegram->answerCallbackQuery($callbackQuery['id'], $this->startBotPrompt(), showAlert: true);

                return;
            }

            // In a group there's otherwise no visible sign that anything
            // happened, since the menu went to a DM instead of the group.
            $this->telegram->answerCallbackQuery($callbackQuery['id'], $isGroup ? 'Check your DMs! 📩' : null);

            return;
        }

        if (str_starts_with($data, 'rate:')) {
            // Answered as a private popup (show_alert) rather than a message
            // posted to the chat - in a group, only the tapping user should
            // see the result, not everyone. The currency menu stays on
            // screen either way, so there's nothing else to navigate back to.
            $this->answerBestRate($callbackQuery['id'], substr($data, strlen('rate:')));

            return;
        }

        $this->telegram->answerCallbackQuery($callbackQuery['id']);
    }

    /**
     * @return bool Whether the menu was actually delivered.
     */
    private function sendCurrencyMenu(int|string $userId): bool
    {
        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code']);

        if ($currencies->isEmpty()) {
            return ($this->telegram->sendMessage($userId, 'No currencies are being tracked yet.')['ok'] ?? false);
        }

        $buttons = $currencies
            ->map(fn ($currency) => ['text' => $currency->code, 'callback_data' => "rate:{$currency->code}"])
            ->chunk(3)
            ->map(fn ($row) => $row->values()->all())
            ->values()
            ->all();

        return ($this->telegram->sendMessage($userId, 'Choose a currency:', $buttons)['ok'] ?? false);
    }

    private function startBotPrompt(): string
    {
        $username = config('services.telegram.bot_username');

        return $username
            ? "Please message me privately first (t.me/{$username}), then try again here."
            : 'Please start a private chat with me first, then try again here.';
    }

    private function answerBestRate(string $callbackQueryId, string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)->where('is_active', true)->first();

        if (!$currency) {
            $this->telegram->answerCallbackQuery($callbackQueryId, "Unknown currency: {$currencyCode}", showAlert: true);

            return;
        }

        // Cash is the default view on the website's rates table too - the
        // rate type most people mean when they ask "what's the rate today".
        $best = CurrencyRate::query()
            ->where('currency_id', $currency->id)
            ->where('rate_type', RateType::CASH)
            ->whereHas('organization', fn ($query) => $query->active())
            ->with('organization')
            ->orderBy('sell_rate')
            ->first();

        if (!$best) {
            $this->telegram->answerCallbackQuery(
                $callbackQueryId,
                "No {$currencyCode} cash rate data available yet.",
                showAlert: true
            );

            return;
        }

        $updatedAt = $best->scraped_at?->format('Y-m-d H:i') ?? 'unknown';

        // Popup alerts are plain text only (no HTML/buttons) and capped at
        // 200 characters by Telegram.
        $text = "Best {$currencyCode} cash rate:\n"
            . "{$best->organization->name}\n"
            . "Buy: {$best->buy_rate}  Sell: {$best->sell_rate}\n"
            . "Updated {$updatedAt}";

        $this->telegram->answerCallbackQuery($callbackQueryId, $text, showAlert: true);
    }
}
