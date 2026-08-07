<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register/customer', [RegisteredUserController::class, 'create'])->name('register.customer');
    Route::post('/register/customer', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
});
