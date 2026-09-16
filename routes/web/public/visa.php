<?php

use App\Http\Controllers\VisaPartnerResponseController;
use App\Http\Controllers\VisaRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/visa', [VisaRequestController::class, 'create'])->name('visa.request');

// Declared before /visa/{visaRequest}, or the token would be read as an id.
Route::get('/visa/respond/{token}', [VisaPartnerResponseController::class, 'show'])->name('visa.respond');

Route::middleware('throttle:quote_response_submit')->group(function () {
    Route::post('/visa/respond/{token}', [VisaPartnerResponseController::class, 'store'])->name('visa.respond.store');
});

Route::get('/visa/{visaRequest}', [VisaRequestController::class, 'show'])->name('visa.show');

Route::post('/visa/{visaRequest}/close', [VisaRequestController::class, 'close'])->name('visa.close');

Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
    Route::post('/visa', [VisaRequestController::class, 'store'])->name('visa.request.store');
});
