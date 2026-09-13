<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Redirects the bare domain to the visitor's preferred supported language.
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $available = array_keys(config('localization.available'));
        $preferred = $request->getPreferredLanguage($available);

        return redirect('/'.($preferred ?? config('localization.default')));
    }
}
