<?php

use App\Enums\UserRole;
use App\Http\Controllers\Writer\DashboardController as WriterDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('writer')->name('writer.')->middleware('auth:writer')->group(function () {
    Route::middleware('role:writer,'.UserRole::WRITER->value)->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [WriterDashboardController::class, 'index'])->name('index');
    });
});
