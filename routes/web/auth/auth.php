<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Organization\Auth\AuthenticatedSessionController as OrganizationAuthenticatedSessionController;
use App\Http\Controllers\Writer\Auth\AuthenticatedSessionController as WriterAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// Entry point that lets a guest pick which account type to register as -
// see registration/customer.php, registration/organization.php and
// registration/writer.php for the actual registration flows.
Route::get('/register', function () {
    return view('auth.register-choice');
})->name('register');

// Clicking the link from a verification email is guard-agnostic (see
// VerifyEmailController::verify) - only *requesting* a new one differs per
// account type, handled by each guard's own group below.
//
// The 6/minute default below is overridable per-environment via
// RATE_LIMIT_VERIFY_PER_MINUTE (see config/rate-limits.php) - prod's .env
// can raise this during the pre-launch testing phase without changing the
// default or the tests that assert it. Read via config() rather than a
// bare env() - the deploy pipeline runs `php artisan config:cache`, after
// which .env is never loaded again, so a raw env() call here would keep
// returning its hardcoded default regardless of what .env says.
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

    // role:organization,<value> kept off /logout (see organizations/dashboard.php,
    // which shares this guard but not this prefix/middleware group) on
    // purpose - a wrong-role session on this guard (shouldn't happen, see
    // EnsureUserRole's docblock) should still be able to log itself out
    // rather than getting stuck 403'd.
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
