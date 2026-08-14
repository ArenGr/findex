<?php

use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

/*
 * Embeddable widgets, deliberately outside the {locale} prefix.
 *
 * A host page in any language embeds the same URL and the widget shows numbers,
 * so a locale segment would only be a way for an embed code to go stale. Same
 * reasoning as the OAuth callbacks below it in web.php: these URLs are pasted
 * into other people's HTML and must never move.
 */
Route::get('/widgets/{type}', [WidgetController::class, 'show'])->name('widgets.show');
