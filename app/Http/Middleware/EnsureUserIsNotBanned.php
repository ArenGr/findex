<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    /**
     * Every guard is checked, not just 'web' - all four share the `users`
     * table and the same banned_at column, so a ban has to end an
     * organization's or writer's session too, not only a customer's.
     * Checking all of them here (rather than one middleware per guard)
     * keeps a single 'banned' alias usable on any route group.
     */
    private const GUARD_LOGIN_ROUTES = [
        'web' => 'login',
        'organization' => 'org.login',
        'writer' => 'writer.login',
    ];

    /**
     * Cuts off a user's session on their very next request after being
     * banned, rather than only blocking their next login attempt (see each
     * AuthenticatedSessionController::store's banned_at credential check).
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::GUARD_LOGIN_ROUTES as $guard => $loginRoute) {
            $user = Auth::guard($guard)->user();

            if (! $user || ! $user->isBanned()) {
                continue;
            }

            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($loginRoute, ['locale' => SetLocale::resolveFor($request)])
                ->withErrors(['email' => __('auth.failed')]);
        }

        return $next($request);
    }
}
