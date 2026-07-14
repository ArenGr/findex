<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class QuoteResponse extends Model
{
    /**
     * Kept as a plain const list rather than reusing the Currency model -
     * Currency tracks foreign-exchange rates against AMD, a different
     * concept from "which currency is this travel quote priced in" (where
     * AMD itself is a valid answer).
     */
    public const CURRENCIES = ['AMD', 'USD', 'EUR'];

    /**
     * A partner can propose several options within one response (see
     * QuoteSuggestion) - capped to keep the response form and the
     * customer-facing comparison usable rather than a wall of options.
     */
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
        'reminded_at',
    ];

    protected $casts = [
        'telegram_message_id' => 'integer',
        'responded_at' => 'datetime',
        'reminded_at' => 'datetime',
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

    public function suggestions(): HasMany
    {
        return $this->hasMany(QuoteSuggestion::class);
    }

    /**
     * The representative option for contexts that only show one figure per
     * response (e.g. the side-by-side comparison table) - the cheapest,
     * since that's the option most likely to interest a budget-conscious
     * traveler comparing across agencies.
     */
    public function cheapestSuggestion(): ?QuoteSuggestion
    {
        return $this->relationLoaded('suggestions')
            ? $this->suggestions->sortBy('price_amount')->first()
            : $this->suggestions()->orderBy('price_amount')->first();
    }

    /**
     * The secure, unauthenticated link a partner uses to respond - the
     * response_token itself is the credential (a long random opaque
     * string), so unlike QuoteRequest::signedResultsUrl() this doesn't need
     * Laravel's HMAC-signed-URL machinery on top of it.
     */
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
