<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    use GeneratesUniqueSlug;

    public function index(): View
    {
        $writer = Auth::guard('writer')->user()->writer;

        return view('writer.dashboard.articles.index', [
            'articles' => $writer->articles()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('writer.dashboard.articles.create', [
            'languages' => config('localization.available'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $writer = Auth::guard('writer')->user()->writer;
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['title'], Article::class);
        $data['status'] = Article::STATUS_DRAFT;

        $writer->articles()->create($data);

        return redirect()->route('writer.dashboard.articles.index')->with('status', 'article-created');
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated writer's own articles is also what
     * enforces that a writer can only edit their own.
     */
    public function edit(string $locale, string $article): View
    {
        $writer = Auth::guard('writer')->user()->writer;
        $article = $writer->articles()->findOrFail($article);
        abort_unless($article->isDraft(), 403);

        return view('writer.dashboard.articles.edit', [
            'article' => $article,
            'languages' => config('localization.available'),
        ]);
    }

    public function update(Request $request, string $locale, string $article): RedirectResponse
    {
        $writer = Auth::guard('writer')->user()->writer;
        $article = $writer->articles()->findOrFail($article);
        abort_unless($article->isDraft(), 403);

        $article->update($this->validated($request));

        return redirect()->route('writer.dashboard.articles.index')->with('status', 'article-updated');
    }

    public function destroy(string $locale, string $article): RedirectResponse
    {
        $writer = Auth::guard('writer')->user()->writer;
        $writer->articles()->findOrFail($article)->delete();

        return redirect()->route('writer.dashboard.articles.index')->with('status', 'article-deleted');
    }

    /**
     * A dedicated action rather than a status field on the update form, so
     * a writer can't tamper with status through the edit form - the only
     * way an article becomes 'submitted' is through this explicit endpoint.
     */
    public function submit(string $locale, string $article): RedirectResponse
    {
        $writer = Auth::guard('writer')->user()->writer;
        $article = $writer->articles()->findOrFail($article);
        abort_unless($article->isDraft(), 403);

        $article->update(['status' => Article::STATUS_SUBMITTED]);

        return redirect()->route('writer.dashboard.articles.index')->with('status', 'article-submitted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'language' => ['required', Rule::in(array_keys(config('localization.available')))],
            'body' => ['required', 'string'],
        ]);
    }
}
