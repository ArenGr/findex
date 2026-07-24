<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * The tag every cache entry derived from CurrencyRate/MortgageOffer/
 * Currency/Organization/Branch data is stored under (see RateController,
 * CompareController, CurrencyConverter, RatesBotHandler). Centralized here
 * so the tag name isn't duplicated - and possibly typo'd - across each of
 * those models' booted() hooks.
 */
class RateCache
{
    public const TAG = 'rates';

    public static function invalidate(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
