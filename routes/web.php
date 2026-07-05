<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReviewController;
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

        Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');

        Route::middleware('guest')->group(function () {
            Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
            Route::post('/register', [RegisteredUserController::class, 'store']);

            Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store']);
        });

        Route::middleware('auth')->group(function () {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        });
    });

Route::get('/rates', [RateController::class, 'index'])->name('rates.index');
