<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every other sensitive endpoint in the app is throttled; registration was
 * the one that wasn't. Each signup writes a user row and sends a
 * verification email, so an uncapped endpoint is both an outbound-mail
 * lever (sender reputation) and - via the unique:users,email rule, whose
 * validation error confirms an address exists - an account-enumeration
 * oracle across every role, admins included.
 *
 * The limiter is keyed on IP alone rather than IP+email (unlike 'login'),
 * since an attacker picks a fresh email every time and including it would
 * defeat the cap entirely - that's what this test pins down.
 */
class RegistrationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Logs out first: a successful registration authenticates the session,
     * and the route sits behind 'guest' - which runs before 'throttle' and
     * would redirect (never incrementing the limiter) on every attempt
     * after the first. A real attacker is a fresh guest each time.
     */
    private function register(int $n): TestResponse
    {
        Auth::logout();

        return $this->post(route('register.customer', ['locale' => 'en']), [
            'name' => "Spam {$n}",
            'email' => "spam{$n}@example.com",
            'password' => 'password-password',
            'password_confirmation' => 'password-password',
        ]);
    }

    public function test_registration_is_rate_limited_per_ip_regardless_of_the_email_used(): void
    {
        Mail::fake();

        $limit = config('rate-limits.register_per_hour');

        // Every request uses a different email - only the shared IP should
        // bind them together.
        for ($i = 0; $i < $limit; $i++) {
            $this->register($i)->assertStatus(302);
        }

        $this->register($limit)->assertStatus(429);

        $this->assertSame($limit, User::count());
    }
}
