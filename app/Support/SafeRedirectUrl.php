<?php

namespace App\Support;

use Illuminate\Http\Request;

class SafeRedirectUrl
{
    public static function resolve(Request $request, ?string $candidate, string $fallback): string
    {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            return $fallback;
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        if ($host === false) {
            return $fallback;
        }

        if ($host === null) {
            return str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')
                ? $candidate
                : $fallback;
        }

        return $host === $request->getHost() ? $candidate : $fallback;
    }
}
