<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannedAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private function organizationUser(array $userOverrides = []): User
    {
        $organization = Organization::create([
            'name' => 'Banned Test Org',
            'slug' => 'banned-test-org-'.uniqid(),
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
        ]);

        return User::factory()->organization($organization)->create($userOverrides);
    }

    private function writerUser(array $userOverrides = []): User
    {
        return User::factory()->writer(Writer::factory()->create())->create($userOverrides);
    }

    public function test_a_banned_organization_cannot_log_in(): void
    {
        $user = $this->organizationUser(['email' => 'banned-org@example.com', 'banned_at' => now()]);

        $response = $this->post(route('org.login', ['locale' => 'en']), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('organization');
    }

    public function test_a_banned_writer_cannot_log_in(): void
    {
        $user = $this->writerUser(['email' => 'banned-writer@example.com', 'banned_at' => now()]);

        $response = $this->post(route('writer.login', ['locale' => 'en']), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('writer');
    }

    public function test_an_organization_banned_mid_session_is_cut_off_on_its_next_request(): void
    {
        $user = $this->organizationUser();

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.index', ['locale' => 'en']))
            ->assertOk();

        $user->ban();

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.index', ['locale' => 'en']))
            ->assertRedirect(route('org.login', ['locale' => 'en']));

        $this->assertGuest('organization');
    }

    public function test_a_writer_banned_mid_session_is_cut_off_on_its_next_request(): void
    {
        $user = $this->writerUser();

        $this->actingAs($user, 'writer')
            ->get(route('writer.dashboard.index', ['locale' => 'en']))
            ->assertOk();

        $user->ban();

        $this->actingAs($user, 'writer')
            ->get(route('writer.dashboard.index', ['locale' => 'en']))
            ->assertRedirect(route('writer.login', ['locale' => 'en']));

        $this->assertGuest('writer');
    }

    public function test_an_unbanned_organization_is_unaffected(): void
    {
        $user = $this->organizationUser();

        $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.index', ['locale' => 'en']))
            ->assertOk();
    }
}
