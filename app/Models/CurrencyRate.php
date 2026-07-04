<?php

namespace App\Models;

use App\Enums\RateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurrencyRate extends Model
{
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
     * Get the historical records for this rate.
     */
    public function history(): HasMany
    {
        return $this->hasMany(CurrencyRateHistory::class);
    }

    /**
     * Calculate the spread between buy and sell rates.
     */
    public function getSpread(): float
    {
        return (float) ($this->sell_rate - $this->buy_rate);
    }

    /**
     * Calculate the spread percentage.
     */
    public function getSpreadPercentage(): float
    {
        if ($this->buy_rate == 0) {
            return 0;
        }

        return round(($this->getSpread() / $this->buy_rate) * 100, 2);
    }
}
