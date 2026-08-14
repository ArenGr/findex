<?php

namespace App\Models;

use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class CurrencyRateHistory extends Model
{
    use Prunable;

    protected $table = 'currency_rate_history';

    /**
     * The history charts are cached, so a new snapshot has to flush them.
     *
     * In practice a row only ever lands here because RateScraper saw a rate
     * change, and saving that rate invalidates the cache anyway - but the two
     * writes are independent, and a chart that silently ignores the data it was
     * built from is a bad thing to leave load-bearing on that coincidence.
     */
    protected static function booted(): void
    {
        static::saved(fn () => RateCache::invalidate());
        static::deleted(fn () => RateCache::invalidate());
    }

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
