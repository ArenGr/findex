<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Currency;

/**
 * Approximate cross-currency conversion for display only (e.g. "≈ 850 USD"
 * next to a quote priced in AMD) - not a financial-grade FX rate. There's
 * no single canonical AMD exchange rate in this app (every bank publishes
 * its own via CurrencyRate), so this averages each active bank's latest
 * rate rather than picking one bank arbitrarily.
 */
class CurrencyConverter
{
    /**
     * No explicit currency-preference setting exists anywhere in the app -
     * this reuses the visitor's site locale as a proxy, the same way
     * Organization::getDescriptionAttribute() already does for content.
     * Approximate by nature (a Russian-reading visitor outside Russia
     * wouldn't get their real local currency).
     */
    public const LOCALE_CURRENCIES = [
        'hy' => 'AMD',
        'en' => 'USD',
        'ru' => 'RUR',
    ];

    public function preferredCurrencyForLocale(string $locale): string
    {
        return self::LOCALE_CURRENCIES[$locale] ?? 'AMD';
    }

    public function convert(float $amount, string $from, string $to): ?float
    {
        if ($from === $to) {
            return $amount;
        }

        $amountInAmd = $from === 'AMD' ? $amount : $this->toAmd($amount, $from);

        if ($amountInAmd === null) {
            return null;
        }

        return $to === 'AMD' ? $amountInAmd : $this->fromAmd($amountInAmd, $to);
    }

    private function toAmd(float $amount, string $currencyCode): ?float
    {
        $rate = $this->averageRate($currencyCode);

        return $rate === null ? null : $amount * $rate;
    }

    private function fromAmd(float $amountInAmd, string $currencyCode): ?float
    {
        $rate = $this->averageRate($currencyCode);

        return $rate === null || $rate == 0.0 ? null : $amountInAmd / $rate;
    }

    /**
     * Mean of (buy+sell)/2 across every bank's latest non-cash rate for
     * this currency (falling back to cash if no non-cash rate was
     * scraped) - non-cash is the more representative "value" rate, cash
     * exchange-booth rates carry a wider spread.
     */
    private function averageRate(string $currencyCode): ?float
    {
        $currency = Currency::where('code', $currencyCode)->first();

        if (!$currency) {
            return null;
        }

        $rates = $currency->latestRates()->where('rate_type', RateType::NON_CASH)->get();

        if ($rates->isEmpty()) {
            $rates = $currency->latestRates()->where('rate_type', RateType::CASH)->get();
        }

        if ($rates->isEmpty()) {
            return null;
        }

        return (float) $rates->avg(fn ($rate) => ((float) $rate->buy_rate + (float) $rate->sell_rate) / 2);
    }
}
