<?php

use App\Http\Controllers\LocaleRedirectController;
use App\Support\Features;
use Illuminate\Support\Facades\Route;

// Gates a whole route file on a feature - see App\Support\Features.
$feature = fn (string $key, string $file) => Route::middleware('feature:'.$key)->group(__DIR__.'/web/'.$file);

Route::get('/', LocaleRedirectController::class)->name('root');

Route::prefix('{locale}')
    ->middleware('setlocale')
    ->group(function () use ($feature) {
        // Public website
        require __DIR__.'/web/public/pages.php';
        require __DIR__.'/web/public/organizations.php';
        $feature(Features::ARTICLES, 'public/articles.php');
        $feature(Features::TRAVEL, 'public/tourism.php');
        $feature(Features::EXCHANGE, 'public/exchange.php');
        $feature(Features::INSURANCE, 'public/insurance.php');
        $feature(Features::VISA, 'public/visa.php');

        // Authentication
        require __DIR__.'/web/auth/auth.php';

        // Registration
        $feature(Features::CUSTOMER_REGISTRATION, 'registration/customer.php');
        $feature(Features::ORGANIZATION_REGISTRATION, 'registration/organization.php');
        $feature(Features::WRITER_REGISTRATION, 'registration/writer.php');

        // Authenticated accounts
        $feature(Features::RATE_ALERTS, 'customers/rate-alerts.php');
        $feature(Features::PUBLIC_API, 'customers/api-keys.php');
        require __DIR__.'/web/organizations/dashboard.php';
        $feature(Features::ARTICLES, 'writers/dashboard.php');
    });

$feature(Features::WIDGETS, 'public/widgets.php');

$feature(Features::GOOGLE_AUTH, 'auth/social.php');

$feature(Features::TELEGRAM, 'integrations/telegram.php');
