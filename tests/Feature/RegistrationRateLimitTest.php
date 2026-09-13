<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// Every other sensitive endpoint in the app is throttled; registration was the one that wasn't.
class RegistrationRateLimitTest extends TestCase
{
    use RefreshDatabase;

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

        // Every request uses a different email - only the shared IP should bind them together.
        for ($i = 0; $i < $limit; $i++) {
            $this->register($i)->assertStatus(302);
        }

        $this->register($limit)->assertStatus(429);

        $this->assertSame($limit, User::count());
    }
}
