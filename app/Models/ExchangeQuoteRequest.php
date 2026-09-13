<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class ExchangeQuoteRequest extends Model
{
    protected $fillable = [
        'public_code',
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

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            if ($request->public_code !== null) {
                return;
            }

            do {
                $code = 'FX-'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            } while (static::where('public_code', $code)->exists());

            $request->public_code = $code;
        });
    }

    // The account name if filed while signed in, otherwise the guest's own name.
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

    public function getClosesInAttribute(): ?string
    {
        return $this->is_open ? $this->expires_at->diffForHumans(['parts' => 1]) : null;
    }

    public function getClosesInShortAttribute(): ?string
    {
        return $this->is_open
            ? $this->expires_at->diffForHumans(['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE])
            : null;
    }

    private const LINK_LIFETIME_DAYS = 30;

    public function signedResultsUrl(): string
    {
        return URL::signedRoute('exchange.show', [
            'locale' => $this->locale,
            'exchangeQuoteRequest' => $this->id,
        ], $this->expires_at->copy()->addDays(self::LINK_LIFETIME_DAYS));
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
