<?php

use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

// Public detail page for articles written in-house by Findex's writers, once
// approved by an admin (see App\Filament\Resources\Articles\ArticleResource).
// No index/browse page - articles are discovered via the home page's news
// section (resources/views/components/news-section.blade.php).
Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
