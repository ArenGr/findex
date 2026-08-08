<?php

namespace App\Models;

use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    /**
     * A currency code has no country of its own to derive a flag from the way
     * QuoteRequestController::worldCountries() does for ISO-3166 countries
     * (that trick needs a 2-letter country code, not a 3-letter currency one) -
     * so this is a small hand-picked map to one representative country/region
     * per currency, the same convention used by most currency-converter apps.
     * EUR gets the real EU flag rather than any single member state's.
     */
    public const FLAGS = [
        'AMD' => '🇦🇲',
        'USD' => '🇺🇸',
        'EUR' => '🇪🇺',
        'GBP' => '🇬🇧',
        'CHF' => '🇨🇭',
        'RUR' => '🇷🇺',
        'GEL' => '🇬🇪',
        'AED' => '🇦🇪',
        'CNY' => '🇨🇳',
        'KZT' => '🇰🇿',
        'CAD' => '🇨🇦',
        'AUD' => '🇦🇺',
    ];

    /** Empty for a currency with no mapped flag, so callers can print it blind. */
    public static function flag(?string $code): string
    {
        return self::FLAGS[$code] ?? '';
    }

    protected static function booted(): void
    {
        static::saved(fn () => RateCache::invalidate());
        static::deleted(fn () => RateCache::invalidate());
    }

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all currency rates for this currency.
     */
    public function rates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class);
    }

    /**
     * Get the latest rates for this currency from all organizations.
     */
    public function latestRates(): HasMany
    {
        return $this->rates()->where('scraped_at', '>=', now()->subHours(24));
    }
}
