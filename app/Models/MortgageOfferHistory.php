<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortgageOfferHistory extends Model
{
    use Prunable;

    protected $table = 'mortgage_offer_history';

    protected $fillable = [
        'mortgage_offer_id',
        'interest_rate_min',
        'interest_rate_max',
        'scraped_at',
    ];

    protected $casts = [
        'interest_rate_min' => 'decimal:2',
        'interest_rate_max' => 'decimal:2',
        'scraped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the mortgage offer this history belongs to.
     */
    public function mortgageOffer(): BelongsTo
    {
        return $this->belongsTo(MortgageOffer::class);
    }

    /**
     * Scanned by the scheduled `model:prune` command - see
     * config/history.php.
     */
    public function prunable(): Builder
    {
        return static::where('scraped_at', '<=', now()->subMonths(config('history.retention_months')));
    }

    /**
     * Create a history record from a mortgage offer.
     */
    public static function createFromOffer(MortgageOffer $offer): self
    {
        return self::create([
            'mortgage_offer_id' => $offer->id,
            'interest_rate_min' => $offer->interest_rate_min,
            'interest_rate_max' => $offer->interest_rate_max,
            'scraped_at' => $offer->scraped_at ?? now(),
        ]);
    }
}
