<?php

use App\Enums\UserRole;
use App\Http\Controllers\Writer\ArticleController;
use App\Http\Controllers\Writer\DashboardController as WriterDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('writer')->name('writer.')->middleware('auth:writer')->group(function () {
    Route::middleware('role:writer,'.UserRole::WRITER->value)->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [WriterDashboardController::class, 'index'])->name('index');

        Route::prefix('articles')->name('articles.')->group(function () {
            Route::get('/', [ArticleController::class, 'index'])->name('index');
            Route::get('/create', [ArticleController::class, 'create'])->name('create');
            Route::post('/', [ArticleController::class, 'store'])->name('store');
            Route::get('/{article}/edit', [ArticleController::class, 'edit'])->name('edit');
            Route::put('/{article}', [ArticleController::class, 'update'])->name('update');
            Route::delete('/{article}', [ArticleController::class, 'destroy'])->name('destroy');
            Route::post('/{article}/submit', [ArticleController::class, 'submit'])->name('submit');
        });
    });
});
