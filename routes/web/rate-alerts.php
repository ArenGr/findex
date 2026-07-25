<?php

use App\Http\Controllers\RateAlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'banned'])->group(function () {
    Route::get('/alerts', [RateAlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts', [RateAlertController::class, 'store'])->name('alerts.store');
    Route::patch('/alerts/{rateAlert}/toggle', [RateAlertController::class, 'toggle'])->name('alerts.toggle');
    Route::delete('/alerts/{rateAlert}', [RateAlertController::class, 'destroy'])->name('alerts.destroy');
});
