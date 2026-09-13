<?php

use App\Http\Controllers\DestinationAlertController;
use App\Http\Controllers\PartnerResponseController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\VoiceFillController;
use Illuminate\Support\Facades\Route;

Route::get('/tourism', [QuoteRequestController::class, 'create'])->name('tourism.request');

Route::middleware(['banned', 'throttle:voice_fill'])->group(function () {
    Route::post('/tourism/voice-fill', [VoiceFillController::class, 'store'])->name('tourism.voice-fill');
});

Route::get('/tourism/mine', [QuoteRequestController::class, 'mine'])
    ->middleware(['auth', 'banned'])
    ->name('tourism.mine');

Route::get('/tourism/resend', [QuoteRequestController::class, 'resendForm'])->name('tourism.resend');

Route::middleware(['banned', 'throttle:quote_link_resend'])->group(function () {
    Route::post('/tourism/resend', [QuoteRequestController::class, 'resend'])->name('tourism.resend.send');
});

Route::get('/tourism/respond/{token}', [PartnerResponseController::class, 'show'])->name('tourism.respond');

Route::get('/tourism/respond/{token}/attachment/{suggestion}', [PartnerResponseController::class, 'attachment'])
    ->name('tourism.respond.attachment');

Route::middleware('throttle:quote_response_submit')->group(function () {
    Route::post('/tourism/respond/{token}', [PartnerResponseController::class, 'store'])->name('tourism.respond.store');
});

// The request's own page: where it stands, who was contacted, how many have answered.
Route::get('/tourism/{quoteRequest}', [QuoteRequestController::class, 'show'])->name('tourism.show');

Route::get('/tourism/{quoteRequest}/offers', [QuoteRequestController::class, 'offers'])->name('tourism.offers');

Route::get('/tourism/{quoteRequest}/compare', [QuoteRequestController::class, 'compare'])->name('tourism.compare');

Route::get('/tourism/{quoteRequest}/offers/{suggestion}', [QuoteRequestController::class, 'offer'])->name('tourism.offers.show');

Route::get('/tourism/{quoteRequest}/offers/{suggestion}/attachment', [QuoteRequestController::class, 'offerAttachment'])
    ->name('tourism.offers.attachment');

Route::post('/tourism/{quoteRequest}/offers/{suggestion}/select', [QuoteRequestController::class, 'selectOffer'])
    ->name('tourism.offers.select');

// Ending a request early - the traveler has picked someone, or is no longer travelling.
Route::post('/tourism/{quoteRequest}/close', [QuoteRequestController::class, 'close'])->name('tourism.close');

Route::middleware(['auth', 'banned'])->group(function () {
    Route::post('/tourism/{quoteRequest}/suggestions/{suggestion}/claim', [QuoteRequestController::class, 'claimSuggestion'])
        ->name('tourism.suggestions.claim');
});

Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
    Route::post('/tourism', [QuoteRequestController::class, 'store'])->name('tourism.request.store');
});

Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
    Route::post('/tourism/destination-alerts', [DestinationAlertController::class, 'store'])->name('tourism.destination-alerts.store');
});

Route::middleware('signed')->group(function () {
    Route::get('/tourism/destination-alerts/unsubscribe', [DestinationAlertController::class, 'unsubscribe'])
        ->name('tourism.destination-alerts.unsubscribe');

    Route::get('/tourism/review-prompts/unsubscribe', [QuoteRequestController::class, 'unsubscribeFromReviewPrompts'])
        ->name('tourism.review-prompts.unsubscribe');
});
