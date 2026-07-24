<?php

namespace App\Models;

use App\Enums\MortgageRateType;
use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MortgageOffer extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => RateCache::invalidate());
        static::deleted(fn () => RateCache::invalidate());
    }

    protected $fillable = [
        'organization_id',
        'currency',
        'rate_type',
        'category',
        'interest_rate_min',
        'interest_rate_max',
        'term_min_months',
        'term_max_months',
        'min_down_payment_percent',
        'min_amount',
        'max_amount',
        'source_url',
        'scraped_at',
    ];

    protected $casts = [
        'rate_type' => MortgageRateType::class,
        'interest_rate_min' => 'decimal:2',
        'interest_rate_max' => 'decimal:2',
        'min_down_payment_percent' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'scraped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization that provided this mortgage offer.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the historical records for this offer.
     */
    public function history(): HasMany
    {
        return $this->hasMany(MortgageOfferHistory::class);
    }
}
