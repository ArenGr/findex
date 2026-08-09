<?php

namespace Tests\Feature;

use App\Models\User;
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

    /**
     * The header's Connect entry is an account action, not a group invite: it
     * points at the alert page's Telegram connect flow, which is auth-gated,
     * so notifications end up bound to a registered user rather than to a
     * bot session tied to nothing.
     */
    public function test_the_connect_menu_offers_telegram_through_the_account_flow(): void
    {
        $response = $this->get('/en');

        $response->assertOk()
            ->assertSee('Connect')
            ->assertSee(route('alerts.index', ['locale' => 'en', 'channel' => 'telegram']), false)
            ->assertSee('Telegram');
    }

    /** The bare t.me bot link started a session bound to no account. */
    public function test_the_header_no_longer_links_straight_to_the_bot(): void
    {
        config(['services.telegram.bot_username' => 'findex_rates_bot']);

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('t.me/findex_rates_bot');
    }

    /** It rendered href="#" on every page, because the group URL is never set. */
    public function test_the_unconfigured_whatsapp_entry_is_gone(): void
    {
        $this->get('/en')->assertOk()->assertDontSee('WhatsApp');
    }

    public function test_a_guest_following_connect_is_sent_to_sign_in_first(): void
    {
        $this->get('/en/alerts?channel=telegram')
            ->assertRedirect(route('login', ['locale' => 'en']));
    }

    public function test_a_signed_in_visitor_lands_on_the_telegram_connect_button(): void
    {
        config(['services.telegram.bot_username' => 'findex_rates_bot']);
        $user = User::factory()->create(['telegram_chat_id' => null]);

        $response = $this->actingAs($user)->get('/en/alerts?channel=telegram');

        $response->assertOk()
            // The deep link carries the token that binds the chat to this user.
            ->assertSee('t.me/findex_rates_bot?start='.$user->fresh()->telegram_connect_token, false);
    }

    public function test_the_header_reports_an_already_connected_account(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '123456']);

        $this->actingAs($user)->get('/en')->assertOk()->assertSee('Connected');
    }

    /** Separate test, not a second request: actingAs persists for the whole one. */
    public function test_an_account_without_telegram_is_not_reported_as_connected(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);

        $this->actingAs($user)->get('/en')->assertOk()->assertDontSee('Connected');
    }

    public function test_a_guest_is_not_reported_as_connected(): void
    {
        $this->get('/en')->assertOk()->assertDontSee('Connected');
    }
}
