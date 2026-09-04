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
    /**
     * How many currencies the homepage widget carries.
     *
     * It is a teaser, not the rates page: it shows five banks for one
     * currency at a time and links to /rates for the rest. Carrying all
     * eleven active currencies meant the homepage shipped every currency
     * crossed with every rate type - 53 hidden panels, 165 rows - to display
     * five, which is most of why the document ran to ~460 KB and kept growing
     * under the reader while it streamed in. The ones that do not fit are one
     * click away behind the widget's own "view all" link.
     */
    private const CURRENCY_LIMIT = 3;

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
        //
        // Walked in sort order and stopped at CURRENCY_LIMIT currencies that
        // actually have rates, rather than mapping them all and slicing after:
        // a currency the widget will not show should not be queried either.
        $ratesByCurrency = [];

        foreach ($currencies as $currency) {
            if (count($ratesByCurrency) >= self::CURRENCY_LIMIT) {
                break;
            }

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
                        'spread' => round($rate->getSpread(), 2),
                        'updated' => $rate->scraped_at?->diffForHumans(),
                        'rating' => (float) ($ratingsByOrgId[$rate->organization_id]->reviews_avg_rating ?? 0),
                        'reviews_count' => (int) ($ratingsByOrgId[$rate->organization_id]->reviews_count ?? 0),
                    ])
                    ->values()
                    ->all();

                return [$rateType->value => $rows];
            })->filter(fn ($rows) => count($rows) > 0);

            if ($byType->isEmpty()) {
                continue;
            }

            $ratesByCurrency[$currency->code] = $byType->all();
        }

        $currencyCodes = array_keys($ratesByCurrency);

        $defaultCurrency = collect($currencyCodes)->first(fn ($code) => ! empty($ratesByCurrency[$code]))
            ?? ($currencyCodes[0] ?? null);

        $defaultRateType = $defaultCurrency && array_key_exists(RateType::CASH->value, $ratesByCurrency[$defaultCurrency] ?? [])
            ? RateType::CASH->value
            : ($defaultCurrency ? array_key_first($ratesByCurrency[$defaultCurrency] ?? []) : null);

        // One alert-creation link per currency (not per row/bank/rate-type
        // anymore - see rates-table.blade.php's single header CTA) so
        // switching currency tabs still points the visitor at the right
        // currency without needing a row-specific deep link.
        $alertUrlByCurrency = $currencies
            ->filter(fn ($currency) => array_key_exists($currency->code, $ratesByCurrency))
            ->mapWithKeys(
                fn ($currency) => [$currency->code => route('alerts.index', ['currency_id' => $currency->id]).'#create-alert']
            )->all();

        return [
            'currencies' => $currencyCodes,
            'ratesByCurrency' => $ratesByCurrency,
            'defaultCurrency' => $defaultCurrency,
            'defaultRateType' => $defaultRateType,
            'alertUrlByCurrency' => $alertUrlByCurrency,
        ];
    }
}
