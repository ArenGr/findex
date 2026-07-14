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
        'review_prompted_at',
        'budget_min_amd',
        'budget_max_amd',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
        'all_inclusive' => 'boolean',
        'insurance' => 'boolean',
        'expires_at' => 'datetime',
        'review_prompted_at' => 'datetime',
        'budget_min_amd' => 'decimal:2',
        'budget_max_amd' => 'decimal:2',
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
     * Matched against a partner's opt-in min_lead_party_size (see
     * Organization::tourismPartnersForDestination()).
     */
    public function getPartySizeAttribute(): int
    {
        return $this->adults + $this->children;
    }

    /**
     * "Closes in 3 days" style countdown for surfacing urgency on the
     * customer-facing request pages - null once the request has closed
     * (those pages show the fixed closed date instead, see is_open).
     */
    public function getClosesInAttribute(): ?string
    {
        return $this->is_open ? $this->expires_at->diffForHumans(['parts' => 1]) : null;
    }

    /**
     * The single figure matched against a partner's opt-in
     * min_lead_budget_amd (see Organization::tourismPartnersForDestination()) -
     * the stated max is the most a partner could hope to capture, so a
     * partner's minimum is checked against it rather than the min (a
     * request with only a min stated falls back to that, since it's the
     * only figure available).
     */
    public function getBudgetForFilteringAttribute(): ?float
    {
        $value = $this->budget_max_amd ?? $this->budget_min_amd;

        return $value !== null ? (float) $value : null;
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
