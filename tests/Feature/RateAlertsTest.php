<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_form_prefills_from_query_string(): void
    {
        $user = User::factory()->create();
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $organization = Organization::create(['name' => 'Test Bank', 'slug' => 'test-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => true]);

        $response = $this->actingAs($user)->get("/en/alerts?currency_id={$currency->id}&organization_id={$organization->id}&rate_type=cash&rate_field=buy_rate");

        $response->assertOk();
        $response->assertSee('id="create-alert"', false);
        $response->assertSeeInOrder([
            "value=\"{$currency->id}\" selected",
            "value=\"{$organization->id}\" selected",
            'value="cash" selected',
            'value="buy_rate" selected',
        ], false);
    }

    public function test_visiting_the_index_generates_a_telegram_connect_token_for_an_unconnected_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/alerts')->assertOk();

        $user->refresh();
        $this->assertNotNull($user->telegram_connect_token);
        $this->assertSame('en', $user->locale);
    }

    public function test_visiting_the_index_does_not_touch_the_token_for_an_already_connected_user(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '999', 'telegram_connect_token' => null]);

        $this->actingAs($user)->get('/en/alerts')->assertOk();

        $this->assertNull($user->refresh()->telegram_connect_token);
    }

    public function test_creating_a_telegram_alert_without_a_connected_account_fails(): void
    {
        $user = User::factory()->create();
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $response = $this->actingAs($user)->post('/en/alerts', [
            'currency_id' => $currency->id,
            'rate_type' => 'cash',
            'rate_field' => 'sell_rate',
            'direction' => 'below',
            'threshold' => 400,
            'channel' => 'telegram',
        ]);

        $response->assertSessionHasErrors('channel');
        $this->assertSame(0, $user->rateAlerts()->count());
    }

    public function test_creating_a_telegram_alert_with_a_connected_account_uses_its_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '555']);
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $response = $this->actingAs($user)->post('/en/alerts', [
            'currency_id' => $currency->id,
            'rate_type' => 'cash',
            'rate_field' => 'sell_rate',
            'direction' => 'below',
            'threshold' => 400,
            'channel' => 'telegram',
        ]);

        $response->assertRedirect(route('alerts.index'));
        $alert = $user->rateAlerts()->firstOrFail();
        $this->assertSame('555', $alert->telegram_chat_id);
    }

    public function test_disconnect_clears_telegram_chat_id_and_a_fresh_token_appears_on_reload(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '123456', 'telegram_connect_token' => null]);

        $this->actingAs($user)->post('/en/alerts/telegram/disconnect')
            ->assertRedirect(route('alerts.index'));

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);

        $this->actingAs($user)->get('/en/alerts')->assertOk();
        $user->refresh();
        $this->assertNotNull($user->telegram_connect_token);
    }

    public function test_connecting_viber_sets_a_chat_id(): void
    {
        $user = User::factory()->create(['viber_chat_id' => null]);

        $this->actingAs($user)->post('/en/alerts/viber/connect')
            ->assertRedirect(route('alerts.index'));

        $this->assertNotNull($user->refresh()->viber_chat_id);
    }

    public function test_disconnecting_viber_clears_the_chat_id(): void
    {
        $user = User::factory()->create(['viber_chat_id' => 'demo-viber-abc123']);

        $this->actingAs($user)->post('/en/alerts/viber/disconnect')
            ->assertRedirect(route('alerts.index'));

        $this->assertNull($user->refresh()->viber_chat_id);
    }

    public function test_creating_a_viber_alert_without_a_connected_account_fails(): void
    {
        $user = User::factory()->create();
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $response = $this->actingAs($user)->post('/en/alerts', [
            'currency_id' => $currency->id,
            'rate_type' => 'cash',
            'rate_field' => 'sell_rate',
            'direction' => 'below',
            'threshold' => 400,
            'channel' => 'viber',
        ]);

        $response->assertSessionHasErrors('channel');
        $this->assertSame(0, $user->rateAlerts()->count());
    }

    public function test_creating_a_viber_alert_with_a_connected_account_uses_its_chat_id(): void
    {
        $user = User::factory()->create(['viber_chat_id' => 'demo-viber-xyz789']);
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);

        $response = $this->actingAs($user)->post('/en/alerts', [
            'currency_id' => $currency->id,
            'rate_type' => 'cash',
            'rate_field' => 'sell_rate',
            'direction' => 'below',
            'threshold' => 400,
            'channel' => 'viber',
        ]);

        $response->assertRedirect(route('alerts.index'));
        $alert = $user->rateAlerts()->firstOrFail();
        $this->assertSame('demo-viber-xyz789', $alert->viber_chat_id);
    }
}
