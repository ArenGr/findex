<?php

use App\Http\Controllers\RateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/rates', [RateController::class, 'index'])->name('rates.index');
