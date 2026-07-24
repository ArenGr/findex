<?php

namespace App\Models;

use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
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
