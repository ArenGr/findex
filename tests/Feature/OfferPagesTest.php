<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_lists_every_category(): void
    {
        $response = $this->get(route('banks.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('Mortgages');
        $response->assertSee('Personal Loans');
        $response->assertSee('Credit Cards');
    }

    /**
     * Regression test: OfferController::show() previously declared only
     * `string $category` (no leading `$locale`) - since Laravel binds route
     * parameters to controller parameters by position, not name, that made
     * $category silently receive the *locale* segment's value instead
     * ('en'/'hy'/'ru'), which never matches a real category key and 404s
     * every single request. Exercised across all three locales here so any
     * regression back to that positional-binding bug fails immediately,
     * not just on the default locale.
     */
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

    /**
     * /banks/all (the bank directory - OrganizationController::banks(),
     * unrelated to the {category} product pages) sits right next to the
     * {category} wildcard route - only the regex whitelist on that route
     * (see routes/web/public/pages.php) keeps "all" from being swallowed
     * as an unrecognized category and 404ing instead of reaching the
     * directory.
     */
    public function test_the_bank_directory_is_reachable_and_not_swallowed_by_the_category_wildcard(): void
    {
        $response = $this->get(route('banks.all', ['locale' => 'en']));

        $response->assertOk();
    }
}
