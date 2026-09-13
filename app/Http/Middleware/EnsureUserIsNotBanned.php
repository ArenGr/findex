<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    private const GUARD_LOGIN_ROUTES = [
        'web' => 'login',
        'organization' => 'org.login',
        'writer' => 'writer.login',
    ];

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
