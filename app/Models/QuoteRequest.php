<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class QuoteRequest extends Model
{
    /**
     * Destination countries a request can be filed for, and the only values
     * a tourism organization can register as "served" (see TourismDestination).
     * Kept as a plain const list rather than a DB table or enum, matching how
     * Organization::TYPES-style lists already work elsewhere in this app.
     */
    public const DESTINATIONS = ['AE', 'EG', 'GE', 'GR', 'TH', 'CY', 'IT', 'FR', 'ES'];

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'locale',
        'destination_country',
        'hotel_name',
        'check_in',
        'check_out',
        'adults',
        'children',
        'all_inclusive',
        'insurance',
        'notes',
        'expires_at',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
        'all_inclusive' => 'boolean',
        'insurance' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The account name if filed while signed in, otherwise the guest's own name.
     */
    public function getRequesterNameAttribute(): ?string
    {
        return $this->user->name ?? $this->guest_name;
    }

    public function getRequesterEmailAttribute(): ?string
    {
        return $this->user->email ?? $this->guest_email;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * A guest has no account to log back into, so this signed link (emailed
     * on submission and on every partner reply) is their only way back to
     * the results page - it stays valid exactly as long as the request
     * itself stays open to new replies.
     */
    public function signedResultsUrl(): string
    {
        return URL::signedRoute('tourism.show', [
            'locale' => $this->locale,
            'quoteRequest' => $this->id,
        ], $this->expires_at);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuoteResponse::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
