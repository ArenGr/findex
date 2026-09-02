<?php

namespace Tests\Feature;

use App\Enums\MortgageRateType;
use App\Models\MortgageOffer;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesBankProducts;
use Tests\TestCase;

/**
 * The mortgages page prepares APR-enriched, promo-filtered offer data for the
 * single interactive offers table (which ranks client-side). This covers the
 * server-side preparation - the enrichment and the exclusions - since the
 * pure client-side sort isn't reachable from a request test.
 */
class MortgageRankingPageTest extends TestCase
{
    use EnablesBankProducts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBankProducts(['mortgages']);
    }

    private function bank(string $slug, string $name): Organization
    {
        return Organization::create([
            'name' => $name, 'slug' => $slug, 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
    }

    private function secondaryOffer(Organization $org, float $apr, array $extra = []): void
    {
        MortgageOffer::create(array_merge([
            'organization_id' => $org->id,
            'currency' => 'AMD',
            'rate_type' => MortgageRateType::FIXED->value,
            'category' => 'secondary_market',
            'interest_rate_min' => $apr,
            'interest_rate_max' => $apr,
            'apr_min' => $apr,
            'apr_max' => $apr,
            'min_down_payment_percent' => 10,
            'term_max_months' => 240,
            'source_tier' => 'official_page',
            'scraped_at' => now(),
        ], $extra));
    }

    private function page(): string
    {
        // The offer data is embedded via @js inside an x-data attribute, so
        // its JSON quotes arrive HTML-escaped - decode so assertions can read
        // the embedded structure.
        $html = $this->get(route('banks.show', ['locale' => 'en', 'category' => 'mortgages']))
            ->assertOk()
            ->getContent();

        // Offer data is embedded via @js inside an x-data attribute, which
        // encodes JSON quotes as \u0022. Normalise so assertions can read the
        // embedded structure directly.
        return str_replace('\u0022', '"', html_entity_decode($html));
        // TEMP
        file_put_contents('/tmp/page.html', $out ?? '');
    }

    public function test_the_offers_table_embeds_apr_enriched_offers(): void
    {
        $this->secondaryOffer($this->bank('cheap-bank', 'Cheap Bank'), 11.25);
        $this->secondaryOffer($this->bank('pricey-bank', 'Pricey Bank'), 15.5);

        $html = $this->page();

        $this->assertStringContainsString('Cheap Bank', $html);
        $this->assertStringContainsString('Pricey Bank', $html);
        // Effective rate + APR basis are embedded for the client-side ranker.
        $this->assertStringContainsString('"eff_rate":11.25', $html);
        $this->assertStringContainsString('"basis":"apr"', $html);
    }

    public function test_an_expired_promo_offer_is_left_out_of_the_table(): void
    {
        $this->secondaryOffer($this->bank('live-bank', 'Live Bank'), 13.0, ['promo_ends_at' => now()->addMonth()]);
        $this->secondaryOffer($this->bank('expired-bank', 'Expired Bank'), 9.0, ['promo_ends_at' => now()->subDay()]);

        $html = $this->page();

        $this->assertStringContainsString('Live Bank', $html);
        // Its only figure is a lapsed promo, so the bank drops off entirely -
        // out of the offers table, the benchmark and the overview.
        $this->assertStringNotContainsString('Expired Bank', $html);
    }

    public function test_a_floating_offer_is_flagged_in_the_embedded_data(): void
    {
        $this->secondaryOffer($this->bank('float-bank', 'Float Bank'), 12.0, ['rate_type' => MortgageRateType::FLOATING_5Y->value]);

        $this->assertStringContainsString('"floating"', $this->page());
    }
}
