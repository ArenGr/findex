<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationRegistrationNotifiesAdminsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_when_an_organization_registers(): void
    {
        $adminOne = User::factory()->admin()->create(['name' => 'Admin One', 'email' => 'admin-one@example.com']);
        $adminTwo = User::factory()->admin()->create(['name' => 'Admin Two', 'email' => 'admin-two@example.com']);

        $response = $this->post('/en/org/register', [
            'name' => 'New Test Bank',
            'email' => 'new-test-bank@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'type' => 'bank',
        ]);

        $response->assertRedirect();

        // Login credentials now live on `users`, not `organizations` - see
        // RegisteredOrganizationController::store().
        $organizationUser = User::where('email', 'new-test-bank@example.com')->first();
        $this->assertNotNull($organizationUser);
        $organization = $organizationUser->organization;
        $this->assertNotNull($organization);
        $this->assertFalse($organization->is_active);

        $this->assertSame(1, $adminOne->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $adminTwo->fresh()->unreadNotifications()->count());

        $notification = $adminOne->fresh()->unreadNotifications()->first();
        $this->assertSame('New organization awaiting approval', $notification->data['title']);

        // OrganizationResource routes admin pages by id, not by the model's
        // own slug-based getRouteKeyName() used for public routes - the
        // review link must be built from the id or it 404s once clicked.
        $reviewUrl = $notification->data['actions'][0]['url'];
        $this->assertStringContainsString("/admin/organizations/{$organization->id}/edit", $reviewUrl);
        $this->assertStringNotContainsString("/organizations/{$organization->slug}/edit", $reviewUrl);

        $this->actingAs($adminOne, 'admin');
        $this->get(parse_url($reviewUrl, PHP_URL_PATH))->assertOk();
    }

    public function test_admin_notification_bell_shows_the_unread_count(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Test Admin', 'email' => 'test-admin@example.com']);
        $this->actingAs($admin, 'admin');

        \Filament\Notifications\Notification::make()
            ->title('New organization awaiting approval')
            ->body('Pending Bank just registered.')
            ->sendToDatabase($admin);
        \Filament\Notifications\Notification::make()
            ->title('Another organization awaiting approval')
            ->body('Another Bank just registered.')
            ->sendToDatabase($admin);

        $component = Livewire::test(DatabaseNotifications::class);

        $this->assertSame(2, $component->instance()->getUnreadNotificationsCount());
    }
}
