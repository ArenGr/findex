<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\RateScraper;
use Illuminate\Console\Command;

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
            // Only organizations that actually have an active source of this
            // type. Without this the command tried every active org - so once
            // non-bank types existed (insurers, which carry no currency_rates
            // source), each one failed with "Source 'currency_rates' not
            // found" and inflated the failure count. Scoping by the source
            // itself is more robust than a hardcoded type == 'bank': it is
            // correct for branches/mortgages too, and stays correct if an
            // exchange office ever starts publishing rates.
            $organizations = Organization::active()
                ->whereHas('sources', fn ($query) => $query
                    ->where('source_type', $sourceType)
                    ->where('is_active', true))
                ->get();

            if ($organizations->isEmpty()) {
                $this->error("No active organizations have an active '{$sourceType}' source.");

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
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return $failureCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
