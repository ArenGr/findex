<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class VisaResponse extends Model
{
    public const CURRENCIES = ['AMD', 'USD', 'EUR'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_DECLINED = 'declined';

    public const MAX_PROCESSING_DAYS = 365;

    protected $fillable = [
        'visa_request_id',
        'organization_id',
        'response_token',
        'status',
        'price_amount',
        'price_currency',
        'processing_days',
        'reply_text',
        'responded_at',
        'valid_until',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'processing_days' => 'integer',
        'responded_at' => 'datetime',
        'viewed_at' => 'datetime',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getHasRepliedAttribute(): bool
    {
        return $this->status === self::STATUS_RESPONDED;
    }

    public function getIsDeclinedAttribute(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function getIsReviewingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->viewed_at !== null;
    }

    // Past the deadline the agency itself set.
    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    // Whether the agency may still submit or revise its answer.
    public function getIsEditableAttribute(): bool
    {
        return $this->status !== self::STATUS_DECLINED && $this->visaRequest->is_open;
    }

    public function markViewed(): void
    {
        if ($this->viewed_at === null) {
            $this->forceFill(['viewed_at' => now()])->save();
        }
    }

    public function secureRespondUrl(): string
    {
        return URL::route('visa.respond', [
            'locale' => $this->visaRequest->locale,
            'token' => $this->response_token,
        ]);
    }

    public function visaRequest(): BelongsTo
    {
        return $this->belongsTo(VisaRequest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
