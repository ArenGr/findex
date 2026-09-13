<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Organization\Auth\AuthenticatedSessionController as OrganizationAuthenticatedSessionController;
use App\Http\Controllers\Writer\Auth\AuthenticatedSessionController as WriterAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/register', function () {
    return view('auth.register-choice');
})->name('register');

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:'.config('rate-limits.verify_per_minute').',1'])
    ->name('verification.verify');

// Customer
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', 'banned'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForCustomer'])
        ->middleware('throttle:'.config('rate-limits.verify_per_minute').',1')
        ->name('verification.send');
});

// Organization
Route::prefix('org')->name('org.')->group(function () {
    Route::middleware('guest:organization')->group(function () {
        Route::get('/login', [OrganizationAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [OrganizationAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth:organization')->group(function () {
        Route::post('/logout', [OrganizationAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForOrganization'])
            ->middleware('throttle:'.config('rate-limits.verify_per_minute').',1')
            ->name('verification.send');
    });
});

// Writer
Route::prefix('writer')->name('writer.')->group(function () {
    Route::middleware('guest:writer')->group(function () {
        Route::get('/login', [WriterAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [WriterAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth:writer')->group(function () {
        Route::post('/logout', [WriterAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForWriter'])
            ->middleware('throttle:'.config('rate-limits.verify_per_minute').',1')
            ->name('verification.send');
    });
});
