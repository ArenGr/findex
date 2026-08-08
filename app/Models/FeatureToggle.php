<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An admin-controlled on/off switch for one bank product page. Enabling a
 * key surfaces that category in the header menu and on the /banks hub, and
 * makes its page reachable; disabling hides it everywhere and 404s the page
 * (see OfferController).
 *
 * Rows are seeded rather than created from the panel - a toggle only means
 * something if the app has a page behind it, so the set is defined in code
 * (FeatureToggleSeeder) and the panel only flips them.
 */
class FeatureToggle extends Model
{
    private const CACHE_KEY = 'feature-toggles.enabled';

    protected $fillable = [
        'key',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Read on every request that renders the header, so it's cached as a
     * plain array of enabled keys and busted on any write. Forever, not a
     * TTL: the only thing that changes it is a save/delete here, and both
     * are covered.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, string>
     */
    public static function enabledKeys(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->where('is_enabled', true)->orderBy('key')->pluck('key')->all()
        );
    }

    public static function enabled(string $key): bool
    {
        return in_array($key, static::enabledKeys(), true);
    }
}
