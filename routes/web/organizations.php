<?php

use App\Http\Controllers\CompareController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/compare', [CompareController::class, 'show'])->name('organizations.compare');
Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
Route::get('/rates', [RateController::class, 'index'])->name('rates.index');

// Open to guests (see ReviewController::store) - 'banned' still
// blocks a signed-in banned user, it's simply a no-op for guests.
Route::middleware(['banned', 'throttle:reviews'])->group(function () {
    Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
