<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Public organization directory. Browsing, a single organization's page, and
// reviews are cross-type (bank, exchange, insurance, tourism, other) - see
// OrganizationController::index for the type filter. Rates and comparison
// only ever apply to bank/exchange organizations (Organization::RATES_TYPES)
// - every other type has no currency rates to show.
Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
Route::get('/compare', [CompareController::class, 'show'])->name('organizations.compare');
Route::get('/rates', [RateController::class, 'index'])->name('rates.index');

// Dedicated SEO landing pages for a single organization type - see
// OrganizationController::categoryPage() for why these exist separately
// from index()'s ?type= filter on the generic directory.
Route::get('/banks', [OrganizationController::class, 'banks'])->name('banks');
Route::get('/travel-agencies', [OrganizationController::class, 'travelAgencies'])->name('travel-agencies');

// Open to guests (see ReviewController::store) - 'banned' still
// blocks a signed-in banned user, it's simply a no-op for guests.
Route::middleware(['banned', 'throttle:reviews'])->group(function () {
    Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
