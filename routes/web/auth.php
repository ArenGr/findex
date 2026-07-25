<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Organization\Auth\AuthenticatedSessionController as OrganizationAuthenticatedSessionController;
use App\Http\Controllers\Organization\Auth\RegisteredOrganizationController;
use App\Http\Controllers\Writer\Auth\AuthenticatedSessionController as WriterAuthenticatedSessionController;
use App\Http\Controllers\Writer\Auth\RegisteredWriterController;
use Illuminate\Support\Facades\Route;

Route::get('/register', function () {
    return view('auth.register-choice');
})->name('register');

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('guest')->group(function () {
    Route::get('/register/customer', [RegisteredUserController::class, 'create'])->name('register.customer');
    Route::post('/register/customer', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', 'banned'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForCustomer'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::prefix('org')->name('org.')->group(function () {
    Route::middleware('guest:organization')->group(function () {
        Route::get('/login', [OrganizationAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [OrganizationAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

        Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredOrganizationController::class, 'store']);
    });

    Route::middleware('auth:organization')->group(function () {
        Route::post('/logout', [OrganizationAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForOrganization'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Dashboard routes are split out but must stay nested here, inside
        // both prefix('org')->name('org.') and this auth:organization group.
        require __DIR__ . '/organization-dashboard.php';
    });
});

Route::prefix('writer')->name('writer.')->group(function () {
    Route::middleware('guest:writer')->group(function () {
        Route::get('/login', [WriterAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [WriterAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

        Route::get('/register', [RegisteredWriterController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredWriterController::class, 'store']);
    });

    Route::middleware('auth:writer')->group(function () {
        Route::post('/logout', [WriterAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForWriter'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Dashboard routes are split out but must stay nested here, inside
        // both prefix('writer')->name('writer.') and this auth:writer group.
        require __DIR__ . '/writer-dashboard.php';
    });
});
