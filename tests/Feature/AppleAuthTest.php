<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Mirrors GoogleAuthTest - same account-preemption fix applies to Apple
 * (see AppleAuthController::callback), plus one Apple-specific case: Apple
 * only ever sends a name on the account's very first authorization, never
 * again after, so a re-login with no name must not null out what's stored.
 */
class AppleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The redirect()/callback() routes 404 unless Apple is configured
        // (see AppleAuthController) - real credentials aren't set up yet,
        // so simulate "configured" for these tests, which are exercising
        // the callback logic itself, not that gate. See
        // test_apple_routes_404_when_not_configured for the gate's own test.
        config(['services.apple.client_id' => 'test-client-id']);
    }

    private function fakeAppleUser(string $id, string $email, ?string $name = 'Real Owner'): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);

        Socialite::shouldReceive('driver')
            ->with('apple')
            ->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);
    }

    public function test_first_apple_link_to_an_existing_account_revokes_its_prior_password(): void
    {
        // Simulates an attacker pre-registering the victim's email with a
        // password only the attacker knows, before the real owner ever
        // signs in with Apple.
        $squatted = User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('attacker-known-password'),
            'email_verified_at' => null,
        ]);

        $this->fakeAppleUser('apple-id-1', 'victim@example.com');

        $this->post('/auth/apple/callback')->assertRedirect();

        $squatted->refresh();

        $this->assertFalse(
            Hash::check('attacker-known-password', $squatted->password),
            'The pre-existing password must stop working once Apple links to this account.'
        );
        $this->assertSame('apple-id-1', $squatted->apple_id);
        $this->assertNotNull($squatted->email_verified_at);
        $this->assertAuthenticatedAs($squatted);
    }

    public function test_subsequent_apple_logins_do_not_keep_rotating_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'returning@example.com',
            'apple_id' => 'apple-id-2',
        ]);
        $passwordHash = $user->password;

        $this->fakeAppleUser('apple-id-2', 'returning@example.com');

        $this->post('/auth/apple/callback')->assertRedirect();

        $this->assertSame($passwordHash, $user->fresh()->password);
    }

    public function test_apple_login_creates_a_new_verified_user_when_no_account_exists(): void
    {
        $this->fakeAppleUser('apple-id-3', 'brand-new@example.com');

        $this->post('/auth/apple/callback')->assertRedirect();

        $user = User::where('email', 'brand-new@example.com')->firstOrFail();

        $this->assertSame('apple-id-3', $user->apple_id);
        $this->assertSame('Real Owner', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_apple_relogin_without_a_name_does_not_null_out_the_stored_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Real Owner',
            'email' => 'returning-no-name@example.com',
            'apple_id' => 'apple-id-4',
        ]);

        // Apple's normal behavior after the first authorization - name is
        // simply absent, never re-sent.
        $this->fakeAppleUser('apple-id-4', 'returning-no-name@example.com', name: null);

        $this->post('/auth/apple/callback')->assertRedirect();

        $this->assertSame('Real Owner', $user->fresh()->name);
    }

    public function test_apple_login_with_no_name_falls_back_to_the_email_local_part_for_a_new_user(): void
    {
        $this->fakeAppleUser('apple-id-5', 'no-name-provided@example.com', name: null);

        $this->post('/auth/apple/callback')->assertRedirect();

        $user = User::where('email', 'no-name-provided@example.com')->firstOrFail();

        $this->assertSame('no-name-provided', $user->name);
    }

    public function test_apple_routes_404_when_not_configured(): void
    {
        // Real credentials aren't purchased/configured yet - the button is
        // hidden (see auth/register.blade.php, auth/login.blade.php) and
        // the routes themselves must refuse to run rather than throwing
        // while Socialite tries to sign a JWT with an empty private key.
        config(['services.apple.client_id' => null]);

        $this->get('/auth/apple/redirect')->assertNotFound();
        $this->post('/auth/apple/callback')->assertNotFound();
    }
}
