<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Keys, plans and limits.
 *
 * The commercial numbers live in config/api.php and are read from there in
 * every assertion below rather than repeated - a repricing should change one
 * file, and if it also has to change the tests then the limits have leaked into
 * the code somewhere they should not have.
 */
class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The key is shown once and never stored. A database dump has to be
     * worthless to whoever ends up holding it.
     */
    public function test_the_key_itself_is_never_written_down(): void
    {
        [$key, $token] = ApiKey::issue(['name' => 'Test']);

        $row = DB::table('api_keys')->where('id', $key->id)->first();

        $this->assertStringNotContainsString($token, json_encode($row));
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        // Enough to tell two keys apart in a dashboard, not enough to use.
        $this->assertSame(substr($token, 0, 11), $row->prefix);
        $this->assertNotNull(ApiKey::findByToken($token));
    }

    /** The public API stays open; a key buys a bigger allowance, not entry. */
    public function test_the_api_is_readable_without_a_key(): void
    {
        $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', (string) config('api.anonymous.requests_per_day'));
    }

    public function test_a_key_is_given_the_allowance_of_its_plan(): void
    {
        [, $token] = ApiKey::issue(['name' => 'Business', 'plan' => 'business']);

        $this->withToken($token)
            ->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', (string) config('api.plans.business.requests_per_day'));
    }

    /**
     * Distinct from having no key: someone holding a revoked or mistyped key
     * needs to be told, not quietly downgraded and left wondering why they are
     * being throttled.
     */
    public function test_a_wrong_or_revoked_key_is_rejected_rather_than_ignored(): void
    {
        $this->withToken('fx_never_issued')->getJson('/api/v1/currencies')->assertStatus(401);

        [$key, $token] = ApiKey::issue(['name' => 'Retired']);
        $key->forceFill(['revoked_at' => now()])->save();

        $this->withToken($token)->getJson('/api/v1/currencies')->assertStatus(401);
    }

    public function test_exceeding_the_plan_returns_429_with_a_retry_hint(): void
    {
        $perMinute = config('api.anonymous.requests_per_minute');

        for ($i = 0; $i < $perMinute; $i++) {
            $this->getJson('/api/v1/currencies')->assertOk();
        }

        $this->getJson('/api/v1/currencies')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonStructure(['message', 'retry_after']);
    }

    /** Null means unmetered - the contract is the limit, not the code. */
    public function test_an_unmetered_plan_is_never_throttled(): void
    {
        [, $token] = ApiKey::issue(['name' => 'Enterprise', 'plan' => 'enterprise']);

        $this->assertNull(config('api.plans.enterprise.requests_per_day'));

        for ($i = 0; $i < config('api.anonymous.requests_per_minute') + 5; $i++) {
            $this->withToken($token)->getJson('/api/v1/currencies')->assertOk();
        }
    }

    /** Counts per key per day, so usage can be reported without a request log. */
    public function test_usage_is_counted_per_key_per_day(): void
    {
        [$key, $token] = ApiKey::issue(['name' => 'Counter', 'plan' => 'basic']);

        foreach (range(1, 3) as $ignored) {
            $this->withToken($token)->getJson('/api/v1/currencies')->assertOk();
        }

        $this->assertSame(3, (int) $key->usages()->where('day', now()->toDateString())->value('requests'));
        $this->assertNotNull($key->refresh()->last_used_at);
    }

    /** An unknown plan must not take the API down for whoever is on it. */
    public function test_a_retired_plan_name_falls_back_instead_of_failing(): void
    {
        [$key, $token] = ApiKey::issue(['name' => 'Legacy', 'plan' => 'plan_we_stopped_selling']);

        $this->assertSame(config('api.plans.free'), $key->limits());

        $this->withToken($token)->getJson('/api/v1/currencies')->assertOk();
    }
}
