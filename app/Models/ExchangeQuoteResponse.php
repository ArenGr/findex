<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class ExchangeQuoteResponse extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'exchange_quote_request_id',
        'organization_id',
        'response_token',
        'status',
        'posted_rate',
        'offered_rate',
        'reply_text',
        'telegram_message_id',
        'responded_at',
        'reminded_at',
    ];

    protected $casts = [
        'posted_rate' => 'decimal:4',
        'offered_rate' => 'decimal:4',
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

    /**
     * True once the org has offered something better than what was posted
     * when the request went out - drives the "Improved" badge/filter on the
     * results page. False for a plain "kept as is" confirmation.
     */
    public function getHasImprovedRateAttribute(): bool
    {
        return $this->has_replied && $this->offered_rate !== null && (float) $this->offered_rate > (float) $this->posted_rate;
    }

    /**
     * The secure, unauthenticated link a partner uses to respond - the
     * response_token itself is the credential, same pattern as
     * QuoteResponse::secureRespondUrl().
     */
    public function secureRespondUrl(): string
    {
        return URL::route('exchange.respond', [
            'locale' => $this->exchangeQuoteRequest->locale,
            'token' => $this->response_token,
        ]);
    }

    public function exchangeQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(ExchangeQuoteRequest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
