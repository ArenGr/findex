<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class QuoteResponse extends Model
{
    public const CURRENCIES = ['AMD', 'USD', 'EUR'];

    public const MAX_SUGGESTIONS = 5;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'quote_request_id',
        'organization_id',
        'response_token',
        'status',
        'telegram_message_id',
        'reply_text',
        'responded_at',
        'valid_until',
        'reminded_at',
        'contact_phone',
        'contact_whatsapp',
        'contact_telegram',
        'contact_instagram',
    ];

    protected $casts = [
        'telegram_message_id' => 'integer',
        'responded_at' => 'datetime',
        'valid_until' => 'datetime',
        'viewed_at' => 'datetime',
        'reminded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getHasRepliedAttribute(): bool
    {
        return $this->status === self::STATUS_RESPONDED;
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

    // Whether the agency may still submit or revise its offer.
    public function getIsEditableAttribute(): bool
    {
        return $this->status !== self::STATUS_DECLINED && $this->quoteRequest->is_open;
    }

    public function markViewed(): void
    {
        if ($this->viewed_at === null) {
            $this->forceFill(['viewed_at' => now()])->save();
        }
    }

    public function getIsDeclinedAttribute(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function getHasContactInfoAttribute(): bool
    {
        return $this->contact_phone || $this->contact_whatsapp || $this->contact_telegram || $this->contact_instagram;
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(QuoteSuggestion::class);
    }

    // The representative option for contexts that only show one figure per response (e.g.
    public function cheapestSuggestion(): ?QuoteSuggestion
    {
        return $this->relationLoaded('suggestions')
            ? $this->suggestions->sortBy('price_amount')->first()
            : $this->suggestions()->orderBy('price_amount')->first();
    }

    public function secureRespondUrl(): string
    {
        return URL::route('tourism.respond', [
            'locale' => $this->quoteRequest->locale,
            'token' => $this->response_token,
        ]);
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
