<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

class OrgRatingsCache
{
    public const TAG = 'org-ratings';

    public static function invalidate(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
