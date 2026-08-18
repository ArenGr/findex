<?php

use App\Http\Controllers\ExchangePartnerResponseController;
use App\Http\Controllers\ExchangeQuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/exchange', [ExchangeQuoteController::class, 'create'])->name('exchange.request');

Route::get('/exchange/mine', [ExchangeQuoteController::class, 'mine'])
    ->middleware(['auth', 'banned'])
    ->name('exchange.mine');

Route::get('/exchange/resend', [ExchangeQuoteController::class, 'resendForm'])->name('exchange.resend');

Route::middleware(['banned', 'throttle:exchange_quote_link_resend'])->group(function () {
    Route::post('/exchange/resend', [ExchangeQuoteController::class, 'resend'])->name('exchange.resend.send');
});

Route::get('/exchange/respond/{token}', [ExchangePartnerResponseController::class, 'show'])->name('exchange.respond');

Route::middleware('throttle:exchange_quote_response_submit')->group(function () {
    Route::post('/exchange/respond/{token}', [ExchangePartnerResponseController::class, 'store'])->name('exchange.respond.store');

    Route::post('/exchange/respond/{token}/outcome', [ExchangePartnerResponseController::class, 'outcome'])
        ->name('exchange.respond.outcome');
});

Route::get('/exchange/{exchangeQuoteRequest}', [ExchangeQuoteController::class, 'show'])->name('exchange.show');

Route::post('/exchange/{exchangeQuoteRequest}/offers/{response}', [ExchangeQuoteController::class, 'accept'])
    ->name('exchange.offers.accept');

Route::post('/exchange/{exchangeQuoteRequest}/cancel', [ExchangeQuoteController::class, 'cancel'])
    ->name('exchange.cancel');

Route::middleware(['banned', 'throttle:exchange_quote_requests'])->group(function () {
    Route::post('/exchange', [ExchangeQuoteController::class, 'store'])->name('exchange.request.store');
});
