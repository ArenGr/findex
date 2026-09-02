<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSource;
use App\Models\ScrapingJob;
use App\Services\RateScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * scrape:rates must only touch organizations that actually have an active
 * source of the requested type. Before this, it iterated every active org, so
 * non-bank types (insurers, which carry no currency_rates source) each failed
 * with "Source not found" and inflated the failure count.
 */
class ScrapeRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function bankWithRatesSource(string $slug): Organization
    {
        $bank = Organization::create([
            'name' => 'Bank '.$slug, 'slug' => $slug, 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        OrganizationSource::create([
            'organization_id' => $bank->id, 'source_type' => 'currency_rates',
            'url' => 'https://example.test/rates', 'is_active' => true,
        ]);

        return $bank;
    }

    public function test_it_scrapes_only_organizations_that_have_the_source(): void
    {
        $this->bankWithRatesSource('test-bank');

        // An insurer with no currency_rates source - must be skipped entirely.
        Organization::create([
            'name' => 'Test Insurer', 'slug' => 'test-insurer', 'type' => 'insurance',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $scraped = [];
        $this->mock(RateScraper::class, function (MockInterface $mock) use (&$scraped) {
            $mock->shouldReceive('scrape')->andReturnUsing(function (Organization $org, string $type) use (&$scraped) {
                $scraped[] = $org->slug;

                return new ScrapingJob(['status' => 'success', 'records_found' => 3]);
            });
        });

        $this->artisan('scrape:rates')->assertExitCode(0);

        $this->assertSame(['test-bank'], $scraped);
    }

    public function test_an_inactive_source_is_not_scraped(): void
    {
        $bank = $this->bankWithRatesSource('active-bank');

        $stale = Organization::create([
            'name' => 'Stale Bank', 'slug' => 'stale-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        OrganizationSource::create([
            'organization_id' => $stale->id, 'source_type' => 'currency_rates',
            'url' => 'https://example.test/rates', 'is_active' => false,
        ]);

        $scraped = [];
        $this->mock(RateScraper::class, function (MockInterface $mock) use (&$scraped) {
            $mock->shouldReceive('scrape')->andReturnUsing(function (Organization $org) use (&$scraped) {
                $scraped[] = $org->slug;

                return new ScrapingJob(['status' => 'success', 'records_found' => 1]);
            });
        });

        $this->artisan('scrape:rates')->assertExitCode(0);

        $this->assertSame(['active-bank'], $scraped);
    }

    public function test_it_fails_clearly_when_nothing_has_the_source(): void
    {
        Organization::create([
            'name' => 'Test Insurer', 'slug' => 'test-insurer', 'type' => 'insurance',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $this->mock(RateScraper::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('scrape');
        });

        $this->artisan('scrape:rates')
            ->expectsOutputToContain("No active organizations have an active 'currency_rates' source.")
            ->assertExitCode(1);
    }
}
