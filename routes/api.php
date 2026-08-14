<?php

use Illuminate\Support\Facades\Route;

/*
 * Versioned from the first line rather than "v1 later".
 *
 * The public contract is deliberately its own layer: every response below is
 * built by a Resource in app/Http/Resources/Api/V1, never by handing an Eloquent
 * model to the serializer. A column rename should be a private matter, and it
 * stops being one the moment a customer's integration depends on it.
 *
 * No locale segment: these are numbers, and a caller asking for USD rates does
 * not need them in Armenian.
 */
Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api/v1.php'));
