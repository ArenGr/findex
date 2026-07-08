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
     * A private 1-on-1 assistant: tap a currency button (or just type its
     * code), get the current best rate back in the same chat. No groups, no
     * inline buttons/popups - just a persistent keyboard and plain replies.
     */
    public function handleUpdate(array $update): void
    {
        if (!isset($update['message']['text'])) {
            return;
        }

        $chatId = $update['message']['chat']['id'];
        $text = trim($update['message']['text']);

        $currency = Currency::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(code) = ?', [strtoupper($text)])
            ->first();

        if ($currency) {
            $this->replyWithBestRate($chatId, $currency);

            return;
        }

        $this->sendCurrencyMenu($chatId);
    }

    private function sendCurrencyMenu(int|string $chatId): void
    {
        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code');

        if ($currencies->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'No currencies are being tracked yet.');

            return;
        }

        $keyboard = $currencies->chunk(3)->map(fn ($row) => $row->values()->all())->values()->all();

        $this->telegram->sendMessage(
            $chatId,
            "Welcome to Findex! 👋\nTap a currency below to see today's best cash rate.\n\n"
                . "Setting up a rate alert on the website? Your chat ID is: <code>{$chatId}</code>",
            $keyboard
        );
    }

    private function replyWithBestRate(int|string $chatId, Currency $currency): void
    {
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
            $this->telegram->sendMessage($chatId, "No {$currency->code} cash rate data available yet.");

            return;
        }

        $updatedAt = $best->scraped_at?->format('Y-m-d H:i') ?? 'unknown';

        $text = "<b>Best {$currency->code} cash rate right now</b>\n"
            . "🏦 {$best->organization->name}\n"
            . "Buy: {$best->buy_rate}  Sell: {$best->sell_rate}\n"
            . "<i>Updated {$updatedAt}</i>";

        $this->telegram->sendMessage($chatId, $text);
    }
}
