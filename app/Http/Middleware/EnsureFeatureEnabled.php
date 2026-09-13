<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on one or more feature flags - `->middleware('feature:travel')`.
 *
 * A switched-off feature 404s rather than redirecting: the point of off is that
 * the section is not there, and a redirect would still confirm it exists.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            abort_if(Features::disabled($feature), 404);
        }

        return $next($request);
    }
}
