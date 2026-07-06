<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    /**
     * Cuts off a user's session on their very next request after being
     * banned, rather than only blocking their next login attempt (see
     * AuthenticatedSessionController::store's banned_at credential check).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->isBanned()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login', ['locale' => $request->route('locale')])
                ->withErrors(['email' => __('auth.failed')]);
        }

        return $next($request);
    }
}
