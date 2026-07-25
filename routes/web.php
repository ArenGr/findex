<?php

use Illuminate\Support\Facades\Route;

// Redirect the bare domain to the visitor's preferred supported language.
Route::get('/', function () {
    $available = array_keys(config('localization.available'));
    $preferred = request()->getPreferredLanguage($available);

    return redirect('/'.($preferred ?? config('localization.default')));
});

Route::prefix('{locale}')
    ->whereIn('locale', array_keys(config('localization.available')))
    ->middleware('setlocale')
    ->group(function () {
        require __DIR__.'/web/marketing.php';
        require __DIR__.'/web/organizations.php';
        require __DIR__.'/web/auth.php';
        require __DIR__.'/web/rate-alerts.php';
        require __DIR__.'/web/tourism.php';
        require __DIR__.'/web/insurance.php';
    });

require __DIR__.'/web/google-auth.php';
require __DIR__.'/web/telegram.php';
