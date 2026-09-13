<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $guard, string $role): Response
    {
        abort_unless(Auth::guard($guard)->user()?->role === UserRole::from((int) $role), 403);

        return $next($request);
    }
}
