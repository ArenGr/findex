<?php

use App\Support\Features;
use Illuminate\Support\Facades\Route;

// Versioned from the first line rather than "v1 later".
Route::prefix('v1')->name('api.v1.')->middleware('feature:'.Features::PUBLIC_API)
    ->group(base_path('routes/api/v1.php'));
