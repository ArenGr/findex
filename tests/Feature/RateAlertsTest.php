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
}
