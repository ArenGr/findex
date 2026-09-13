<?php

namespace Tests\Feature;

use App\Models\MortgageOffer;
use App\Models\Organization;
use Database\Seeders\CollectedMortgageOfferSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesBankProducts;
use Tests\TestCase;

class CollectedMortgageSeederTest extends TestCase
{
    use EnablesBankProducts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBankProducts(['mortgages']);

        foreach ([
            'armswissbank' => 'Armswissbank',
            'ameria' => 'Ameriabank',
            'acba' => 'ACBA Bank',
            'ardshinbank' => 'Ardshinbank',
            'ineco' => 'Inecobank',
            'vtb' => 'VTB Bank',
            'evoca' => 'Evocabank',
        ] as $slug => $name) {
            Organization::create([
                'name' => $name, 'slug' => $slug, 'type' => 'bank',
                'country_code' => 'AM', 'is_active' => true,
            ]);
        }

        $this->seed(CollectedMortgageOfferSeeder::class);
    }

    public function test_it_seeds_only_the_banks_that_exist(): void
    {
        // 12 offers in the seeder, but only the 7 orgs above exist here.
        $this->assertSame(7, MortgageOffer::count());
        $this->assertSame('9.37', MortgageOffer::whereHas('organization', fn ($q) => $q->where('slug', 'armswissbank'))->value('apr_min'));
    }

    public function test_the_page_shows_the_market_tier_and_a_promo_free_offers_table(): void
    {
        $html = $this->get(route('banks.show', ['locale' => 'en', 'category' => 'mortgages']))
            ->assertOk()
            ->getContent();

        // Normalise the @js-embedded offer JSON (quotes as \u0022).
        $decoded = str_replace('\u0022', '"', html_entity_decode($html));

        $this->assertStringContainsString(__('offers.mortgage_market.benchmark_label'), $decoded);
        $this->assertStringContainsString(__('offers.mortgage_market.heading'), $decoded);

        // Tier 2 - the offers table embeds the banks, ranked client-side.
        $this->assertStringContainsString('Armswissbank', $decoded);
        $this->assertStringContainsString('"eff_rate":9.37', $decoded);

        $this->assertStringNotContainsString('Ardshinbank', $decoded);

        $this->assertStringContainsString('"floating"', $decoded);
        $this->assertStringContainsString('"promo"', $decoded);
        $this->assertStringContainsString('"rate_only"', $decoded);
    }
}
