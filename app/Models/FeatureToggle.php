<?php

namespace App\Models;

use App\Support\Features;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

// The stored on/off state of one feature. App\Support\Features says which exist.
class FeatureToggle extends Model
{
    private const CACHE_KEY = 'feature-toggles.states';

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
     * What the table says, before parents are applied.
     *
     * @return array<string, bool>
     */
    public static function storedStates(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('is_enabled', 'key')->map(fn ($on) => (bool) $on)->all()
        );
    }

    /**
     * Every switched-on key, parents applied.
     *
     * @return array<int, string>
     */
    public static function enabledKeys(): array
    {
        return array_keys(array_filter(Features::states()));
    }

    public static function enabled(string $key): bool
    {
        return Features::enabled($key);
    }

    /** Puts every known flag back to the default declared in the registry. */
    public static function resetToDefaults(): int
    {
        $changed = 0;

        foreach (Features::all() as $key => $meta) {
            $changed += static::query()
                ->where('key', $key)
                ->where('is_enabled', '!=', $meta['default'])
                ->update(['is_enabled' => $meta['default']]);
        }

        static::forgetCache();

        return $changed;
    }

    /** Adds a row for every registry key that has none, at its default. */
    public static function sync(): int
    {
        $existing = static::query()->pluck('key')->all();
        $missing = array_diff(Features::keys(), $existing);

        foreach ($missing as $key) {
            static::create(['key' => $key, 'is_enabled' => Features::all()[$key]['default']]);
        }

        return count($missing);
    }
}
