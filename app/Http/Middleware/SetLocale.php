<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the locale from the {locale} route segment, apply it for
     * the current request, and make route()/URL::to() include it by
     * default so views don't need to pass it manually.
     */
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

    /**
     * The locale to build a redirect URL with, for code that runs before (or
     * outside) this middleware and so cannot rely on URL::defaults().
     *
     * Most routes carry the locale as a segment, but the ones registered with
     * external providers at fixed URLs - OAuth callbacks, webhooks - do not.
     * Reading the segment alone yields null there, and route() throws on a
     * missing required parameter rather than omitting it, so a redirect off
     * one of those routes used to 500 instead of going anywhere.
     */
    public static function resolveFor(Request $request): string
    {
        $available = config('localization.available');

        $fromRoute = $request->route('locale');

        if (is_string($fromRoute) && array_key_exists($fromRoute, $available)) {
            return $fromRoute;
        }

        // Set the last time they browsed a locale-prefixed page, so it is a
        // real preference rather than a guess. Null for a guest, by definition.
        $fromUser = $request->user()?->locale;

        if (is_string($fromUser) && array_key_exists($fromUser, $available)) {
            return $fromUser;
        }

        return $request->getPreferredLanguage(array_keys($available))
            ?? config('localization.default');
    }
}
