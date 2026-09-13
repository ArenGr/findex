<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\CurrencyLandingController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\RateHistoryController;
use App\Http\Controllers\ReviewController;
use App\Support\Features;
use Illuminate\Support\Facades\Route;

// Public organization directory.
Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index')->middleware('feature:'.Features::ORGANIZATIONS);
Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show')->middleware('feature:'.Features::ORGANIZATIONS);
Route::get('/compare', [CompareController::class, 'show'])->name('organizations.compare')->middleware('feature:'.Features::COMPARE);
Route::get('/rates', [RateController::class, 'index'])->name('rates.index')->middleware('feature:'.Features::RATES);

Route::get('/rates/history', [RateHistoryController::class, 'index'])->name('rates.history')->middleware('feature:'.Features::RATES_HISTORY);

// One landing page per currency, for the search "USD to AMD rate today".
Route::get('/rates/{currency}', [CurrencyLandingController::class, 'show'])
    ->where('currency', '[A-Za-z]{3}')
    ->middleware('feature:'.Features::RATES)
    ->name('rates.currency');

Route::get('/banks/all', [OrganizationController::class, 'banks'])->middleware('feature:'.Features::ORGANIZATIONS)->name('banks.all');
Route::get('/travel-agencies', [OrganizationController::class, 'travelAgencies'])->middleware('feature:'.Features::TRAVEL)->name('travel-agencies');
Route::get('/insurance/companies', [OrganizationController::class, 'insuranceCompanies'])->middleware('feature:'.Features::INSURANCE)->name('insurance.companies');

Route::middleware(['banned', 'throttle:reviews'])->group(function () {
    Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->middleware('feature:'.Features::REVIEWS)->name('reviews.store');
});
