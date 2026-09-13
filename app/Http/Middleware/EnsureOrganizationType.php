<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationType
{
    // Blocks org-type-specific dashboard pages (e.g.
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $organization = Auth::guard('organization')->user()?->organization;

        abort_unless($organization && in_array($organization->type, $types, true), 403);

        return $next($request);
    }
}
