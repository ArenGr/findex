<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteSuggestion extends Model
{
    protected $fillable = [
        'quote_response_id',
        'price_amount',
        'price_currency',
        'offered_hotel_name',
        'flight_details',
        'inclusions',
        'attachment_path',
        'promo_code',
        'promo_note',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'claimed_at' => 'datetime',
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
}
