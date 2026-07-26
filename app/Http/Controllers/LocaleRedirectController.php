<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirects the bare domain to the visitor's preferred supported language.
 *
 * Lives in a controller (not a route closure) so the route table stays
 * fully cacheable with `php artisan route:cache` - a single closure
 * anywhere in the route files disables caching entirely.
 */
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $available = array_keys(config('localization.available'));
        $preferred = $request->getPreferredLanguage($available);

        return redirect('/'.($preferred ?? config('localization.default')));
    }
}
