<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\VerifyEmailAddress;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'telegram_chat_id', 'telegram_connect_token', 'viber_chat_id', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned_at' => 'datetime',
            'review_prompts_opted_out_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    // The two letters that stand in for a face.
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = match (count($words)) {
            0 => mb_substr(trim($this->email ?? ''), 0, 1),
            1 => mb_substr($words[0], 0, 1),
            default => mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1),
        };

        return mb_strtoupper($letters) ?: '-';
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function rateAlerts(): HasMany
    {
        return $this->hasMany(RateAlert::class);
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function exchangeQuoteRequests(): HasMany
    {
        return $this->hasMany(ExchangeQuoteRequest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function isOrganization(): bool
    {
        return $this->role === UserRole::ORGANIZATION;
    }

    public function isWriter(): bool
    {
        return $this->role === UserRole::WRITER;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function ban(): void
    {
        $this->forceFill(['banned_at' => now()])->save();
    }

    public function unban(): void
    {
        $this->forceFill(['banned_at' => null])->save();
    }

    public function optOutOfReviewPrompts(): void
    {
        $this->forceFill(['review_prompts_opted_out_at' => now()])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this)->send(new VerifyEmailAddress($this, $this->verificationUrl()));
    }

    private function verificationUrl(): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'locale' => app()->getLocale(),
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
        ]);
    }
}
