<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function organizationUser(string $type = 'bank'): User
    {
        $organization = Organization::create([
            'name' => 'Guard Test Bank',
            'slug' => 'guard-test-bank-'.uniqid(),
            'type' => $type,
            'country_code' => 'AM',
            'is_active' => true,
        ]);

        return User::factory()->organization($organization)->create();
    }

    private function writerUser(): User
    {
        $writer = Writer::create([
            'name' => 'Guard Test Writer',
            'slug' => 'guard-test-writer-'.uniqid(),
            'is_active' => true,
        ]);

        return User::factory()->writer($writer)->create();
    }

    public function test_guest_hitting_customer_route_is_sent_to_customer_login(): void
    {
        $this->get('/en/alerts')->assertRedirect('/en/login');
    }

    public function test_guest_hitting_org_dashboard_is_sent_to_org_login_not_customer_login(): void
    {
        $this->get('/en/org/dashboard')->assertRedirect('/en/org/login');
    }

    public function test_guest_hitting_writer_dashboard_is_sent_to_writer_login_not_customer_or_org_login(): void
    {
        $this->get('/en/writer/dashboard')->assertRedirect('/en/writer/login');
    }

    public function test_customer_cannot_use_organization_guarded_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/org/dashboard')->assertRedirect('/en/org/login');
    }

    public function test_organization_cannot_use_customer_guarded_routes(): void
    {
        $user = $this->organizationUser();

        $this->app['auth']->guard('organization')->setUser($user);

        $this->get('/en/alerts')->assertRedirect('/en/login');
    }

    public function test_organization_can_access_its_own_dashboard(): void
    {
        $user = $this->organizationUser();

        $this->actingAs($user, 'organization')->get('/en/org/dashboard')->assertOk();
    }

    public function test_customer_cannot_use_writer_guarded_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/writer/dashboard')->assertRedirect('/en/writer/login');
    }

    public function test_writer_cannot_use_customer_guarded_routes(): void
    {
        $user = $this->writerUser();

        $this->app['auth']->guard('writer')->setUser($user);

        $this->get('/en/alerts')->assertRedirect('/en/login');
    }

    public function test_writer_can_access_its_own_dashboard(): void
    {
        $user = $this->writerUser();

        $this->actingAs($user, 'writer')->get('/en/writer/dashboard')->assertOk();
    }

    public function test_customer_role_forced_onto_writer_guard_is_still_blocked_from_dashboard(): void
    {
        $customer = User::factory()->create();

        $this->app['auth']->guard('writer')->setUser($customer);

        $this->get('/en/writer/dashboard')->assertForbidden();
    }

    public function test_customer_role_forced_onto_organization_guard_is_still_blocked_from_dashboard(): void
    {
        $customer = User::factory()->create();

        $this->app['auth']->guard('organization')->setUser($customer);

        $this->get('/en/org/dashboard')->assertForbidden();
    }

    public function test_customer_can_access_its_own_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/alerts')->assertOk();
    }

    public function test_banned_customer_is_logged_out_and_blocked(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $response = $this->actingAs($user)->get('/en/alerts');

        $response->assertRedirect();
        $this->assertGuest();
    }
}
