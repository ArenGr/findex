<?php

namespace Tests\Feature;

use App\Http\Controllers\OfferController;
use App\Models\FeatureToggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesBankProducts;
use Tests\TestCase;

class BankProductFeatureTogglesTest extends TestCase
{
    use EnablesBankProducts, RefreshDatabase;

    public function test_an_enabled_category_is_reachable_and_listed(): void
    {
        $this->enableBankProducts(['credit-cards']);

        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']))->assertOk();
        $this->get('/en')->assertSee(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']), false);
        $this->get(route('banks.index', ['locale' => 'en']))
            ->assertSee(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']), false);
    }

    public function test_a_disabled_category_404s_and_is_hidden_from_the_nav_and_hub(): void
    {
        $this->enableBankProducts(['mortgages']);

        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']))->assertNotFound();
        $this->get('/en')->assertDontSee('banks/credit-cards');
        $this->get(route('banks.index', ['locale' => 'en']))->assertDontSee('banks/credit-cards');
    }

    public function test_flipping_a_toggle_takes_effect_immediately(): void
    {
        $this->enableBankProducts([]);

        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'investing']))->assertNotFound();

        FeatureToggle::where('key', 'investing')->first()->update(['is_enabled' => true]);

        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'investing']))->assertOk();

        FeatureToggle::where('key', 'investing')->first()->update(['is_enabled' => false]);

        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'investing']))->assertNotFound();
    }

    public function test_the_banking_menu_disappears_entirely_when_every_product_is_off(): void
    {
        $this->enableBankProducts([]);

        $response = $this->get('/en');

        $response->assertOk();
        $response->assertDontSee(__('nav.banking.label'));
        // The rest of the header must survive a fully-empty Banking menu.
        $response->assertSee(__('nav.travel.label'));
    }

    public function test_every_known_category_renders_a_page_when_enabled(): void
    {
        $this->enableBankProducts();

        foreach (OfferController::CATEGORIES as $category) {
            $this->get(route('banks.show', ['locale' => 'en', 'category' => $category]))
                ->assertOk();
        }
    }

    public function test_a_sample_page_states_that_its_figures_are_not_real(): void
    {
        $this->enableBankProducts();

        $this->get(route('banks.show', ['locale' => 'en', 'category' => 'credit-cards']))
            ->assertSee(__('bank_products.sample_badge'))
            ->assertSee(__('bank_products.sample_notice'))
            ->assertSee(__('bank_products.needed_heading'));
    }

    public function test_sample_rows_line_up_with_their_column_headings(): void
    {
        foreach (array_keys(config('bank-products')) as $category) {
            $columnCount = count(__('bank_products.'.$category.'.columns'));

            foreach (config('bank-products.'.$category.'.rows') as $index => $row) {
                $this->assertCount(
                    $columnCount,
                    $row,
                    "Row {$index} of '{$category}' does not match its column count.",
                );
            }
        }
    }
}
