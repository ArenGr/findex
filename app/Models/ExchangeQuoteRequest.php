<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class ExchangeQuoteRequest extends Model
{
    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'locale',
        'currency_id',
        'amount',
        'rate_field',
        'preferred_city',
        'notes',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
     * "Closes in 3 days" style countdown, same shape as
     * QuoteRequest::getClosesInAttribute() - null once closed (those pages
     * show the fixed closed date instead).
     */
    public function getClosesInAttribute(): ?string
    {
        return $this->is_open ? $this->expires_at->diffForHumans(['parts' => 1]) : null;
    }

    /**
     * A guest has no account to log back into, so this signed link (emailed
     * on submission and on every partner reply) is their only way back to
     * the results page - stays valid exactly as long as the request itself
     * stays open to new replies. Same pattern as
     * QuoteRequest::signedResultsUrl().
     */
    public function signedResultsUrl(): string
    {
        return URL::signedRoute('exchange.show', [
            'locale' => $this->locale,
            'exchangeQuoteRequest' => $this->id,
        ], $this->expires_at);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExchangeQuoteResponse::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
