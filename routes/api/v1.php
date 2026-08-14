<?php

use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\RateController;
use Illuminate\Support\Facades\Route;

Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');

Route::get('/rates', [RateController::class, 'index'])->name('rates.index');
Route::get('/rates/best', [MarketController::class, 'best'])->name('rates.best');
Route::get('/rates/average', [MarketController::class, 'average'])->name('rates.average');
Route::get('/rates/history', [MarketController::class, 'history'])->name('rates.history');

Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('/organizations/{organization}/rates', [OrganizationController::class, 'rates'])->name('organizations.rates');
