<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    private function writerUser(): array
    {
        $writer = Writer::create([
            'name' => 'Article Test Writer',
            'slug' => 'article-test-writer-'.uniqid(),
            'is_active' => true,
        ]);
        $user = User::factory()->writer($writer)->create();

        return [$writer, $user];
    }

    public function test_writer_can_create_a_draft(): void
    {
        [, $user] = $this->writerUser();

        $this->actingAs($user, 'writer')->post(route('writer.dashboard.articles.store', ['locale' => 'en']), [
            'title' => 'How exchange rates work',
            'language' => 'en',
            'body' => 'A long explanation of exchange rates.',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'title' => 'How exchange rates work',
            'status' => Article::STATUS_DRAFT,
        ]);
    }

    public function test_writer_can_edit_own_draft(): void
    {
        [$writer, $user] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Original title', 'slug' => 'original-title', 'language' => 'en', 'body' => 'Original body.',
            'status' => Article::STATUS_DRAFT,
        ]);

        $this->actingAs($user, 'writer')->put(route('writer.dashboard.articles.update', ['locale' => 'en', 'article' => $article->id]), [
            'title' => 'Updated title',
            'language' => 'en',
            'body' => 'Updated body.',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => 'Updated title']);
    }

    public function test_writer_cannot_edit_another_writers_article(): void
    {
        [$writer] = $this->writerUser();
        [, $otherUser] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Mine', 'slug' => 'mine-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_DRAFT,
        ]);

        $this->actingAs($otherUser, 'writer')
            ->get(route('writer.dashboard.articles.edit', ['locale' => 'en', 'article' => $article->id]))
            ->assertNotFound();
    }

    public function test_writer_can_delete_own_article(): void
    {
        [$writer, $user] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'To delete', 'slug' => 'to-delete-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_DRAFT,
        ]);

        $this->actingAs($user, 'writer')
            ->delete(route('writer.dashboard.articles.destroy', ['locale' => 'en', 'article' => $article->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_writer_can_submit_a_draft(): void
    {
        [$writer, $user] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Ready to submit', 'slug' => 'ready-to-submit-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_DRAFT,
        ]);

        $this->actingAs($user, 'writer')
            ->post(route('writer.dashboard.articles.submit', ['locale' => 'en', 'article' => $article->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => Article::STATUS_SUBMITTED]);
    }

    public function test_submitted_article_cannot_be_edited(): void
    {
        [$writer, $user] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Already submitted', 'slug' => 'already-submitted-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_SUBMITTED,
        ]);

        $this->actingAs($user, 'writer')
            ->get(route('writer.dashboard.articles.edit', ['locale' => 'en', 'article' => $article->id]))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_article_routes(): void
    {
        $this->get('/en/writer/dashboard/articles')->assertRedirect('/en/writer/login');
    }

    public function test_customer_cannot_access_article_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/writer/dashboard/articles')->assertRedirect('/en/writer/login');
    }

    public function test_create_requires_title_language_and_body(): void
    {
        [, $user] = $this->writerUser();

        $this->actingAs($user, 'writer')
            ->post(route('writer.dashboard.articles.store', ['locale' => 'en']), [])
            ->assertSessionHasErrors(['title', 'language', 'body']);
    }

    public function test_writer_can_edit_and_resubmit_a_rejected_article(): void
    {
        [$writer, $user] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Rejected once', 'slug' => 'rejected-once-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_REJECTED, 'rejection_reason' => 'Needs sources.',
        ]);

        $this->actingAs($user, 'writer')
            ->put(route('writer.dashboard.articles.update', ['locale' => 'en', 'article' => $article->id]), [
                'title' => 'Rejected once, now fixed',
                'language' => 'en',
                'body' => 'Updated body with sources.',
            ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => 'Rejected once, now fixed']);

        $this->actingAs($user, 'writer')
            ->post(route('writer.dashboard.articles.submit', ['locale' => 'en', 'article' => $article->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => Article::STATUS_SUBMITTED]);
    }

    public function test_home_news_section_shows_only_approved_articles(): void
    {
        // Rendered in isolation via $this->blade() rather than a full GET to
        // '/' - the home page also renders <x-top-rated-organizations />,
        // whose HAVING-clause query is MySQL-only and errors under the
        // SQLite test database, unrelated to anything article-related here.
        // Mirrors what the 'setlocale' middleware does for a real request -
        // route('articles.show', ...) needs a default {locale} to fill in
        // since $this->blade() doesn't dispatch through the router.
        app()->setLocale('en');
        URL::defaults(['locale' => 'en']);

        [$writer] = $this->writerUser();
        $approved = $writer->articles()->create([
            'title' => 'Visible article', 'slug' => 'visible-article-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'excerpt' => 'Teaser.', 'status' => Article::STATUS_APPROVED, 'published_at' => now(),
        ]);
        $writer->articles()->create([
            'title' => 'Hidden draft', 'slug' => 'hidden-draft-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_DRAFT,
        ]);
        $writer->articles()->create([
            'title' => 'Hidden submitted', 'slug' => 'hidden-submitted-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_SUBMITTED,
        ]);

        $html = $this->blade('<x-news-section />');

        $html->assertSee($approved->title);
        $html->assertDontSee('Hidden draft');
        $html->assertDontSee('Hidden submitted');
    }

    public function test_article_links_use_the_slug_not_the_id(): void
    {
        URL::defaults(['locale' => 'en']);

        [$writer] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Readable URL', 'slug' => 'readable-url-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_APPROVED, 'published_at' => now(),
        ]);

        $url = route('articles.show', $article);

        $this->assertStringContainsString($article->slug, $url);
        $this->assertStringNotContainsString('/'.$article->id, $url);
    }

    public function test_public_show_404s_for_a_non_approved_article(): void
    {
        [$writer] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'Not yet live', 'slug' => 'not-yet-live-'.uniqid(), 'language' => 'en', 'body' => 'Body.',
            'status' => Article::STATUS_SUBMITTED,
        ]);

        $this->get(route('articles.show', ['locale' => 'en', 'article' => $article->slug]))
            ->assertNotFound();
    }

    public function test_public_show_renders_an_approved_article(): void
    {
        [$writer] = $this->writerUser();
        $article = $writer->articles()->create([
            'title' => 'A published guide', 'slug' => 'a-published-guide-'.uniqid(), 'language' => 'en',
            'body' => "First paragraph.\n\nSecond paragraph.",
            'status' => Article::STATUS_APPROVED, 'published_at' => now(),
        ]);

        $this->get(route('articles.show', ['locale' => 'en', 'article' => $article->slug]))
            ->assertOk()
            ->assertSee($article->title);
    }
}
