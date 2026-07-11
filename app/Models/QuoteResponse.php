<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const STATUS_PENDING = 'pending';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'quote_request_id',
        'organization_id',
        'response_token',
        'status',
        'telegram_message_id',
        'price_amount',
        'price_currency',
        'offered_hotel_name',
        'flight_details',
        'inclusions',
        'reply_text',
        'attachment_path',
        'responded_at',
    ];

    protected $casts = [
        'telegram_message_id' => 'integer',
        'price_amount' => 'decimal:2',
        'responded_at' => 'datetime',
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
