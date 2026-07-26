<?php

use App\Http\Controllers\Organization\CurrencyRateController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;

// Currency rate management only applies to bank/exchange organizations
// (see Organization::RATES_TYPES).
Route::middleware('org.type:'.implode(',', Organization::RATES_TYPES))->group(function () {
    Route::get('/rates', [CurrencyRateController::class, 'index'])->name('rates.index');
    Route::get('/rates/create', [CurrencyRateController::class, 'create'])->name('rates.create');
    Route::post('/rates', [CurrencyRateController::class, 'store'])->name('rates.store');
    Route::get('/rates/{rate}/edit', [CurrencyRateController::class, 'edit'])->name('rates.edit');
    Route::put('/rates/{rate}', [CurrencyRateController::class, 'update'])->name('rates.update');
});
