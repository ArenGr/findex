<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced option within an agency's reply - the thing a traveler
 * actually compares. An agency may send several (a budget and a premium
 * package, say), capped by QuoteResponse::MAX_SUGGESTIONS.
 */
class QuoteSuggestion extends Model
{
    /**
     * What kind of flight is being quoted. Deliberately not a free-text
     * field: "direct" is the single most common thing a traveler filters
     * on, and it can't be filtered on if one agency writes "direct",
     * another "non-stop" and a third "no changes".
     */
    public const FLIGHT_DIRECT = 'direct';

    public const FLIGHT_ONE_STOP = 'one_stop';

    public const FLIGHT_MULTI_STOP = 'multi_stop';

    public const FLIGHT_TYPES = [self::FLIGHT_DIRECT, self::FLIGHT_ONE_STOP, self::FLIGHT_MULTI_STOP];

    /**
     * Mirrors QuoteRequest::MEAL_PREFERENCES minus "any" - "any" is a thing
     * a traveler can ask for but not a thing an agency can quote.
     */
    public const MEAL_PLANS = [
        QuoteRequest::MEAL_BREAKFAST,
        QuoteRequest::MEAL_HALF_BOARD,
        QuoteRequest::MEAL_FULL_BOARD,
        QuoteRequest::MEAL_ALL_INCLUSIVE,
    ];

    public const MIN_HOTEL_STARS = 1;

    public const MAX_HOTEL_STARS = 5;

    protected $fillable = [
        'quote_response_id',
        'price_amount',
        'price_currency',
        'offered_hotel_name',
        'hotel_stars',
        'flight_included',
        'flight_type',
        'flight_details',
        'meal_plan',
        'transfer_included',
        'insurance_included',
        'inclusions',
        'attachment_path',
        'promo_code',
        'promo_note',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'hotel_stars' => 'integer',
        'flight_included' => 'boolean',
        'transfer_included' => 'boolean',
        'insurance_included' => 'boolean',
        'claimed_at' => 'datetime',
        'selected_at' => 'datetime',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(QuoteResponse::class, 'quote_response_id');
    }

    /**
     * Only set once claimed (see claim()) - the customer account an org can
     * cross-check in person against whoever shows up with the promo code.
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function getIsClaimedAttribute(): bool
    {
        return $this->claimed_by_user_id !== null;
    }

    /**
     * An offer is expired when the agency's own deadline has passed. Read
     * off the parent response, which is where the agency states it - see
     * the valid_until column's migration for why it lives there.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->response->is_expired;
    }

    public function getIsSelectedAttribute(): bool
    {
        return $this->selected_at !== null;
    }

    /**
     * Whether this option can still be chosen. An expired offer stays
     * visible - it's part of the record of what was quoted - but it isn't a
     * live offer any more, and the agency isn't holding that price.
     */
    public function getIsSelectableAttribute(): bool
    {
        return ! $this->is_expired && $this->response->has_replied;
    }

    /**
     * claimed_by_user_id/claimed_at are deliberately not mass-assignable
     * (see $fillable above) - claiming goes through this dedicated method
     * instead, matching User::ban()'s reasoning for banned_at.
     */
    public function claim(User $user): void
    {
        $this->forceFill([
            'claimed_by_user_id' => $user->id,
            'claimed_at' => now(),
        ])->save();
    }

    /**
     * The traveler picking this option. Same reasoning as claim() for not
     * being mass-assignable, and the same "changing your mind is allowed"
     * behaviour as the exchange flow's accept() - the caller clears any
     * previous selection on the same request first.
     */
    public function select(): void
    {
        $this->forceFill(['selected_at' => now()])->save();
    }

    public function deselect(): void
    {
        $this->forceFill(['selected_at' => null])->save();
    }
}
