<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use App\Services\Cache\RateCache;
use Illuminate\Support\Facades\Cache;

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
     * When each rate last actually moved.
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
