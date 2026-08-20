<?php

use App\Http\Controllers\DestinationAlertController;
use App\Http\Controllers\PartnerResponseController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\VoiceFillController;
use Illuminate\Support\Facades\Route;

Route::get('/tourism', [QuoteRequestController::class, 'create'])->name('tourism.request');

// Open to guests, same as the request form itself - each hit is two paid
// OpenAI calls, so it's throttled far tighter than the other tourism
// endpoints (see the voice_fill limiter in AppServiceProvider).
Route::middleware(['banned', 'throttle:voice_fill'])->group(function () {
    Route::post('/tourism/voice-fill', [VoiceFillController::class, 'store'])->name('tourism.voice-fill');
});

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

// The agency's own attachment, reached with the same response token as the
// page it is linked from - the file is on the private disk, so this is the
// only route to it. Registered beside the other respond routes, ahead of the
// {quoteRequest} wildcard below.
Route::get('/tourism/respond/{token}/attachment/{suggestion}', [PartnerResponseController::class, 'attachment'])
    ->name('tourism.respond.attachment');

Route::middleware('throttle:quote_response_submit')->group(function () {
    Route::post('/tourism/respond/{token}', [PartnerResponseController::class, 'store'])->name('tourism.respond.store');
});

// The request's own page: where it stands, who was contacted, how many
// have answered. Where submission lands and where the confirmation email
// points - see QuoteRequest::signedResultsUrl().
Route::get('/tourism/{quoteRequest}', [QuoteRequestController::class, 'show'])->name('tourism.show');

// Everything below is gated the same way as the status page above - the
// owning account, or a valid signature. A guest has no account, so the
// status page mints a fresh signed link for each of these (see
// QuoteRequest::signedUrlFor()); Laravel's signature covers the exact
// route, so one can't be reused to reach another.
Route::get('/tourism/{quoteRequest}/offers', [QuoteRequestController::class, 'offers'])->name('tourism.offers');

Route::get('/tourism/{quoteRequest}/compare', [QuoteRequestController::class, 'compare'])->name('tourism.compare');

Route::get('/tourism/{quoteRequest}/offers/{suggestion}', [QuoteRequestController::class, 'offer'])->name('tourism.offers.show');

// Gated exactly like the offer page above - the attachment is one
// traveller's pricing and lives on the private disk, not behind a
// permanent public URL.
Route::get('/tourism/{quoteRequest}/offers/{suggestion}/attachment', [QuoteRequestController::class, 'offerAttachment'])
    ->name('tourism.offers.attachment');

Route::post('/tourism/{quoteRequest}/offers/{suggestion}/select', [QuoteRequestController::class, 'selectOffer'])
    ->name('tourism.offers.select');

// Ending a request early - the traveler has picked someone, or is no
// longer travelling. Same authorization as the pages above, and the same
// outcome as letting the clock run out.
Route::post('/tourism/{quoteRequest}/close', [QuoteRequestController::class, 'close'])->name('tourism.close');

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
