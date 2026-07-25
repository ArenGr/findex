<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriterRegistrationNotifiesAdminsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_when_a_writer_registers(): void
    {
        $adminOne = User::factory()->admin()->create(['name' => 'Admin One', 'email' => 'admin-one@example.com']);
        $adminTwo = User::factory()->admin()->create(['name' => 'Admin Two', 'email' => 'admin-two@example.com']);

        $response = $this->post('/en/writer/register', [
            'name' => 'New Test Writer',
            'email' => 'new-test-writer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'expertise' => 'Personal finance',
            'topics' => 'Budgeting, Currency markets',
        ]);

        $response->assertRedirect();

        // Login credentials live on `users`, not `writers` - see
        // RegisteredWriterController::store().
        $writerUser = User::where('email', 'new-test-writer@example.com')->first();
        $this->assertNotNull($writerUser);
        $writer = $writerUser->writer;
        $this->assertNotNull($writer);
        $this->assertFalse($writer->is_active);

        $this->assertSame(1, $adminOne->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $adminTwo->fresh()->unreadNotifications()->count());

        $notification = $adminOne->fresh()->unreadNotifications()->first();
        $this->assertSame('New writer awaiting approval', $notification->data['title']);

        // WriterResource routes admin pages by id, not by the model's own
        // slug-based getRouteKeyName() used for public routes - the review
        // link must be built from the id or it 404s once clicked.
        $reviewUrl = $notification->data['actions'][0]['url'];
        $this->assertStringContainsString("/admin/writers/{$writer->id}/edit", $reviewUrl);
        $this->assertStringNotContainsString("/writers/{$writer->slug}/edit", $reviewUrl);

        $this->actingAs($adminOne, 'admin');
        $this->get(parse_url($reviewUrl, PHP_URL_PATH))->assertOk();
    }
}
