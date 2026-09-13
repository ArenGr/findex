<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Services\Cache\OrgRatingsCache;
use App\Services\Cache\RateCache;
use Illuminate\Support\Facades\Cache;

class HomeRatesTableData
{
    // How many currencies the homepage widget carries.
    private const CURRENCY_LIMIT = 3;

    public function build(): array
    {
        return Cache::tags([RateCache::TAG, OrgRatingsCache::TAG])->remember(
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

        $ratingsByOrgId = Organization::withRatingStats()->get()->keyBy('id');

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
