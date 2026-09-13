<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! array_key_exists($locale, config('localization.available'))) {
            abort(404);
        }

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }

    public static function resolveFor(Request $request): string
    {
        $available = config('localization.available');

        $fromRoute = $request->route('locale');

        if (is_string($fromRoute) && array_key_exists($fromRoute, $available)) {
            return $fromRoute;
        }

        $fromUser = $request->user()?->locale;

        if (is_string($fromUser) && array_key_exists($fromUser, $available)) {
            return $fromUser;
        }

        return $request->getPreferredLanguage(array_keys($available))
            ?? config('localization.default');
    }
}
