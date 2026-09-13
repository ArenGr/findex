<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

class RateCache
{
    public const TAG = 'rates';

    public static function invalidate(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
