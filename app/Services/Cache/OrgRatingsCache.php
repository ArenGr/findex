<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * The tag for cache entries that eager-load Organization::withRatingStats()
 * (review count/average) alongside rate data - a separate tag from
 * RateCache::TAG since a review write shouldn't force-expire every plain
 * rate lookup, only the entries that actually depend on ratings.
 */
class OrgRatingsCache
{
    public const TAG = 'org-ratings';

    public static function invalidate(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
