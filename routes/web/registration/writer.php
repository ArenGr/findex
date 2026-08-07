<?php

use App\Http\Controllers\Writer\Auth\RegisteredWriterController;
use Illuminate\Support\Facades\Route;

Route::prefix('writer')->name('writer.')->middleware('guest:writer')->group(function () {
    Route::get('/register', [RegisteredWriterController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredWriterController::class, 'store'])->middleware('throttle:register');
});
