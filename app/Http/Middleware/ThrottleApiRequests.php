<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies whatever the caller's plan allows.
 *
 * Two windows, because they answer different questions: the per-minute limit
 * stops a runaway loop taking the site down, and the daily limit is the product
 * being sold. Both come from config/api.php - no limit is written down here.
 */
class ThrottleApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->attributes->get('api_key');

        [$identifier, $limits] = $key instanceof ApiKey
            ? ['key:'.$key->id, $key->limits()]
            : ['ip:'.$request->ip(), config('api.anonymous')];

        foreach ([['minute', 60], ['day', 86400]] as [$window, $seconds]) {
            $allowance = $limits['requests_per_'.$window] ?? null;

            // Null is unmetered - an enterprise agreement where the contract is
            // the limit, not the code.
            if ($allowance === null) {
                continue;
            }

            $bucket = "api:{$identifier}:{$window}";

            if (RateLimiter::tooManyAttempts($bucket, $allowance)) {
                $retryAfter = RateLimiter::availableIn($bucket);

                return response()->json([
                    'message' => "API rate limit exceeded for this {$window}.",
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $allowance,
                    'X-RateLimit-Remaining' => 0,
                ]);
            }

            RateLimiter::hit($bucket, $seconds);
        }

        $response = $next($request);

        // Reported against the daily allowance: that is the one a customer is
        // budgeting against, and the per-minute guard is ours, not theirs.
        $daily = $limits['requests_per_day'] ?? null;

        if ($daily !== null) {
            $response->headers->add([
                'X-RateLimit-Limit' => $daily,
                'X-RateLimit-Remaining' => max(0, $daily - RateLimiter::attempts("api:{$identifier}:day")),
            ]);
        }

        return $response;
    }
}
