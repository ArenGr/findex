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

    /**
     * The code a visitor quotes, and the only thing an exchange office needs in
     * order to find this request. Five digits is enough to be unambiguous read
     * aloud and short enough to write on a hand - and it carries nothing about
     * the person, which is the point.
     */
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
     * The same span without "from now" on the end, for the places that supply
     * their own framing - "59 minutes left" rather than "59 minutes from now
     * left". Translated by Carbon either way, which is why neither of these is
     * a count of minutes formatted in a view.
     */
    public function getClosesInShortAttribute(): ?string
    {
        return $this->is_open
            ? $this->expires_at->diffForHumans(['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE])
            : null;
    }

    /**
     * A guest has no account to log back into, so this signed link (emailed
     * on submission and on every partner reply) is their only way back to
     * the results page - stays valid exactly as long as the request itself
     * stays open to new replies. Same pattern as
     * QuoteRequest::signedResultsUrl().
     */
    /**
     * How long the offer window runs and how long you can read your own
     * request are different questions, and signing the link with expires_at
     * answered them with one number: the moment the window shut, the link
     * 403'd - so the "request expired" page was unreachable by exactly the
     * people it is written for, and a guest lost the record of what they had
     * asked for. Harmless while windows were a week long; the windows are now
     * fifteen minutes.
     *
     * Answering and accepting stay bounded by expires_at, enforced in
     * ExchangeQuoteController::accept, which 410s once it passes.
     */
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
