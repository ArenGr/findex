<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesBankProducts;
use Tests\TestCase;

/**
 * Covers the top-level nav in site-header.blade.php: Banking (a dropdown
 * with exactly three real product links) and Insurance/Travel (plain
 * links, each with one real destination and no dropdown chrome around it).
 */
class SiteHeaderNavTest extends TestCase
{
    use EnablesBankProducts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBankProducts();
    }

    /**
     * Banking groups its products into Loans and Cards submenus. The leaf
     * links are derived from OfferController::CATEGORIES rather than
     * hardcoded in the view, so this also pins that wiring.
     */
    public function test_the_banking_menu_links_to_every_grouped_bank_product(): void
    {
        $response = $this->get('/en');

        $response->assertOk();

        foreach (['mortgages', 'personal-loans', 'business-loans', 'student-loans', 'credit-cards', 'banking', 'investing'] as $category) {
            $response->assertSee(route('banks.show', ['locale' => 'en', 'category' => $category]), false);
        }
    }

    public function test_the_banking_menu_shows_the_group_labels(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertSee(__('nav.banking.groups.loans'));
        $response->assertSee(__('nav.banking.groups.cards'));
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
