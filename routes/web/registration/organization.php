<?php

use App\Http\Controllers\Organization\Auth\RegisteredOrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('org')->name('org.')->middleware('guest:organization')->group(function () {
    Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredOrganizationController::class, 'store']);
});
