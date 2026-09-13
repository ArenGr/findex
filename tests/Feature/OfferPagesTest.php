<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesBankProducts;
use Tests\TestCase;

class OfferPagesTest extends TestCase
{
    use EnablesBankProducts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBankProducts();
    }

    public function test_the_index_page_lists_every_category(): void
    {
        $response = $this->get(route('banks.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('Mortgages');
        $response->assertSee('Personal Loans');
        $response->assertSee('Credit Cards');
    }

    public function test_an_available_category_page_loads_in_every_locale(): void
    {
        foreach (['en', 'hy', 'ru'] as $locale) {
            $response = $this->get(route('banks.show', ['locale' => $locale, 'category' => 'mortgages']));

            $response->assertOk();
        }
    }

    public function test_the_personal_loans_and_banking_pages_load(): void
    {
        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'personal-loans']))->assertOk();
        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'banking']))->assertOk();
    }

    public function test_an_unbuilt_category_shows_a_coming_soon_page_instead_of_404ing(): void
    {
        $response = $this->get(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']));

        $response->assertOk();
        $response->assertSee('Credit Cards');
    }

    public function test_an_unrecognized_category_slug_404s(): void
    {
        $response = $this->get(route('banks.show', ['locale' => 'en', 'category' => 'not-a-real-category']));

        $response->assertNotFound();
    }

    public function test_the_bank_directory_is_reachable_and_not_swallowed_by_the_category_wildcard(): void
    {
        $response = $this->get(route('banks.all', ['locale' => 'en']));

        $response->assertOk();
    }
}
