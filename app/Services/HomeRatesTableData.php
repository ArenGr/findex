<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Services\Cache\OrgRatingsCache;
use App\Services\Cache\RateCache;
use Illuminate\Support\Facades\Cache;

/**
 * Backs the homepage rates widget (rates-table.blade.php) - extracted out of
 * the view so its query results can be cached as a plain array. Depends on
 * both rate data and Organization::withRatingStats(), so it's tagged with
 * both RateCache::TAG and OrgRatingsCache::TAG.
 */
class HomeRatesTableData
{
    public function build(): array
    {
        return Cache::tags([RateCache::TAG, OrgRatingsCache::TAG])->remember(
            // Row URLs (route('organizations.show', ...), route('alerts.index', ...))
            // are locale-prefixed - a locale-less key would leak one
            // locale's links into another locale's cached render.
            'home.rates_table.'.app()->getLocale(),
            now()->addMinutes(15),
            fn () => $this->compute()
        );
    }

    private function compute(): array
    {
        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Precomputed once (instead of per-row) so displaying a rating badge
        // next to each organization doesn't add an N+1 query per row.
        $ratingsByOrgId = Organization::withRatingStats()->get()->keyBy('id');

        // Per currency, per rate type (cash/non-cash/card/...), ranked cheapest-to-buy
        // first (lowest sell_rate = best for a visitor buying foreign currency with
        // AMD). Rate types with no data for a currency are dropped entirely so the
        // sub-tabs only ever show options that actually have something to display.
        $ratesByCurrency = $currencies->mapWithKeys(function ($currency) use ($ratingsByOrgId) {
            $byType = collect(RateType::cases())->mapWithKeys(function ($rateType) use ($currency, $ratingsByOrgId) {
                $rows = CurrencyRate::query()
                    ->where('currency_id', $currency->id)
                    ->where('rate_type', $rateType)
                    ->whereHas('organization', fn ($query) => $query->active())
                    ->with('organization')
                    ->orderBy('sell_rate')
                    ->limit(5)
                    ->get()
                    ->map(fn ($rate) => [
                        'id' => $rate->organization->id,
                        'name' => $rate->organization->name,
                        'url' => route('organizations.show', $rate->organization),
                        'logo' => $rate->organization->logo,
                        'initial' => mb_strtoupper(mb_substr($rate->organization->name, 0, 1)),
                        'buy_rate' => (float) $rate->buy_rate,
                        'sell_rate' => (float) $rate->sell_rate,
                        'rating' => (float) ($ratingsByOrgId[$rate->organization_id]->reviews_avg_rating ?? 0),
                        'reviews_count' => (int) ($ratingsByOrgId[$rate->organization_id]->reviews_count ?? 0),
                        // Pre-fills the alert-creation form (see alerts/index.blade.php)
                        // so a visitor doesn't have to re-enter what they're already
                        // looking at - defaults to the sell rate, the one most
                        // relevant when buying foreign currency with AMD.
                        'alertUrl' => route('alerts.index', [
                            'currency_id' => $currency->id,
                            'organization_id' => $rate->organization_id,
                            'rate_type' => $rateType->value,
                            'rate_field' => 'sell_rate',
                        ]).'#create-alert',
                    ])
                    ->values()
                    ->all();

                return [$rateType->value => $rows];
            })->filter(fn ($rows) => count($rows) > 0);

            return [$currency->code => $byType->all()];
        })->all();

        $currencyCodes = $currencies->pluck('code')->all();

        $defaultCurrency = collect($currencyCodes)->first(fn ($code) => ! empty($ratesByCurrency[$code]))
            ?? ($currencyCodes[0] ?? null);

        $defaultRateType = $defaultCurrency && array_key_exists(RateType::CASH->value, $ratesByCurrency[$defaultCurrency] ?? [])
            ? RateType::CASH->value
            : ($defaultCurrency ? array_key_first($ratesByCurrency[$defaultCurrency] ?? []) : null);

        return [
            'currencies' => $currencyCodes,
            'ratesByCurrency' => $ratesByCurrency,
            'defaultCurrency' => $defaultCurrency,
            'defaultRateType' => $defaultRateType,
        ];
    }
}
