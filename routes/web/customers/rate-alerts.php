<?php

use App\Http\Controllers\RateAlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'banned'])->group(function () {
    Route::get('/alerts', [RateAlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts', [RateAlertController::class, 'store'])->name('alerts.store');
    Route::patch('/alerts/{rateAlert}/toggle', [RateAlertController::class, 'toggle'])->name('alerts.toggle');
    Route::delete('/alerts/{rateAlert}', [RateAlertController::class, 'destroy'])->name('alerts.destroy');
    Route::post('/alerts/telegram/disconnect', [RateAlertController::class, 'disconnectTelegram'])->name('alerts.telegram.disconnect');
    Route::post('/alerts/viber/connect', [RateAlertController::class, 'connectViber'])->name('alerts.viber.connect');
    Route::post('/alerts/viber/disconnect', [RateAlertController::class, 'disconnectViber'])->name('alerts.viber.disconnect');
});
