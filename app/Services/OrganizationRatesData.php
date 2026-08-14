<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use App\Services\Cache\RateCache;
use Illuminate\Support\Facades\Cache;

/**
 * Every rate one organization publishes, grouped by transaction type, with
 * enough market context to say whether each one is worth walking to.
 *
 * Extracted from the controller so the query results can be cached as a plain
 * array - config/cache.php sets 'serializable_classes' => false, so nothing
 * here may return an Eloquent model or a Carbon instance.
 */
class OrganizationRatesData
{
    public function build(Organization $organization): array
    {
        return Cache::tags([RateCache::TAG])->remember(
            'organization.rates.'.$organization->id.'.'.app()->getLocale(),
            now()->addMinutes(15),
            fn () => $this->compute($organization)
        );
    }

    private function compute(Organization $organization): array
    {
        $rates = $organization->currencyRates()
            ->with('currency')
            ->whereHas('currency', fn ($query) => $query->where('is_active', true))
            ->get();

        if ($rates->isEmpty()) {
            return ['groups' => [], 'updated_at' => null, 'currency_count' => 0];
        }

        $bests = $this->marketBests($rates->pluck('currency_id')->unique()->all());
        $changed = $this->lastChanged($rates->pluck('id')->all());

        // Grouped by transaction type rather than listed flat: cash and card
        // rates for the same currency are different products at different
        // prices, and a single list invites reading one as the other.
        $groups = $rates
            ->sortBy(fn (CurrencyRate $rate) => $rate->currency->sort_order)
            ->groupBy(fn (CurrencyRate $rate) => $rate->rate_type->value)
            ->map(fn ($rows) => $rows->map(function (CurrencyRate $rate) use ($bests, $changed) {
                $key = $rate->currency_id.'-'.$rate->rate_type->value;

                return [
                    'code' => $rate->currency->code,
                    'name' => $rate->currency->name,
                    'buy_rate' => (float) $rate->buy_rate,
                    'sell_rate' => (float) $rate->sell_rate,
                    // Whether this organization holds the best rate in the
                    // country for that side, which is the one fact a visitor
                    // cannot work out from this page alone.
                    'best_buy' => $this->matches((float) $rate->buy_rate, $bests[$key]['buy'] ?? null),
                    'best_sell' => $this->matches((float) $rate->sell_rate, $bests[$key]['sell'] ?? null),
                    'scraped_at' => $rate->scraped_at?->toIso8601String(),
                    'changed_at' => $changed[$rate->id] ?? null,
                ];
            })->values()->all())
            // Enum declaration order - the two everyday types first.
            ->sortBy(fn ($rows, $type) => array_search($type, array_map(
                fn (RateType $case) => $case->value,
                RateType::cases(),
            )))
            ->all();

        return [
            'groups' => $groups,
            'updated_at' => $rates->max('scraped_at')?->toIso8601String(),
            'currency_count' => $rates->pluck('currency_id')->unique()->count(),
        ];
    }

    /**
     * The best rate available anywhere for each currency and transaction type
     * this organization quotes. One grouped query, not one per row.
     *
     * @param  array<int, int>  $currencyIds
     * @return array<string, array{buy: float, sell: float}>
     */
    private function marketBests(array $currencyIds): array
    {
        return CurrencyRate::query()
            ->whereIn('currency_id', $currencyIds)
            ->whereHas('organization', fn ($query) => $query->active())
            ->groupBy('currency_id', 'rate_type')
            ->selectRaw('currency_id, rate_type, MAX(buy_rate) as best_buy, MIN(sell_rate) as best_sell')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->currency_id.'-'.($row->rate_type instanceof RateType ? $row->rate_type->value : $row->rate_type) => [
                    'buy' => (float) $row->best_buy,
                    'sell' => (float) $row->best_sell,
                ],
            ])
            ->all();
    }

    /**
     * When each rate last actually moved. RateScraper only appends history on
     * a change, so the newest snapshot is the last change - which separates an
     * organization that repriced this morning from one that has not moved in a
     * week, where "checked today" says the same thing about both.
     *
     * @param  array<int, int>  $rateIds
     * @return array<int, string>
     */
    private function lastChanged(array $rateIds): array
    {
        return CurrencyRateHistory::query()
            ->whereIn('currency_rate_id', $rateIds)
            ->groupBy('currency_rate_id')
            ->selectRaw('currency_rate_id, MAX(scraped_at) as last_changed')
            ->pluck('last_changed', 'currency_rate_id')
            ->all();
    }

    /** Decimal casts drop trailing zeros, so compare with a tolerance. */
    private function matches(float $value, ?float $target): bool
    {
        return $target !== null && abs($value - $target) < 0.00005;
    }
}
