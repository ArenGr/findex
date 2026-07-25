<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app has four guards (web/organization/writer/admin) with custom
 * guest-redirect logic in bootstrap/app.php specifically so an
 * unauthenticated hit on one area doesn't redirect to another area's login
 * form. That logic is easy to silently break (e.g. while touching
 * middleware config) with nothing else catching it, since a wrong redirect
 * still "works" - it just sends the user to the wrong login page.
 *
 * All four guards now authenticate against the same `users` table (see
 * App\Enums\UserRole) rather than separate tables/models - guard membership
 * alone no longer structurally proves role the way it did before. What
 * actually keeps a customer/organization/writer/admin session out of the
 * wrong area now is role-scoped login (the customer/organization/writer
 * AuthenticatedSessionControllers' Auth::attempt() calls),
 * User::canAccessPanel() for the Filament-driven 'admin' guard, and the
 * EnsureUserRole middleware for the 'organization'/'writer' guards' dashboard
 * routes - see test_customer_role_forced_onto_organization_guard_is_still_blocked_from_dashboard
 * below, which proves that last layer actually works rather than just
 * trusting the guard name.
 */
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

        // The 'web' guard's user simply isn't authenticated on the
        // 'organization' guard, so this must still bounce to org login.
        $this->actingAs($user)->get('/en/org/dashboard')->assertRedirect('/en/org/login');
    }

    public function test_organization_cannot_use_customer_guarded_routes(): void
    {
        $user = $this->organizationUser();

        // Deliberately not actingAs(): that helper also calls
        // Auth::shouldUse($guard), which changes the *default* guard for
        // the rest of the test - meaning /en/alerts's unguarded `auth`
        // middleware (default guard, i.e. 'web') would then authenticate
        // as the organization too, masking the exact guard leak this test
        // exists to catch. Setting the 'organization' guard's user
        // directly, without touching the default, mirrors a real session
        // where only the org is logged in.
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

        // Deliberately not actingAs() - see test_organization_cannot_use_customer_guarded_routes
        // for why: it would also change the default guard, masking the
        // exact guard leak this test exists to catch.
        $this->app['auth']->guard('writer')->setUser($user);

        $this->get('/en/alerts')->assertRedirect('/en/login');
    }

    public function test_writer_can_access_its_own_dashboard(): void
    {
        $user = $this->writerUser();

        $this->actingAs($user, 'writer')->get('/en/writer/dashboard')->assertOk();
    }

    /**
     * Forces a customer-role User directly onto the 'writer' guard,
     * bypassing role-scoped login entirely - proves EnsureUserRole (not
     * just guard membership) is what blocks the dashboard, mirroring
     * test_customer_role_forced_onto_organization_guard_is_still_blocked_from_dashboard.
     */
    public function test_customer_role_forced_onto_writer_guard_is_still_blocked_from_dashboard(): void
    {
        $customer = User::factory()->create();

        $this->app['auth']->guard('writer')->setUser($customer);

        $this->get('/en/writer/dashboard')->assertForbidden();
    }

    /**
     * Forces a customer-role User directly onto the 'organization' guard,
     * bypassing role-scoped login entirely - this can't happen through any
     * real login path, but proves EnsureUserRole (not just guard
     * membership) is what blocks the dashboard now that guards share one
     * table.
     */
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
