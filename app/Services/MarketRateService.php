<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Services\Cache\RateCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The state of the market right now: who leads each side, and what the middle
 * looks like.
 *
 * Extracted so the public API and the embeddable widgets read the same numbers
 * from the same place. A widget that computed "the best USD rate" separately
 * would eventually disagree with the API that sells the same figure, and the
 * first anyone would hear of it is a customer asking which one is wrong.
 */
class MarketRateService
{
    /** @return Collection<int, CurrencyRate> */
    public function currentRates(Currency $currency, RateType $type): Collection
    {
        return CurrencyRate::query()
            ->with(['organization', 'currency'])
            ->where('currency_id', $currency->id)
            ->where('rate_type', $type)
            ->whereHas('organization', fn ($query) => $query->active())
            ->get();
    }

    /**
     * Best from the visitor's side, never the institution's: the highest anyone
     * buys at, the lowest anyone sells at.
     *
     * @return array{highest_buy: ?CurrencyRate, lowest_sell: ?CurrencyRate}
     */
    public function best(Currency $currency, RateType $type): array
    {
        $rates = $this->currentRates($currency, $type);

        return [
            'highest_buy' => $rates->sortByDesc(fn (CurrencyRate $rate) => (float) $rate->buy_rate)->first(),
            'lowest_sell' => $rates->sortBy(fn (CurrencyRate $rate) => (float) $rate->sell_rate)->first(),
        ];
    }

    /**
     * @return array{organizations: int, average_buy: ?float, average_sell: ?float}
     */
    public function average(Currency $currency, RateType $type): array
    {
        $rates = $this->currentRates($currency, $type);

        return [
            'organizations' => $rates->count(),
            'average_buy' => $rates->isEmpty() ? null : round($rates->avg(fn ($rate) => (float) $rate->buy_rate), 4),
            'average_sell' => $rates->isEmpty() ? null : round($rates->avg(fn ($rate) => (float) $rate->sell_rate), 4),
        ];
    }

    /**
     * A widget is embedded on someone else's page and may be hit far more often
     * than our own, so its figures are cached briefly. Tagged, so a scrape
     * flushes it like everything else.
     */
    public function cachedBest(Currency $currency, RateType $type): array
    {
        return Cache::tags([RateCache::TAG])->remember(
            "market.best.{$currency->id}.{$type->value}",
            now()->addMinutes(5),
            function () use ($currency, $type) {
                $best = $this->best($currency, $type);

                // Plain arrays only: config/cache.php forbids objects.
                $side = fn (?CurrencyRate $rate, string $field) => $rate === null ? null : [
                    'rate' => (float) $rate->{$field},
                    'organization' => $rate->organization->name,
                    'slug' => $rate->organization->slug,
                    'scraped_at' => $rate->scraped_at?->toIso8601String(),
                ];

                return [
                    'highest_buy' => $side($best['highest_buy'], 'buy_rate'),
                    'lowest_sell' => $side($best['lowest_sell'], 'sell_rate'),
                ];
            },
        );
    }
}
