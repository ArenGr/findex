<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Recognises an API key, without requiring one.
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
            return response()->json([
                'message' => 'The provided API key is not valid or has been revoked.',
            ], 401);
        }

        $request->attributes->set('api_key', $key);

        $key->recordUse();

        return $next($request);
    }

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
