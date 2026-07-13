<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationType
{
    /**
     * Blocks org-type-specific dashboard pages (e.g. Rates for bank/exchange,
     * Tourism for tourism) at the route level, not just in the nav - without
     * this, hiding a link in dashboard.blade.php doesn't stop another org
     * type from hitting the URL directly.
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $organization = Auth::guard('organization')->user()?->organization;

        abort_unless($organization && in_array($organization->type, $types, true), 403);

        return $next($request);
    }
}
