<?php

namespace App\Services\Telegram;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Services\Cache\RateCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RatesBotHandler
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /**
     * A private 1-on-1 assistant: tap a currency button (or just type its
     * code), get the current best rate back in the same chat. No groups, no
     * inline buttons/popups - just a persistent keyboard and plain replies.
     */
    public function handleUpdate(array $update): void
    {
        if (! isset($update['message']['text'])) {
            return;
        }

        $chatId = $update['message']['chat']['id'];
        $text = trim($update['message']['text']);

        $currency = $this->activeCurrencies()->first(
            fn (object $currency) => strtoupper($currency->code) === strtoupper($text)
        );

        if ($currency) {
            $this->replyWithBestRate($chatId, $currency);

            return;
        }

        $this->sendCurrencyMenu($chatId);
    }

    /**
     * Shared by handleUpdate()'s code lookup and sendCurrencyMenu()'s list -
     * same 'rates' tag as the website's currency dropdowns (RateController),
     * so a scrape or an admin edit invalidates both surfaces together.
     * Returns stdClass rows, not Currency models: config/cache.php's
     * 'serializable_classes' => false means Redis only unserializes plain
     * arrays/scalars, not objects, so the cached value is a plain array
     * and gets rehydrated into lightweight rows here instead.
     */
    private function activeCurrencies(): Collection
    {
        return collect(Cache::tags([RateCache::TAG])->remember(
            'telegram.rates_bot.currencies',
            now()->addMinutes(360),
            fn () => Currency::query()->where('is_active', true)->orderBy('sort_order')->get()->toArray()
        ))->map(fn (array $row) => (object) $row);
    }

    private function sendCurrencyMenu(int|string $chatId): void
    {
        $currencies = $this->activeCurrencies()->pluck('code');

        if ($currencies->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'No currencies are being tracked yet.');

            return;
        }

        $keyboard = $currencies->chunk(3)->map(fn ($row) => $row->values()->all())->values()->all();

        $this->telegram->sendMessage(
            $chatId,
            "Welcome to Findex! 👋\nTap a currency below to see today's best cash rate.\n\n"
                ."Setting up a rate alert on the website? Your chat ID is: <code>{$chatId}</code>",
            $keyboard
        );
    }

    private function replyWithBestRate(int|string $chatId, object $currency): void
    {
        // Cash is the default view on the website's rates table too - the
        // rate type most people mean when they ask "what's the rate today".
        // Reduced to a plain array of just what this message needs (rather
        // than the raw model+relation) since the cache can't store objects
        // - see activeCurrencies()'s docblock.
        $best = Cache::tags([RateCache::TAG])->remember(
            "telegram.rates_bot.best_rate.{$currency->id}",
            now()->addMinutes(360),
            function () use ($currency) {
                $rate = CurrencyRate::query()
                    ->where('currency_id', $currency->id)
                    ->where('rate_type', RateType::CASH)
                    ->whereHas('organization', fn ($query) => $query->active())
                    ->with('organization')
                    ->orderBy('sell_rate')
                    ->first();

                return $rate ? [
                    'organization_name' => $rate->organization->name,
                    'buy_rate' => $rate->buy_rate,
                    'sell_rate' => $rate->sell_rate,
                    'updated_at' => $rate->scraped_at?->format('Y-m-d H:i'),
                ] : null;
            }
        );

        if (! $best) {
            $this->telegram->sendMessage($chatId, "No {$currency->code} cash rate data available yet.");

            return;
        }

        $text = "<b>Best {$currency->code} cash rate right now</b>\n"
            ."🏦 {$best['organization_name']}\n"
            ."Buy: {$best['buy_rate']}  Sell: {$best['sell_rate']}\n"
            .'<i>Updated '.($best['updated_at'] ?? 'unknown').'</i>';

        $this->telegram->sendMessage($chatId, $text);
    }
}
