<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\Organization\Auth\AuthenticatedSessionController as OrganizationAuthenticatedSessionController;
use App\Http\Controllers\Organization\Auth\RegisteredOrganizationController;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\CurrencyRateController;
use App\Http\Controllers\Organization\DashboardController as OrganizationDashboardController;
use App\Http\Controllers\Organization\ProfileController as OrganizationProfileController;
use App\Http\Controllers\Organization\ReportRequestController;
use App\Http\Controllers\Organization\ReviewReplyController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateAlertController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Redirect the bare domain to the visitor's preferred supported language,
// falling back to the site default. Every real page lives under /{locale}/
// so each language gets its own indexable, shareable URL (see SetLocale).
Route::get('/', function () {
    $available = array_keys(config('localization.available'));
    $preferred = request()->getPreferredLanguage($available);

    return redirect('/' . ($preferred ?? config('localization.default')));
});

Route::prefix('{locale}')
    ->whereIn('locale', array_keys(config('localization.available')))
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', function () {
            return view('home');
        })->name('home');

        Route::get('/style-guide', function () {
            return view('style-guide');
        })->name('style-guide');

        Route::get('/about', function () {
            return view('about');
        })->name('about');

        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/compare', [CompareController::class, 'show'])->name('organizations.compare');
        Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');

        Route::get('/register', function () {
            return view('auth.register-choice');
        })->name('register');

        Route::middleware('guest')->group(function () {
            Route::get('/register/customer', [RegisteredUserController::class, 'create'])->name('register.customer');
            Route::post('/register/customer', [RegisteredUserController::class, 'store']);

            Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
        });

        Route::middleware(['auth', 'banned'])->group(function () {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

            Route::get('/alerts', [RateAlertController::class, 'index'])->name('alerts.index');
            Route::post('/alerts', [RateAlertController::class, 'store'])->name('alerts.store');
            Route::patch('/alerts/{rateAlert}/toggle', [RateAlertController::class, 'toggle'])->name('alerts.toggle');
            Route::delete('/alerts/{rateAlert}', [RateAlertController::class, 'destroy'])->name('alerts.destroy');
        });

        Route::prefix('org')->name('org.')->group(function () {
            Route::middleware('guest:organization')->group(function () {
                Route::get('/login', [OrganizationAuthenticatedSessionController::class, 'create'])->name('login');
                Route::post('/login', [OrganizationAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

                Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
                Route::post('/register', [RegisteredOrganizationController::class, 'store']);
            });

            Route::middleware('auth:organization')->group(function () {
                Route::post('/logout', [OrganizationAuthenticatedSessionController::class, 'destroy'])->name('logout');

                Route::prefix('dashboard')->name('dashboard.')->group(function () {
                    Route::get('/', [OrganizationDashboardController::class, 'index'])->name('index');

                    Route::get('/profile', [OrganizationProfileController::class, 'edit'])->name('profile.edit');
                    Route::put('/profile', [OrganizationProfileController::class, 'update'])->name('profile.update');

                    Route::get('/reviews', [ReviewReplyController::class, 'index'])->name('reviews.index');
                    Route::post('/reviews/{review}/reply', [ReviewReplyController::class, 'store'])->name('reviews.reply');

                    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
                    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
                    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
                    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
                    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
                    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

                    Route::get('/reports', [ReportRequestController::class, 'index'])->name('reports.index');
                    Route::get('/reports/create', [ReportRequestController::class, 'create'])->name('reports.create');
                    Route::post('/reports', [ReportRequestController::class, 'store'])->name('reports.store');
                    Route::get('/reports/{reportRequest}', [ReportRequestController::class, 'show'])->name('reports.show');

                    Route::get('/rates', [CurrencyRateController::class, 'index'])->name('rates.index');
                    Route::get('/rates/create', [CurrencyRateController::class, 'create'])->name('rates.create');
                    Route::post('/rates', [CurrencyRateController::class, 'store'])->name('rates.store');
                    Route::get('/rates/{rate}/edit', [CurrencyRateController::class, 'edit'])->name('rates.edit');
                    Route::put('/rates/{rate}', [CurrencyRateController::class, 'update'])->name('rates.update');
                });
            });
        });
    });

Route::get('/rates', [RateController::class, 'index'])->name('rates.index');

// A single fixed callback URL is far simpler to register with Google than a
// locale-prefixed one, so this pair lives outside the {locale} group - the
// current locale is restored via session (see GoogleAuthController).
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

// Telegram POSTs here directly (see TelegramWebhookController and the
// telegram:webhook command) - no locale prefix, no CSRF token, since this
// isn't a browser request. Auth is the X-Telegram-Bot-Api-Secret-Token
// header, checked in the controller.
Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
