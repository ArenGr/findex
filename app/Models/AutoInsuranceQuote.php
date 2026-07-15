<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoInsuranceQuote extends Model
{
    public const STATUS_QUOTED = 'quoted';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'auto_insurance_request_id',
        'organization_id',
        'status',
        'premium_amount',
        'premium_currency',
        'policy_term_months',
        'coverage_summary',
        'notes',
        'responded_at',
    ];

    protected $casts = [
        'premium_amount' => 'decimal:2',
        'policy_term_months' => 'integer',
        'responded_at' => 'datetime',
        'interested_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getIsDeclinedAttribute(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function getIsInterestedAttribute(): bool
    {
        return $this->interested_at !== null;
    }

    /**
     * interested_at is deliberately not mass-assignable (see $fillable
     * above) - marking interest goes through this dedicated method instead,
     * matching User::ban()'s reasoning for banned_at.
     */
    public function markInterested(): void
    {
        $this->forceFill(['interested_at' => now()])->save();
    }

    public function autoInsuranceRequest(): BelongsTo
    {
        return $this->belongsTo(AutoInsuranceRequest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
