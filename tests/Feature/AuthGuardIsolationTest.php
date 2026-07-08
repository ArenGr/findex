<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app has three separate auth guards (customer/organization/admin) with
 * custom guest-redirect logic in bootstrap/app.php specifically so an
 * unauthenticated hit on one area doesn't redirect to another area's login
 * form. That logic is easy to silently break (e.g. while touching
 * middleware config) with nothing else catching it, since a wrong redirect
 * still "works" - it just sends the user to the wrong login page.
 */
class AuthGuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_hitting_customer_route_is_sent_to_customer_login(): void
    {
        $this->get('/en/alerts')->assertRedirect('/en/login');
    }

    public function test_guest_hitting_org_dashboard_is_sent_to_org_login_not_customer_login(): void
    {
        $this->get('/en/org/dashboard')->assertRedirect('/en/org/login');
    }

    public function test_customer_cannot_use_organization_guarded_routes(): void
    {
        $user = User::factory()->create();

        // The 'web' guard's user simply isn't authenticated on the
        // 'organization' guard, so this must still bounce to org login.
        $this->actingAs($user)->get('/en/org/dashboard')->assertRedirect('/en/org/login');
    }

    public function test_organization_cannot_use_customer_guarded_routes(): void
    {
        $organization = Organization::create([
            'name' => 'Guard Test Bank',
            'slug' => 'guard-test-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
            'email' => 'guard-test@example.com',
            'password' => 'password',
        ]);

        // Deliberately not actingAs(): that helper also calls
        // Auth::shouldUse($guard), which changes the *default* guard for
        // the rest of the test - meaning /en/alerts's unguarded `auth`
        // middleware (default guard, i.e. 'web') would then authenticate
        // as the organization too, masking the exact guard leak this test
        // exists to catch. Setting the 'organization' guard's user
        // directly, without touching the default, mirrors a real session
        // where only the org is logged in.
        $this->app['auth']->guard('organization')->setUser($organization);

        $this->get('/en/alerts')->assertRedirect('/en/login');
    }

    public function test_organization_can_access_its_own_dashboard(): void
    {
        $organization = Organization::create([
            'name' => 'Guard Test Bank 2',
            'slug' => 'guard-test-bank-2',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
            'email' => 'guard-test-2@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($organization, 'organization')->get('/en/org/dashboard')->assertOk();
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
