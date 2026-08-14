<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Widgets render on other people's websites. Everything asserted here follows
 * from that: they must be framable, they must not carry a session, and their
 * URL must never move - an embed code lives in someone else's HTML and nobody
 * is going back to edit it.
 */
class WidgetTest extends TestCase
{
    use RefreshDatabase;

    private function seedMarket(): Currency
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        foreach ([['cheap-bank', 360, 365], ['corner-exchange', 384, 388]] as [$slug, $buy, $sell]) {
            $org = Organization::create(['name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug,
                'type' => 'bank', 'country_code' => 'AM', 'is_active' => true]);

            CurrencyRate::create(['organization_id' => $org->id, 'currency_id' => $usd->id,
                'rate_type' => RateType::CASH, 'buy_rate' => $buy, 'sell_rate' => $sell, 'scraped_at' => now()]);
        }

        return $usd;
    }

    /**
     * The default SAMEORIGIN would make a widget render everywhere except
     * where it is meant to. Safe to frame because there is nothing to hijack:
     * no session, no form, no action - numbers and a link home.
     */
    public function test_widgets_are_framable_while_the_rest_of_the_site_is_not(): void
    {
        $this->seedMarket();

        $this->get('/widgets/rate?currency=USD')
            ->assertOk()
            ->assertHeaderMissing('X-Frame-Options');

        $this->get('/en/rates')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** An embed code lives in someone else's HTML, so the URL must not move. */
    public function test_the_widget_url_carries_no_locale_segment(): void
    {
        $this->seedMarket();

        $this->get('/widgets/rate?currency=USD')->assertOk();
        $this->get('/en/widgets/rate?currency=USD')->assertNotFound();
    }

    /**
     * The same numbers the API sells. If these ever disagree, the widget has
     * grown its own copy of the rate logic - which is the thing
     * MarketRateService exists to prevent.
     */
    public function test_a_widget_shows_the_same_figures_as_the_api(): void
    {
        $this->seedMarket();

        $api = $this->getJson('/api/v1/rates/best?currency=USD')->assertOk();

        $this->get('/widgets/best?currency=USD')
            ->assertOk()
            ->assertSee(number_format((float) $api->json('data.highest_buy.rate'), 2))
            ->assertSee(number_format((float) $api->json('data.lowest_sell.rate'), 2))
            ->assertSee($api->json('data.highest_buy.organization.name'));
    }

    /** The reason we give them away. */
    public function test_every_widget_links_back(): void
    {
        $this->seedMarket();

        foreach (['rate', 'best', 'converter', 'chart'] as $type) {
            $this->get("/widgets/{$type}?currency=USD")
                ->assertOk()
                ->assertSee('Powered by Findex');
        }
    }

    public function test_unknown_widgets_and_currencies_are_not_invented(): void
    {
        $this->seedMarket();

        $this->get('/widgets/nonsense?currency=USD')->assertNotFound();
        $this->get('/widgets/rate?currency=ZZZ')->assertNotFound();
    }

    /** Cached at the edge: nothing here is personal, and host pages can be busy. */
    public function test_widgets_are_publicly_cacheable(): void
    {
        $this->seedMarket();

        $response = $this->get('/widgets/rate?currency=USD')->assertOk();

        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private', $response->headers->get('Cache-Control'));
    }

    /** A host page we do not control decides the surroundings, so both exist. */
    public function test_a_dark_variant_is_offered(): void
    {
        $this->seedMarket();

        $this->get('/widgets/rate?currency=USD&theme=dark')->assertOk()->assertSee('class="dark"', false);
        $this->get('/widgets/rate?currency=USD')->assertOk()->assertDontSee('class="dark"', false);
    }
}
