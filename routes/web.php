<?php

use App\Http\Controllers\LocaleRedirectController;
use Illuminate\Support\Facades\Route;

// Invokable controller, not a closure - a single closure anywhere in the
// route files disables `php artisan route:cache` entirely.
Route::get('/', LocaleRedirectController::class)->name('root');

// {locale}'s format is constrained globally in AppServiceProvider::boot(),
// so it doesn't need to be repeated here.
Route::prefix('{locale}')
    ->middleware('setlocale')
    ->group(function () {
        // Public website
        require __DIR__.'/web/public/pages.php';
        require __DIR__.'/web/public/organizations.php';
        require __DIR__.'/web/public/articles.php';
        require __DIR__.'/web/public/tourism.php';
        require __DIR__.'/web/public/exchange.php';
        require __DIR__.'/web/public/insurance.php';

        // Authentication - login, logout, email verification
        require __DIR__.'/web/auth/auth.php';

        // Registration - one file per account type
        require __DIR__.'/web/registration/customer.php';
        require __DIR__.'/web/registration/organization.php';
        require __DIR__.'/web/registration/writer.php';

        // Authenticated account areas, one per account type
        require __DIR__.'/web/customers/rate-alerts.php';
        require __DIR__.'/web/customers/api-keys.php';
        require __DIR__.'/web/organizations/dashboard.php';
        require __DIR__.'/web/writers/dashboard.php';
    });

// Pasted into other people's HTML, so the URL must never move - no locale
// segment for the same reason the OAuth callbacks below have none.
require __DIR__.'/web/public/widgets.php';

// Fixed, non-locale-prefixed URLs registered with external providers
// (OAuth callbacks, webhooks) - see social.php and telegram.php for why.
require __DIR__.'/web/auth/social.php';

// Integrations
require __DIR__.'/web/integrations/telegram.php';
