<?php

use App\Http\Controllers\LocaleRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', LocaleRedirectController::class)->name('root');

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

        // Authentication
        require __DIR__.'/web/auth/auth.php';

        // Registration
        require __DIR__.'/web/registration/customer.php';
        require __DIR__.'/web/registration/organization.php';
        require __DIR__.'/web/registration/writer.php';

        // Authenticated accounts
        require __DIR__.'/web/customers/rate-alerts.php';
        require __DIR__.'/web/customers/api-keys.php';
        require __DIR__.'/web/organizations/dashboard.php';
        require __DIR__.'/web/writers/dashboard.php';
    });

require __DIR__.'/web/public/widgets.php';

require __DIR__.'/web/auth/social.php';

require __DIR__.'/web/integrations/telegram.php';
