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

    /** An offer the visitor picked. Still a reply, so has_replied stays true. */
    public const STATUS_ACCEPTED = 'accepted';

    /*
     * What happened in the shop, as opposed to what happened with us. An
     * accepted offer whose customer never appeared is still accepted - status
     * and outcome answer different questions and must not be collapsed.
     */
    public const OUTCOME_COMPLETED = 'completed';

    public const OUTCOME_NO_SHOW = 'no_show';

    public const OUTCOME_EXPIRED = 'expired';

    public const OUTCOMES = [self::OUTCOME_COMPLETED, self::OUTCOME_NO_SHOW, self::OUTCOME_EXPIRED];

    protected $fillable = [
        'outcome',
        'outcome_at',
        'offer_letter',
        'accepted_at',
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
        'accepted_at' => 'datetime',
        'outcome_at' => 'datetime',
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
        return in_array($this->status, [self::STATUS_RESPONDED, self::STATUS_ACCEPTED], true);
    }

    public function getIsAcceptedAttribute(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * The office can only report an outcome for an offer that was actually
     * chosen, and only once - a shop that could revise "completed" to "no show"
     * a week later would make the conversion numbers worthless.
     */
    public function getAwaitsOutcomeAttribute(): bool
    {
        return $this->is_accepted && $this->outcome === null;
    }

    public function recordOutcome(string $outcome): bool
    {
        if (! $this->awaits_outcome || ! in_array($outcome, self::OUTCOMES, true)) {
            return false;
        }

        $this->forceFill(['outcome' => $outcome, 'outcome_at' => now()])->save();

        return true;
    }

    /**
     * What the visitor reads out at the counter: the request's code plus this
     * offer's letter. Null until the office has actually been given a letter,
     * which happens when the response is created.
     */
    public function getRedemptionCodeAttribute(): ?string
    {
        if ($this->offer_letter === null) {
            return null;
        }

        return $this->exchangeQuoteRequest->public_code.'-'.$this->offer_letter;
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
