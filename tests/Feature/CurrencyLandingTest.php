<?php

namespace Tests\Feature;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pages that exist to be found. Somebody searching "USD to AMD rate today"
 * wants the number, so these assert the answer is on the page and in the meta,
 * not that a filter interface loaded.
 */
class CurrencyLandingTest extends TestCase
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

    public function test_the_answer_is_the_page_and_the_meta_description(): void
    {
        $this->seedMarket();

        $this->get('/en/rates/usd')
            ->assertOk()
            ->assertSee('USD to AMD exchange rate today')
            // Highest buy and lowest sell, named from the visitor's side.
            ->assertSee('384.00')
            ->assertSee('365.00')
            ->assertSee('Corner exchange')
            // A search result shows the description, so the number belongs in it.
            ->assertSee('sell at 384.00 AMD, buy at 365.00 AMD', false);
    }

    /** Lowercase in the URL, because that is how a link gets typed. */
    public function test_the_code_is_matched_case_insensitively(): void
    {
        $this->seedMarket();

        $this->get('/en/rates/usd')->assertOk();
        $this->get('/en/rates/USD')->assertOk();
    }

    /**
     * The fixed path must never be swallowed by the parameter beside it - the
     * classic way a route like this breaks another.
     */
    public function test_the_history_page_is_not_captured_by_the_currency_route(): void
    {
        $this->seedMarket();

        $this->get('/en/rates/history?currency=USD')
            ->assertOk()
            ->assertSee('USD exchange rate history in Armenia');
    }

    public function test_a_currency_we_do_not_track_is_not_a_page(): void
    {
        $this->seedMarket();

        $this->get('/en/rates/zzz')->assertNotFound();
        // Four letters is not a currency code and must not reach the controller.
        $this->get('/en/rates/abcd')->assertNotFound();
    }

    /** An inactive currency has no rates to answer with. */
    public function test_an_inactive_currency_has_no_landing_page(): void
    {
        $this->seedMarket();
        Currency::create(['code' => 'XXX', 'name' => 'Retired', 'symbol' => 'X', 'sort_order' => 9, 'is_active' => false]);

        $this->get('/en/rates/xxx')->assertNotFound();
    }

    /**
     * Only what we can stand behind. No aggregate rating and no offer count -
     * structured data that decorates rather than describes is worse than none.
     */
    public function test_the_structured_data_claims_only_the_rate(): void
    {
        $this->seedMarket();

        $html = $this->get('/en/rates/usd')->assertOk()->getContent();

        $this->assertStringContainsString('ExchangeRateSpecification', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringNotContainsString('AggregateOffer', $html);
    }

    /** §46: the caution belongs with the numbers. */
    public function test_the_page_carries_the_rates_disclaimer(): void
    {
        $this->seedMarket();

        $this->get('/en/rates/usd')->assertOk()->assertSee('confirm it before you exchange');
    }
}
