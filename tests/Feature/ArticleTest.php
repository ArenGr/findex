<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
