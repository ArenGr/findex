<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
}
