<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Defense in depth now that the 'web'/'organization'/'admin' guards
     * all share the same users provider (see config/auth.php) - guard
     * membership alone no longer structurally guarantees role the way it
     * did when each guard had its own table. Role-scoped login already
     * keeps the wrong role from authenticating in the first place (see
     * the customer/organization AuthenticatedSessionControllers'
     * Auth::attempt() calls and User::canAccessPanel() for the
     * Filament-driven 'admin' guard), but this middleware backstops any
     * future code path that logs a user into a guard without checking
     * role first.
     */
    public function handle(Request $request, Closure $next, string $guard, string $role): Response
    {
        // Route middleware params always arrive as strings - $role is the
        // enum's raw int value stringified by the route definition (e.g.
        // 'role:organization,'.UserRole::ORGANIZATION->value).
        abort_unless(Auth::guard($guard)->user()?->role === UserRole::from((int) $role), 403);

        return $next($request);
    }
}
