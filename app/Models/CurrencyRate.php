<?php

namespace App\Models;

use App\Enums\RateType;
use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => RateCache::invalidate());
        static::deleted(fn () => RateCache::invalidate());
    }

    protected $fillable = [
        'organization_id',
        'currency_id',
        'rate_type',
        'buy_rate',
        'sell_rate',
        'source_url',
        'scraped_at',
    ];

    protected $casts = [
        'rate_type' => RateType::class,
        'buy_rate' => 'decimal:4',
        'sell_rate' => 'decimal:4',
        'scraped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization that provided this rate.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the currency for this rate.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Calculate the spread between buy and sell rates.
     */
    public function getSpread(): float
    {
        return (float) ($this->sell_rate - $this->buy_rate);
    }
}
