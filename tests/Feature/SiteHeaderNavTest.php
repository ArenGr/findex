<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the top-level nav in site-header.blade.php: Banking (a dropdown
 * with exactly three real product links) and Insurance/Travel (plain
 * links, each with one real destination and no dropdown chrome around it).
 */
class SiteHeaderNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_banking_dropdown_links_to_loans_mortgage_and_cards(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertSee(route('banks.show', ['locale' => 'en', 'category' => 'personal-loans']), false);
        $response->assertSee(route('banks.show', ['locale' => 'en', 'category' => 'mortgages']), false);
        $response->assertSee(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']), false);
    }

    public function test_insurance_is_a_plain_link_straight_to_the_auto_insurance_quote_form(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertSee(route('insurance.auto.request', ['locale' => 'en']), false);
    }

    public function test_travel_is_a_plain_link_straight_to_the_quote_request_form(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertSee(route('tourism.request', ['locale' => 'en']), false);
    }
}
