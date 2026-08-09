<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Regression coverage for the account-preemption fix: linking Google to an
 * existing password account by email alone previously let an attacker who
 * pre-registered the victim's email keep using their own password on the
 * account after the real owner signed in with Google (see
 * GoogleAuthController::callback).
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name = 'Real Owner'): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $id,
            'nickname' => null,
            'name' => $name,
            'email' => $email,
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);
    }

    public function test_first_google_link_to_an_existing_account_revokes_its_prior_password(): void
    {
        // Simulates an attacker pre-registering the victim's email with a
        // password only the attacker knows, before the real owner ever
        // signs in with Google.
        $squatted = User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('attacker-known-password'),
            'email_verified_at' => null,
        ]);

        $this->fakeGoogleUser('google-id-1', 'victim@example.com');

        $this->get('/auth/google/callback')->assertRedirect();

        $squatted->refresh();

        $this->assertFalse(
            Hash::check('attacker-known-password', $squatted->password),
            'The pre-existing password must stop working once Google links to this account.'
        );
        $this->assertSame('google-id-1', $squatted->google_id);
        $this->assertNotNull($squatted->email_verified_at);
        $this->assertAuthenticatedAs($squatted);
    }

    public function test_subsequent_google_logins_do_not_keep_rotating_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'returning@example.com',
            'google_id' => 'google-id-2',
        ]);
        $passwordHash = $user->password;

        $this->fakeGoogleUser('google-id-2', 'returning@example.com');

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertSame($passwordHash, $user->fresh()->password);
    }

    public function test_google_login_creates_a_new_verified_user_when_no_account_exists(): void
    {
        $this->fakeGoogleUser('google-id-3', 'brand-new@example.com');

        $this->get('/auth/google/callback')->assertRedirect();

        $user = User::where('email', 'brand-new@example.com')->firstOrFail();

        $this->assertSame('google-id-3', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * The callback is registered with Google at a fixed URL, so it sits outside
     * the {locale} prefix and has no locale segment to read. An already-signed-
     * in visitor landing back on it - Google's silent prompt=none re-auth, a
     * refresh, a stale tab - trips the guest middleware, and building the
     * redirect used to throw UrlGenerationException for a missing parameter
     * rather than sending them home.
     */
    public function test_an_authenticated_visitor_hitting_the_callback_is_sent_home_not_a_500(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($user)
            ->get('/auth/google/callback')
            ->assertRedirect(route('home', ['locale' => 'ru']));
    }

    /** No stored preference either, so fall back to what the browser asks for. */
    public function test_the_redirect_falls_back_to_the_browser_language(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->get('/auth/google/callback', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertRedirect(route('home', ['locale' => 'en']));
    }
}
