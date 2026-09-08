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

    public function test_the_hero_reports_its_srcset_and_intrinsic_size(): void
    {
        $hero = TravelHero::asset('hero-photo');

        $this->assertNotNull($hero, 'run `npm run travel:assets` to build the derivatives');
        $this->assertStringContainsString('hero-photo-900.webp 900w', $hero['srcset']['webp']);
        $this->assertStringContainsString('hero-photo-1420.webp 1420w', $hero['srcset']['webp']);
        $this->assertStringContainsString('hero-photo-1420.avif 1420w', $hero['srcset']['avif']);
        $this->assertStringContainsString('hero-photo-1420.webp', $hero['src']);
        $this->assertSame(1420, $hero['width']);
        $this->assertSame(700, $hero['height']);
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

    public function test_the_hero_renders_without_its_photograph(): void
    {
        $this->uninstall('hero-photo');
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertDontSee('images/travel/hero-photo-1420', false);
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

    public function test_the_page_renders_the_photograph_when_it_is_installed(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();
        $response->assertSee('images/travel/hero-photo-1420.avif', false);
        $response->assertSee('type="image/avif"', false);
    }
}
