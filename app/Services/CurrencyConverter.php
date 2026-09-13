<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Currency;
use App\Services\Cache\RateCache;
use Illuminate\Support\Facades\Cache;

// Approximate cross-currency conversion for display only (e.g.
class CurrencyConverter
{
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

    private function averageRate(string $currencyCode): ?float
    {
        return Cache::tags([RateCache::TAG])->remember(
            "currency_converter.average_rate.{$currencyCode}",
            now()->addMinutes(360),
            function () use ($currencyCode) {
                $currency = Currency::where('code', $currencyCode)->first();

                if (! $currency) {
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
        );
    }
}
