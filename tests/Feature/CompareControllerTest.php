<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\MortgageOffer;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrganization(string $slug, bool $active = true): Organization
    {
        return Organization::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => $active,
        ]);
    }

    public function test_returns_organizations_in_the_requested_order(): void
    {
        $this->makeOrganization('bank-a');
        $this->makeOrganization('bank-b');

        // Reversed vs. creation order - the view must follow the query
        // string's order, not insertion/id order.
        $response = $this->get('/en/compare?orgs=bank-b,bank-a');

        $slugs = $response->original->getData()['organizations']->pluck('slug')->all();
        $this->assertSame(['bank-b', 'bank-a'], $slugs);
    }

    public function test_caps_at_three_organizations_and_dedupes(): void
    {
        $this->makeOrganization('bank-a');
        $this->makeOrganization('bank-b');
        $this->makeOrganization('bank-c');
        $this->makeOrganization('bank-d');

        $response = $this->get('/en/compare?orgs=bank-a,bank-a,bank-b,bank-c,bank-d');

        $slugs = $response->original->getData()['organizations']->pluck('slug')->all();
        $this->assertCount(3, $slugs);
        $this->assertSame(['bank-a', 'bank-b', 'bank-c'], $slugs);
    }

    public function test_excludes_inactive_and_unknown_organizations(): void
    {
        $this->makeOrganization('bank-a');
        $this->makeOrganization('bank-inactive', active: false);

        $response = $this->get('/en/compare?orgs=bank-a,bank-inactive,does-not-exist');

        $slugs = $response->original->getData()['organizations']->pluck('slug')->all();
        $this->assertSame(['bank-a'], $slugs);
    }

    public function test_renders_gracefully_with_no_orgs_param(): void
    {
        $response = $this->get('/en/compare');

        $response->assertOk();
        $this->assertCount(0, $response->original->getData()['organizations']);
    }

    public function test_only_shows_cash_rates_for_the_curated_currency_set(): void
    {
        $bank = $this->makeOrganization('bank-a');
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $gel = Currency::create(['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => 'GEL', 'sort_order' => 2]);

        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $usd->id, 'rate_type' => 'cash',
            'buy_rate' => 380, 'sell_rate' => 385, 'scraped_at' => now(),
        ]);
        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $usd->id, 'rate_type' => 'non_cash',
            'buy_rate' => 381, 'sell_rate' => 384, 'scraped_at' => now(),
        ]);
        CurrencyRate::create([
            'organization_id' => $bank->id, 'currency_id' => $gel->id, 'rate_type' => 'cash',
            'buy_rate' => 140, 'sell_rate' => 145, 'scraped_at' => now(),
        ]);

        $response = $this->get('/en/compare?orgs=bank-a');

        $rates = $response->original->getData()['ratesByOrgId'][$bank->id];
        $this->assertCount(1, $rates);
        $this->assertSame('USD', $rates->first()->currency->code);
        $this->assertSame('cash', $rates->first()->rate_type->value);
    }

    public function test_only_shows_secondary_market_mortgage_offers(): void
    {
        $bank = $this->makeOrganization('bank-a');
        MortgageOffer::create([
            'organization_id' => $bank->id, 'currency' => 'AMD', 'rate_type' => 'fixed',
            'category' => 'secondary_market', 'interest_rate_min' => 10, 'interest_rate_max' => 12,
        ]);
        // A different currency, not a second category for the same
        // currency+rate_type: (organization_id, currency, rate_type) is
        // uniquely constrained, so a bank can't actually have two offers -
        // in different categories or not - for the same combination today.
        MortgageOffer::create([
            'organization_id' => $bank->id, 'currency' => 'USD', 'rate_type' => 'fixed',
            'category' => 'new_construction', 'interest_rate_min' => 9, 'interest_rate_max' => 11,
        ]);

        $response = $this->get('/en/compare?orgs=bank-a');

        $offers = $response->original->getData()['mortgagesByOrgId'][$bank->id];
        $this->assertCount(1, $offers);
        $this->assertSame('secondary_market', $offers->first()->category);
    }
}
