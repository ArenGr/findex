<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\CurrencyLandingController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\RateHistoryController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Public organization directory.
Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
Route::get('/compare', [CompareController::class, 'show'])->name('organizations.compare');
Route::get('/rates', [RateController::class, 'index'])->name('rates.index');

Route::get('/rates/history', [RateHistoryController::class, 'index'])->name('rates.history');

// One landing page per currency, for the search "USD to AMD rate today".
Route::get('/rates/{currency}', [CurrencyLandingController::class, 'show'])
    ->where('currency', '[A-Za-z]{3}')
    ->name('rates.currency');

Route::get('/banks/all', [OrganizationController::class, 'banks'])->name('banks.all');
Route::get('/travel-agencies', [OrganizationController::class, 'travelAgencies'])->name('travel-agencies');
Route::get('/insurance/companies', [OrganizationController::class, 'insuranceCompanies'])->name('insurance.companies');

Route::middleware(['banned', 'throttle:reviews'])->group(function () {
    Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
