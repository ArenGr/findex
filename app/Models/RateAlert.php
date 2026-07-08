<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateAlert extends Model
{
    protected $fillable = [
        'user_id',
        'currency_id',
        'organization_id',
        'rate_type',
        'rate_field',
        'direction',
        'threshold',
        'channel',
        'telegram_chat_id',
        'is_active',
    ];

    protected $casts = [
        'threshold' => 'decimal:4',
        'is_active' => 'boolean',
        'is_currently_met' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Null means "any active organization" - see the migration's comment.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether the given rate value satisfies this alert's condition.
     */
    public function isMetBy(float $rateValue): bool
    {
        return $this->direction === 'below'
            ? $rateValue <= (float) $this->threshold
            : $rateValue >= (float) $this->threshold;
    }
}
