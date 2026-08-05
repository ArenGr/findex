<?php

use App\Http\Controllers\EmailPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');
if (! app()->isProduction()) {
    Route::get('/style-guide', function () {
        return view('style-guide');
    })->name('style-guide');

    Route::get('/email-preview', [EmailPreviewController::class, 'index'])->name('email-preview.index');
    Route::get('/email-preview/{template}', [EmailPreviewController::class, 'show'])->name('email-preview.show');
}
Route::get('/offers', function () {
    return view('offers');
})->name('offers');
Route::get('/about', function () {
    return view('about');
})->name('about');
Route::get('/team', function () {
    return view('team');
})->name('team');
Route::get('/careers', function () {
    return view('careers');
})->name('careers');
Route::get('/news', function () {
    return view('company-news');
})->name('company.news');
Route::get('/help', function () {
    return view('help');
})->name('help');
Route::get('/faq', function () {
    return view('faq');
})->name('faq');
Route::get('/contact', function () {
    return view('contact');
})->name('contact');
Route::get('/terms', function () {
    return view('legal.terms');
})->name('terms');
Route::get('/privacy', function () {
    return view('legal.privacy');
})->name('privacy');
Route::get('/cookies', function () {
    return view('legal.cookies');
})->name('cookies');
