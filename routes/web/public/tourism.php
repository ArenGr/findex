<?php

use App\Http\Controllers\DestinationAlertController;
use App\Http\Controllers\PartnerResponseController;
use App\Http\Controllers\QuoteRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/tourism', [QuoteRequestController::class, 'create'])->name('tourism.request');

// Registered before the {quoteRequest} wildcard below so "mine" and
// "resend" aren't swallowed by it and treated as a request ID.
Route::get('/tourism/mine', [QuoteRequestController::class, 'mine'])
    ->middleware(['auth', 'banned'])
    ->name('tourism.mine');

Route::get('/tourism/resend', [QuoteRequestController::class, 'resendForm'])->name('tourism.resend');

// Same response either way (see QuoteRequestController::resend) so
// this can't be used to check which emails have filed a request.
Route::middleware(['banned', 'throttle:quote_link_resend'])->group(function () {
    Route::post('/tourism/resend', [QuoteRequestController::class, 'resend'])->name('tourism.resend.send');
});

// The secure, no-login page a partner lands on from the Telegram
// notification - registered before the {quoteRequest} wildcard below
// for the same reason as "mine" and "resend" above.
Route::get('/tourism/respond/{token}', [PartnerResponseController::class, 'show'])->name('tourism.respond');

Route::middleware('throttle:quote_response_submit')->group(function () {
    Route::post('/tourism/respond/{token}', [PartnerResponseController::class, 'store'])->name('tourism.respond.store');
});

Route::get('/tourism/{quoteRequest}', [QuoteRequestController::class, 'show'])->name('tourism.show');

Route::middleware(['auth', 'banned'])->group(function () {
    Route::post('/tourism/{quoteRequest}/suggestions/{suggestion}/claim', [QuoteRequestController::class, 'claimSuggestion'])
        ->name('tourism.suggestions.claim');
});

// Open to guests, same abuse guard as reviews above - each submission
// fans out to every matching partner, so this also protects partners.
Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
    Route::post('/tourism', [QuoteRequestController::class, 'store'])->name('tourism.request.store');
});

Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
    Route::post('/tourism/destination-alerts', [DestinationAlertController::class, 'store'])->name('tourism.destination-alerts.store');
});

// The one-click link from a DestinationNowAvailable email footer - signed
// rather than behind auth, since most destination alerts are left by
// guests with no account to log back into.
Route::middleware('signed')->group(function () {
    Route::get('/tourism/destination-alerts/unsubscribe', [DestinationAlertController::class, 'unsubscribe'])
        ->name('tourism.destination-alerts.unsubscribe');

    // The one-click link from a TripReviewPrompt email footer - only ever
    // generated for requests placed by a logged-in account (see
    // TripReviewPrompt::build()), so the ?user= query param always resolves.
    Route::get('/tourism/review-prompts/unsubscribe', [QuoteRequestController::class, 'unsubscribeFromReviewPrompts'])
        ->name('tourism.review-prompts.unsubscribe');
});
