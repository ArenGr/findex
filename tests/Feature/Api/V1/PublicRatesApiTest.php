<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public API is a contract, and these tests are what makes it one.
 *
 * The shape assertions matter more than they look: every response is built by a
 * Resource rather than by handing an Eloquent model to the serializer, so a
 * column added or renamed tomorrow cannot silently join or break what a paying
 * customer's integration depends on. If one of these fails, the version needs
 * to go up - not the test.
 */
class PublicRatesApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedMarket(): Currency
    {
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1, 'is_active' => true]);

        foreach ([['cheap-bank', 'bank', 360, 365], ['pricey-bank', 'bank', 358, 370], ['corner-exchange', 'exchange', 384, 388]] as [$slug, $type, $buy, $sell]) {
            $org = Organization::create(['name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug,
                'type' => $type, 'country_code' => 'AM', 'is_active' => true]);

            CurrencyRate::create(['organization_id' => $org->id, 'currency_id' => $usd->id,
                'rate_type' => RateType::CASH, 'buy_rate' => $buy, 'sell_rate' => $sell, 'scraped_at' => now()]);
        }

        return $usd;
    }

    public function test_the_rate_payload_shape_is_the_contract(): void
    {
        $this->seedMarket();

        $this->getJson('/api/v1/rates?currency=USD')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['organization' => ['slug', 'name', 'type'], 'currency', 'rate_type', 'buy_rate', 'sell_rate', 'scraped_at']],
                'meta' => ['currency', 'rate_type', 'count'],
            ])
            // Decimals as strings: a consumer parsing 367.00 as a double and
            // printing 366.99999 is a support ticket we would rather avoid.
            ->assertJsonPath('data.0.buy_rate', '360.0000');
    }

    /** Internal ids are ours to change and must not reach the contract. */
    public function test_no_database_ids_are_exposed(): void
    {
        $this->seedMarket();

        foreach (['/api/v1/rates?currency=USD', '/api/v1/organizations', '/api/v1/currencies'] as $url) {
            $body = $this->getJson($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('"id"', $body, "{$url} leaks an internal id");
            $this->assertStringNotContainsString('_id"', $body, "{$url} leaks a foreign key");
        }
    }

    /**
     * "Best" from the customer's side, and named so - the highest anyone buys
     * at, the lowest anyone sells at. The ambiguity in the word is the thing
     * this whole product exists to remove.
     */
    public function test_best_is_named_from_the_customers_side(): void
    {
        $this->seedMarket();

        $this->getJson('/api/v1/rates/best?currency=USD')
            ->assertOk()
            ->assertJsonPath('data.highest_buy.rate', '384.0000')
            ->assertJsonPath('data.highest_buy.organization.slug', 'corner-exchange')
            ->assertJsonPath('data.lowest_sell.rate', '365.0000')
            ->assertJsonPath('data.lowest_sell.organization.slug', 'cheap-bank');
    }

    public function test_the_average_counts_every_organization_quoting(): void
    {
        $this->seedMarket();

        // (360 + 358 + 384) / 3 and (365 + 370 + 388) / 3.
        $this->getJson('/api/v1/rates/average?currency=USD')
            ->assertOk()
            ->assertJsonPath('data.organizations', 3)
            ->assertJsonPath('data.average_buy', '367.3333')
            ->assertJsonPath('data.average_sell', '374.3333');
    }

    /** Asking for a year and getting everything we hold beats an error. */
    public function test_a_history_window_longer_than_the_data_is_clamped_and_declared(): void
    {
        $usd = $this->seedMarket();
        $rate = CurrencyRate::query()->firstOrFail();

        CurrencyRateHistory::create(['currency_rate_id' => $rate->id, 'buy_rate' => 355,
            'sell_rate' => 360, 'scraped_at' => now()->subDays(2)]);

        $response = $this->getJson('/api/v1/rates/history?currency='.$usd->code.'&days=365')->assertOk();

        $this->assertLessThanOrEqual(3, $response->json('meta.days'));
        $this->assertSame($response->json('meta.days_available'), $response->json('meta.days'));
    }

    public function test_unknown_input_is_rejected_rather_than_guessed(): void
    {
        $this->seedMarket();

        $this->getJson('/api/v1/rates')->assertStatus(422)->assertJsonValidationErrors('currency');
        $this->getJson('/api/v1/rates?currency=ZZZ')->assertStatus(422)->assertJsonValidationErrors('currency');
        $this->getJson('/api/v1/rates?currency=USD&type=nonsense')->assertStatus(422)->assertJsonValidationErrors('type');
        $this->getJson('/api/v1/rates/history?currency=USD&days=0')->assertStatus(422)->assertJsonValidationErrors('days');
    }

    /** Errors come back as JSON, not as an HTML error page. */
    public function test_a_missing_organization_answers_in_json(): void
    {
        $this->seedMarket();

        $this->getJson('/api/v1/organizations/does-not-exist/rates')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    /** Only organizations that deal in rates - not insurers or agencies. */
    public function test_the_organization_list_covers_only_rate_publishers(): void
    {
        $this->seedMarket();
        Organization::create(['name' => 'An insurer', 'slug' => 'an-insurer', 'type' => 'insurance',
            'country_code' => 'AM', 'is_active' => true]);

        $slugs = collect($this->getJson('/api/v1/organizations')->assertOk()->json('data'))->pluck('slug');

        $this->assertContains('cheap-bank', $slugs);
        $this->assertNotContains('an-insurer', $slugs);
    }

    /** No locale segment: these are numbers, not prose. */
    public function test_the_api_is_not_locale_prefixed(): void
    {
        $this->seedMarket();

        $this->getJson('/api/v1/currencies')->assertOk();
        $this->getJson('/en/api/v1/currencies')->assertNotFound();
    }
}
