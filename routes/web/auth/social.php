<?php

use App\Http\Controllers\Auth\AppleAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Google
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
    // Apple
    Route::get('/auth/apple/redirect', [AppleAuthController::class, 'redirect'])->name('auth.apple.redirect');
    Route::post('/auth/apple/callback', [AppleAuthController::class, 'callback'])->name('auth.apple.callback');
});
