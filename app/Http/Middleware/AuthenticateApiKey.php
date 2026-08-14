<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recognises an API key, without requiring one.
 *
 * The public API stays open: a rate board nobody can read is not much of a rate
 * board, and the free tier is part of the funnel. A key is how a caller is
 * identified for a larger allowance and for usage reporting - so a missing key
 * is fine, and only a *wrong* one is an error.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenFrom($request);

        if ($token === null) {
            return $next($request);
        }

        $key = ApiKey::findByToken($token);

        if ($key === null) {
            // Distinct from "no key at all": someone holding a revoked or
            // mistyped key needs to be told, not quietly downgraded to the
            // anonymous allowance and left wondering why they are throttled.
            return response()->json([
                'message' => 'The provided API key is not valid or has been revoked.',
            ], 401);
        }

        $request->attributes->set('api_key', $key);

        $key->recordUse();

        return $next($request);
    }

    /**
     * Authorization: Bearer <key> preferred; ?api_key= accepted because some
     * callers (a spreadsheet, a widget embed) cannot set headers at all.
     */
    private function tokenFrom(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $query = $request->query('api_key');

        return is_string($query) && $query !== '' ? $query : null;
    }
}
