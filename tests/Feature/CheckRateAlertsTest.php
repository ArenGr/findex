<?php

namespace Tests\Feature;

use App\Mail\RateAlertTriggered;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\RateAlert;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the alerts:check command's core business logic: edge-triggering
 * (notify once on the false->true transition, not on every run while still
 * met), resetting so a later re-cross notifies again, per-organization
 * scoping, and - per the ok:false fix in CheckRateAlerts::notify() - that a
 * failed Telegram delivery is never mistaken for a successful one.
 */
class CheckRateAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;
    private Organization $organization;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::create(['code' => 'TST', 'name' => 'Test Currency', 'symbol' => 'T', 'sort_order' => 1]);
        $this->organization = Organization::create([
            'name' => 'Alert Test Bank', 'slug' => 'alert-test-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }

    private function setSellRate(float $sellRate): CurrencyRate
    {
        return CurrencyRate::updateOrCreate(
            ['organization_id' => $this->organization->id, 'currency_id' => $this->currency->id, 'rate_type' => 'cash'],
            ['buy_rate' => $sellRate - 5, 'sell_rate' => $sellRate, 'scraped_at' => now()],
        );
    }

    private function createAlert(array $overrides = []): RateAlert
    {
        return RateAlert::create(array_merge([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'organization_id' => $this->organization->id,
            'rate_type' => 'cash',
            'rate_field' => 'sell_rate',
            'direction' => 'below',
            'threshold' => 390,
            'channel' => 'email',
            'is_active' => true,
        ], $overrides));
    }

    public function test_email_alert_fires_once_when_threshold_is_crossed(): void
    {
        Mail::fake();
        $this->setSellRate(385); // below the 390 threshold
        $alert = $this->createAlert();

        Artisan::call('alerts:check');

        Mail::assertSent(RateAlertTriggered::class, 1);
        $alert->refresh();
        $this->assertTrue($alert->is_currently_met);
        $this->assertNotNull($alert->last_triggered_at);
    }

    public function test_alert_does_not_renotify_while_still_met(): void
    {
        Mail::fake();
        $this->setSellRate(385);
        $this->createAlert();
        Artisan::call('alerts:check');
        Mail::assertSent(RateAlertTriggered::class, 1);

        Artisan::call('alerts:check'); // rate unchanged, still below threshold

        Mail::assertSent(RateAlertTriggered::class, 1); // still just the one
    }

    public function test_alert_resets_and_can_refire_after_condition_clears(): void
    {
        Mail::fake();
        $this->setSellRate(385);
        $alert = $this->createAlert();
        Artisan::call('alerts:check');
        Mail::assertSent(RateAlertTriggered::class, 1);

        $this->setSellRate(395); // above threshold - condition no longer met
        Artisan::call('alerts:check');
        $alert->refresh();
        $this->assertFalse($alert->is_currently_met);
        Mail::assertSent(RateAlertTriggered::class, 1); // no new mail for the reset itself

        $this->setSellRate(385); // crosses below again
        Artisan::call('alerts:check');

        Mail::assertSent(RateAlertTriggered::class, 2); // refired
        $this->assertTrue($alert->refresh()->is_currently_met);
    }

    public function test_alert_pinned_to_one_organization_ignores_other_organizations_rate(): void
    {
        Mail::fake();
        $otherOrg = Organization::create([
            'name' => 'Other Bank', 'slug' => 'other-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        CurrencyRate::create([
            'organization_id' => $otherOrg->id, 'currency_id' => $this->currency->id,
            'rate_type' => 'cash', 'buy_rate' => 380, 'sell_rate' => 385, 'scraped_at' => now(),
        ]);
        $this->setSellRate(395); // the pinned org's own rate does NOT satisfy the alert
        $this->createAlert(['organization_id' => $this->organization->id]);

        Artisan::call('alerts:check');

        Mail::assertNothingSent();
    }

    public function test_failed_telegram_delivery_is_not_treated_as_success(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => false, 'description' => 'chat not found']);
        });
        $this->setSellRate(385);
        $alert = $this->createAlert(['channel' => 'telegram', 'telegram_chat_id' => 'bad-chat-id']);

        Artisan::call('alerts:check');

        $alert->refresh();
        $this->assertFalse($alert->is_currently_met);
        $this->assertNull($alert->last_triggered_at);
    }

    public function test_successful_telegram_delivery_marks_alert_as_met(): void
    {
        $this->mock(TelegramClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);
        });
        $this->setSellRate(385);
        $alert = $this->createAlert(['channel' => 'telegram', 'telegram_chat_id' => 'good-chat-id']);

        Artisan::call('alerts:check');

        $alert->refresh();
        $this->assertTrue($alert->is_currently_met);
        $this->assertNotNull($alert->last_triggered_at);
    }
}
