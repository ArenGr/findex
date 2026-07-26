<?php

use App\Http\Controllers\AutoInsuranceController;
use Illuminate\Support\Facades\Route;

Route::get('/insurance/auto', [AutoInsuranceController::class, 'create'])->name('insurance.auto.request');
Route::get('/insurance/auto/{autoInsuranceRequest}', [AutoInsuranceController::class, 'show'])->name('insurance.auto.show');
Route::middleware('throttle:quote_response_submit')->group(function () {
    Route::post('/insurance/auto/{autoInsuranceRequest}/quotes/{quote}/interested', [AutoInsuranceController::class, 'markInterested'])
        ->name('insurance.auto.quotes.interested');
});
Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
    Route::post('/insurance/auto', [AutoInsuranceController::class, 'store'])->name('insurance.auto.request.store');
});
