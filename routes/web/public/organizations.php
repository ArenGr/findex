<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\CurrencyLandingController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\RateHistoryController;
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

// Deliberately its own page rather than a panel on /rates: charts are a
// second question, and §19 asks the main page not to carry them.
Route::get('/rates/history', [RateHistoryController::class, 'index'])->name('rates.history');

// One landing page per currency, for the search "USD to AMD rate today".
// Registered after /rates/history so the fixed path is never swallowed by the
// parameter, and constrained to three letters so it cannot become a catch-all.
Route::get('/rates/{currency}', [CurrencyLandingController::class, 'show'])
    ->where('currency', '[A-Za-z]{3}')
    ->name('rates.currency');

// Dedicated SEO landing pages for a single organization type - see
// OrganizationController::categoryPage() for why these exist separately
// from index()'s ?type= filter on the generic directory. /banks/all (not
// bare /banks) since that URL now belongs to the bank-products hub - see
// pages.php's banks.index/banks.show.
Route::get('/banks/all', [OrganizationController::class, 'banks'])->name('banks.all');
Route::get('/travel-agencies', [OrganizationController::class, 'travelAgencies'])->name('travel-agencies');
// /insurance/companies, not /insurance/all - "companies" is the label
// actually used in the Insurance nav menu (see site-header.blade.php),
// and reads more naturally than "all" does for insurance specifically.
Route::get('/insurance/companies', [OrganizationController::class, 'insuranceCompanies'])->name('insurance.companies');

// Open to guests (see ReviewController::store) - 'banned' still
// blocks a signed-in banned user, it's simply a no-op for guests.
Route::middleware(['banned', 'throttle:reviews'])->group(function () {
    Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
