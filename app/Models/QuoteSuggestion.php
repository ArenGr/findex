<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One priced option within an agency's reply - the thing a traveler actually compares.
class QuoteSuggestion extends Model
{
    // What kind of flight is being quoted.
    public const FLIGHT_DIRECT = 'direct';

    public const FLIGHT_ONE_STOP = 'one_stop';

    public const FLIGHT_MULTI_STOP = 'multi_stop';

    public const FLIGHT_TYPES = [self::FLIGHT_DIRECT, self::FLIGHT_ONE_STOP, self::FLIGHT_MULTI_STOP];

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

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function getIsClaimedAttribute(): bool
    {
        return $this->claimed_by_user_id !== null;
    }

    // An offer is expired when the agency's own deadline has passed.
    public function getIsExpiredAttribute(): bool
    {
        return $this->response->is_expired;
    }

    public function getIsSelectedAttribute(): bool
    {
        return $this->selected_at !== null;
    }

    // Whether this option can still be chosen.
    public function getIsSelectableAttribute(): bool
    {
        return ! $this->is_expired && $this->response->has_replied;
    }

    public function claim(User $user): void
    {
        $this->forceFill([
            'claimed_by_user_id' => $user->id,
            'claimed_at' => now(),
        ])->save();
    }

    // The traveler picking this option.
    public function select(): void
    {
        $this->forceFill(['selected_at' => now()])->save();
    }

    public function deselect(): void
    {
        $this->forceFill(['selected_at' => null])->save();
    }
}
