<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoped through
     * published() so a draft/submitted/rejected slug 404s for guests.
     */
    public function show(string $locale, string $article): View
    {
        $article = Article::published()->where('slug', $article)->firstOrFail();
        $article->load(['writer', 'reviewedBy']);

        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->where('language', $article->language)
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('articles.show', [
            'article' => $article,
            'related' => $related,
        ]);
    }
}
