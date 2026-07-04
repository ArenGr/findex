<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use App\Models\ScrapingJob;
use App\Services\RateScraper;

class ScrapeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:rates {--organization= : Organization slug to scrape} {--source-type=currency_rates : Source type to scrape}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape currency rates from organizations';

    /**
     * Execute the console command.
     */
    public function handle(RateScraper $scraper)
    {
        $organizationSlug = $this->option('organization');
        $sourceType = $this->option('source-type');

        // Get organizations to scrape
        if ($organizationSlug) {
            $organizations = Organization::where('slug', $organizationSlug)->active()->get();

            if ($organizations->isEmpty()) {
                $this->error("Organization '{$organizationSlug}' not found or inactive.");
                return self::FAILURE;
            }
        } else {
            $organizations = Organization::active()->get();

            if ($organizations->isEmpty()) {
                $this->error('No active organizations found.');
                return self::FAILURE;
            }
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($organizations as $organization) {
            try {
                $this->info("Scraping {$organization->name} ({$sourceType})...");

                $job = $scraper->scrape($organization, $sourceType);

                if ($job->status === 'success') {
                    $this->info("✓ {$organization->name}: {$job->records_found} records found");
                    $successCount++;
                } else {
                    $this->error("✗ {$organization->name}: {$job->error_message}");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error scraping {$organization->name}: {$e->getMessage()}");
                $failureCount++;
            }
        }

        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Success: $successCount | Failed: $failureCount");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return $failureCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}

