<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** Public, and readable without an account - it is a sales page. */
    public function test_the_docs_page_lists_endpoints_and_the_real_limits(): void
    {
        $this->get('/en/api')
            ->assertOk()
            ->assertSee('/api/v1/rates/best')
            ->assertSee('Business')
            // Read from config rather than written into the page, so the price
            // list cannot drift from what the limiter enforces.
            ->assertSee('$'.config('api.plans.business.price_usd_monthly'))
            ->assertSee(number_format(config('api.plans.basic.requests_per_day')));
    }

    public function test_keys_are_private_to_their_owner(): void
    {
        $this->get('/en/api/keys')->assertRedirect();

        [$key] = ApiKey::issue(['user_id' => User::factory()->create()->id, 'name' => 'Theirs']);

        $this->actingAs(User::factory()->create())
            ->delete('/en/api/keys/'.$key->id)
            ->assertStatus(403);

        $this->assertNull($key->refresh()->revoked_at);
    }

    /**
     * The key is shown once, on the redirect after creating it, and never
     * again - because after this request nothing can recover it.
     */
    public function test_a_new_key_is_shown_exactly_once(): void
    {
        $user = $this->actingAs(User::factory()->create());

        $response = $user->post('/en/api/keys', ['name' => 'My integration', 'plan' => 'free'])
            ->assertRedirect(route('api.keys.index'))
            ->assertSessionHas('new_api_key');

        $token = session('new_api_key');

        $user->get('/en/api/keys')->assertOk()->assertSee($token);

        // A second visit has nothing left to show, only the prefix.
        $user->get('/en/api/keys')
            ->assertOk()
            ->assertDontSee($token)
            ->assertSee(substr($token, 0, 11));
    }

    /** Enterprise is a conversation, not a button. */
    public function test_only_self_serve_plans_can_be_chosen(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/en/api/keys', ['name' => 'Cheeky', 'plan' => 'enterprise'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, ApiKey::count());
    }

    /** Revoked, not deleted - the usage counted against it has to survive. */
    public function test_revoking_keeps_the_row_for_reporting(): void
    {
        $user = User::factory()->create();
        [$key] = ApiKey::issue(['user_id' => $user->id, 'name' => 'Old']);

        $this->actingAs($user)->delete('/en/api/keys/'.$key->id)->assertRedirect();

        $this->assertNotNull($key->refresh()->revoked_at);
        $this->assertDatabaseHas('api_keys', ['id' => $key->id]);
        // ...and it stops working immediately.
        $this->getJson('/api/v1/currencies', ['Authorization' => 'Bearer whatever'])->assertStatus(401);
    }

    public function test_usage_this_month_is_reported_per_key(): void
    {
        $user = User::factory()->create();
        [$key, $token] = ApiKey::issue(['user_id' => $user->id, 'name' => 'Counted']);

        foreach (range(1, 2) as $ignored) {
            $this->getJson('/api/v1/currencies', ['Authorization' => 'Bearer '.$token])->assertOk();
        }

        $this->actingAs($user)->get('/en/api/keys')->assertOk()->assertSee('Counted')->assertSee('2');
    }
}
