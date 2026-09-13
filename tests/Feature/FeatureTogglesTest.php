<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\Organization;
use App\Models\User;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeatureTogglesTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, bool $on): void
    {
        FeatureToggle::updateOrCreate(['key' => $key], ['is_enabled' => $on]);
        FeatureToggle::forgetCache();
    }

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::sync();
        FeatureToggle::forgetCache();
    }

    public function test_every_registry_key_gets_a_row(): void
    {
        $this->assertSame([], array_diff(Features::keys(), FeatureToggle::pluck('key')->all()));
    }

    public function test_a_flag_with_no_row_falls_back_to_its_registry_default(): void
    {
        FeatureToggle::where('key', Features::TRAVEL)->delete();
        FeatureToggle::forgetCache();

        $this->assertTrue(Features::enabled(Features::TRAVEL));
    }

    public function test_switching_a_feature_off_404s_its_routes(): void
    {
        $this->get('/en/tourism')->assertOk();

        $this->set(Features::TRAVEL, false);

        $this->get('/en/tourism')->assertNotFound();
    }

    public function test_a_child_is_held_off_by_its_parent(): void
    {
        $this->assertTrue(Features::enabled(Features::TRAVEL_VOICE_FILL));

        $this->set(Features::TRAVEL, false);

        $this->assertFalse(Features::enabled(Features::TRAVEL_VOICE_FILL));
        // Its own row is untouched, so re-enabling the parent restores it.
        $this->assertTrue(FeatureToggle::where('key', Features::TRAVEL_VOICE_FILL)->value('is_enabled'));
    }

    public function test_a_child_can_be_switched_off_on_its_own(): void
    {
        $this->set(Features::TRAVEL_VOICE_FILL, false);

        $this->assertTrue(Features::enabled(Features::TRAVEL));
        $this->assertFalse(Features::enabled(Features::TRAVEL_VOICE_FILL));
    }

    public function test_the_nav_and_home_cards_drop_a_switched_off_vertical(): void
    {
        $this->get('/en')->assertOk()->assertSee(route('tourism.request'));

        $this->set(Features::TRAVEL, false);

        $this->get('/en')->assertOk()->assertDontSee('/en/tourism');
    }

    /**
     * The home page, the header and the footer all link into gated routes, and
     * a route() call for a route that is no longer registered throws. With
     * every flag off there is nothing left to link to, which is the case most
     * likely to have been missed.
     */
    #[DataProvider('alwaysOnPages')]
    public function test_the_ungated_pages_survive_every_flag_being_off(string $path): void
    {
        FeatureToggle::query()->update(['is_enabled' => false]);
        FeatureToggle::forgetCache();

        $this->get($path)->assertOk();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function alwaysOnPages(): array
    {
        return array_map(fn (string $p) => [$p], [
            '/en', '/en/about', '/en/team', '/en/careers', '/en/news',
            '/en/help', '/en/faq', '/en/contact', '/en/terms', '/en/privacy',
            '/en/cookies', '/en/login', '/en/register',
        ]);
    }

    public function test_the_blade_directive_follows_the_flag(): void
    {
        // Newlines matter: Blade's custom-directive pattern needs a non-word
        // character before @, so "yes@endfeature" would not compile.
        $template = "@feature('travel')\nyes\n@endfeature";

        $this->set(Features::TRAVEL, true);
        $this->assertSame('yes', trim($this->blade($template)));

        $this->set(Features::TRAVEL, false);
        $this->assertSame('', trim($this->blade($template)));
    }

    /**
     * Off has to mean off in the markup too, not just in the router. Each of
     * these renders on a page that stays up when the feature goes away, so a
     * missed one leaves a dead link or a route() that throws.
     *
     * @param  array<int, string>  $gone
     */
    #[DataProvider('uiThatMustDisappear')]
    public function test_switching_a_feature_off_removes_its_ui(string $feature, string $page, array $gone): void
    {
        $this->get($page)->assertOk()->assertSee($gone[0], false);

        $this->set($feature, false);

        $response = $this->get($page)->assertOk();

        foreach ($gone as $needle) {
            $response->assertDontSee($needle, false);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    public static function uiThatMustDisappear(): array
    {
        return [
            'rate alerts on the rates page' => [Features::RATE_ALERTS, '/en/rates', ['rate-alert-open']],
            'better rate on the rates page' => [Features::EXCHANGE, '/en/rates', ['better-rate-open']],
            'history link on the rates page' => [Features::RATES_HISTORY, '/en/rates', ['/rates/history']],
            'compare tray in the layout' => [Features::COMPARE, '/en/rates', ['$store.compare']],
            'travel in the header and on home' => [Features::TRAVEL, '/en', ['/en/tourism']],
            'the rates table on home' => [Features::RATES, '/en', ['/en/rates']],
        ];
    }

    public function test_an_organization_dashboard_drops_tabs_for_switched_off_features(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard')
            ->assertOk()
            ->assertSee('/org/dashboard/reports', false)
            ->assertSee('/org/dashboard/reviews', false);

        $this->set(Features::REPORTS, false);
        $this->set(Features::REVIEWS, false);

        $this->actingAs($user, 'organization')
            ->get('/en/org/dashboard')
            ->assertOk()
            ->assertDontSee('/org/dashboard/reports', false)
            ->assertDontSee('/org/dashboard/reviews', false);
    }

    public function test_the_register_chooser_only_offers_open_sign_ups(): void
    {
        $this->get('/en/register')->assertOk()->assertSee('/register/customer', false);

        $this->set(Features::CUSTOMER_REGISTRATION, false);

        $this->get('/en/register')->assertOk()->assertDontSee('/register/customer', false);
    }

    public function test_the_map_view_falls_back_to_the_list_when_the_map_is_off(): void
    {
        $this->get('/en/rates?view=map')->assertOk()->assertViewHas('viewMode', 'map');

        $this->set(Features::RATES_MAP, false);

        $this->get('/en/rates?view=map')->assertOk()->assertViewHas('viewMode', 'list');
    }
}
