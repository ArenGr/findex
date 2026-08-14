<?php

use App\Http\Controllers\ExchangePartnerResponseController;
use App\Http\Controllers\ExchangeQuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/exchange', [ExchangeQuoteController::class, 'create'])->name('exchange.request');

// Registered before the {exchangeQuoteRequest} wildcard below so "mine" and
// "resend" aren't swallowed by it and treated as a request ID - same
// reasoning as tourism.php.
Route::get('/exchange/mine', [ExchangeQuoteController::class, 'mine'])
    ->middleware(['auth', 'banned'])
    ->name('exchange.mine');

Route::get('/exchange/resend', [ExchangeQuoteController::class, 'resendForm'])->name('exchange.resend');

// Same response either way (see ExchangeQuoteController::resend) so this
// can't be used to check which emails have filed a request.
Route::middleware(['banned', 'throttle:exchange_quote_link_resend'])->group(function () {
    Route::post('/exchange/resend', [ExchangeQuoteController::class, 'resend'])->name('exchange.resend.send');
});

// The secure, no-login page an exchange office lands on from the Telegram
// notification - registered before the {exchangeQuoteRequest} wildcard
// below for the same reason as "mine" and "resend" above.
Route::get('/exchange/respond/{token}', [ExchangePartnerResponseController::class, 'show'])->name('exchange.respond');

Route::middleware('throttle:exchange_quote_response_submit')->group(function () {
    Route::post('/exchange/respond/{token}', [ExchangePartnerResponseController::class, 'store'])->name('exchange.respond.store');

    // The office reporting what happened at the counter. Same token as the
    // page it is posted from - the shop has no account with us.
    Route::post('/exchange/respond/{token}/outcome', [ExchangePartnerResponseController::class, 'outcome'])
        ->name('exchange.respond.outcome');
});

Route::get('/exchange/{exchangeQuoteRequest}', [ExchangeQuoteController::class, 'show'])->name('exchange.show');

// Picking an offer. Same authorization as the results page it is posted from -
// the owner, or a valid signature - so a guest can accept without an account.
Route::post('/exchange/{exchangeQuoteRequest}/offers/{response}', [ExchangeQuoteController::class, 'accept'])
    ->name('exchange.offers.accept');

// Open to guests, same abuse guard as tourism - each submission fans out to
// every matching partner, so this also protects partners.
Route::middleware(['banned', 'throttle:exchange_quote_requests'])->group(function () {
    Route::post('/exchange', [ExchangeQuoteController::class, 'store'])->name('exchange.request.store');
});
