<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

// An admin-controlled on/off switch for one bank product page.
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
