<?php

namespace Tests\Feature;

use App\Support\TravelHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelHeroAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{0: string, 1: string}> */
    private array $moved = [];

    protected function setUp(): void
    {
        parent::setUp();
        TravelHero::flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->moved as [$from, $to]) {
            if (is_file($to)) {
                rename($to, $from);
            }
        }

        TravelHero::flush();
        parent::tearDown();
    }

    private function uninstall(string $name): void
    {
        foreach (glob(public_path("images/travel/{$name}-*.{avif,webp}"), GLOB_BRACE) ?: [] as $path) {
            $parked = $path.'.parked';
            rename($path, $parked);
            $this->moved[] = [$path, $parked];
        }

        TravelHero::flush();
    }

    public function test_an_unknown_asset_name_resolves_to_nothing(): void
    {
        $this->assertNull(TravelHero::asset('not-an-asset'));
    }

    public function test_an_asset_reports_its_srcset_and_intrinsic_size(): void
    {
        $hero = TravelHero::asset('panorama');

        $this->assertNotNull($hero, 'run `npm run travel:assets` to build the derivatives');
        $this->assertStringContainsString('panorama-960.webp 960w', $hero['srcset']['webp']);
        $this->assertStringContainsString('panorama-1916.webp 1916w', $hero['srcset']['webp']);
        $this->assertStringContainsString('panorama-1916.avif 1916w', $hero['srcset']['avif']);
        $this->assertStringContainsString('panorama-1916.webp', $hero['src']);
        $this->assertSame(1916, $hero['width']);
        $this->assertSame(821, $hero['height']);
    }

    public function test_the_landscape_crop_is_no_longer_shipped_or_referenced(): void
    {
        $this->assertNull(TravelHero::asset('hero-photo'));

        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertDontSee('images/travel/hero-photo', false);
    }

    public function test_the_retired_blob_asset_is_no_longer_referenced(): void
    {
        $this->assertNull(TravelHero::asset('hero'));
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertDontSee('images/travel/hero-2200', false);
    }

    public function test_an_asset_with_nothing_on_disk_resolves_to_nothing(): void
    {
        $this->uninstall('trip-empty');
        $this->assertNull(TravelHero::asset('trip-empty'));
    }

    public function test_the_hero_carries_its_headline_and_signals(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertSee(__('tourism.request.hero_line1'));
        $response->assertSee(__('tourism.request.benefit_trusted'));
    }

    public function test_the_closing_band_is_omitted_when_its_photograph_is_missing(): void
    {
        $this->uninstall('panorama');
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertDontSee(__('tourism.request.closing_title_2'));
    }

    /**
     * Travel opened on a full-bleed photograph while every other vertical
     * opened on a headline and a panel, which is what made the site read as
     * several products. It opens like the rest now - see x-vertical-hero.
     */
    public function test_the_hero_is_no_longer_a_photograph(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertDontSee('images/travel/hero-mobile', false);
    }
}
