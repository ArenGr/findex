<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRateHistory extends Model
{
    use Prunable;

    protected $table = 'currency_rate_history';

    protected $fillable = [
        'currency_rate_id',
        'buy_rate',
        'sell_rate',
        'scraped_at',
    ];

    protected $casts = [
        'buy_rate' => 'decimal:4',
        'sell_rate' => 'decimal:4',
        'scraped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the currency rate this history belongs to.
     */
    public function currencyRate(): BelongsTo
    {
        return $this->belongsTo(CurrencyRate::class);
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
     * Create a history record from a currency rate.
     */
    public static function createFromRate(CurrencyRate $rate): self
    {
        return self::create([
            'currency_rate_id' => $rate->id,
            'buy_rate' => $rate->buy_rate,
            'sell_rate' => $rate->sell_rate,
            'scraped_at' => $rate->scraped_at ?? now(),
        ]);
    }
}
