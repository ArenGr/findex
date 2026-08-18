<?php

use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

/*
 * Embeddable widgets, deliberately outside the {locale} prefix.
 */
Route::get('/widgets/{type}', [WidgetController::class, 'show'])->name('widgets.show');
