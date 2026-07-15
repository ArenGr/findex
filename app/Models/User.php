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

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar'])]
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
            'role' => UserRole::class,
        ];
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

    /**
     * Only set when role is UserRole::ORGANIZATION - the business profile
     * this account logs in on behalf of (see Organization::users()).
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function isOrganization(): bool
    {
        return $this->role === UserRole::ORGANIZATION;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Gates the Filament admin panel (see AdminPanelProvider::authGuard('admin'))
     * - the 'admin', 'organization', and 'web' guards all share this same
     * users table/provider now, so this is what actually keeps a customer
     * or organization session out of the panel rather than the guard name
     * itself. See also EnsureUserRole, which enforces the equivalent for
     * the non-Filament 'organization' guard routes.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * banned_at is deliberately not mass-assignable (see $fillable above) -
     * banning/unbanning goes through these dedicated methods instead, so a
     * future `User::create($request->all())` or similar can't be tricked
     * into self-unbanning or forging a ban timestamp.
     */
    public function ban(): void
    {
        $this->forceFill(['banned_at' => now()])->save();
    }

    public function unban(): void
    {
        $this->forceFill(['banned_at' => null])->save();
    }

    /**
     * Overrides the MustVerifyEmail trait's default (which sends Laravel's
     * generic notification) so verification email matches every other
     * outbound email in this app: a branded Mailable sent directly via
     * Mail::to(), not the Notification system.
     */
    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this)->send(new VerifyEmailAddress($this, $this->verificationUrl()));
    }

    /**
     * Guard-agnostic by design (see VerifyEmailController) - the link
     * itself is the credential, so this doesn't need to know or care
     * whether the account it's for is a customer or an organization.
     */
    private function verificationUrl(): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'locale' => app()->getLocale(),
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
        ]);
    }
}
