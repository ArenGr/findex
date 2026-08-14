<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiKeyController;
use Illuminate\Support\Facades\Route;

// Public: what the API is, what it costs, how to call it. Kept out of the main
// navigation - someone here to check a rate should never meet it.
Route::get('/api', [ApiDocsController::class, 'index'])->name('api.docs');

Route::middleware(['auth', 'banned'])->group(function () {
    Route::get('/api/keys', [ApiKeyController::class, 'index'])->name('api.keys.index');
    Route::post('/api/keys', [ApiKeyController::class, 'store'])->name('api.keys.store');
    Route::delete('/api/keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api.keys.destroy');
});
