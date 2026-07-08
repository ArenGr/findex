<?php

namespace App\Jobs;

use App\Services\AdminNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Backs the "Run All Scrapers" button on the admin scraping jobs page (see
 * ListScrapingJobs). Queued rather than run inline in the request, since
 * scraping every active organization's rates and mortgage offers can take
 * long enough to exceed a normal HTTP request's time budget - it's picked
 * up by the scheduled `queue:work --stop-when-empty` (see bootstrap/app.php)
 * within about a minute, the same as any other queued job here.
 */
class RunAllScrapersJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $ratesExit = Artisan::call('scrape:rates');
        $ratesOutput = Artisan::output();

        $mortgagesExit = Artisan::call('scrape:mortgages');
        $mortgagesOutput = Artisan::output();

        // Both commands return non-zero exit codes when at least one
        // organization failed (see ScrapeRates/ScrapeMortgages), but that was
        // previously discarded here - a fully failed run (or every source
        // failing on a given day) had no signal beyond someone happening to
        // check the admin scraping jobs table.
        if ($ratesExit !== 0 || $mortgagesExit !== 0) {
            $summary = collect([
                $ratesExit !== 0 ? "Rate scraping:\n{$ratesOutput}" : null,
                $mortgagesExit !== 0 ? "Mortgage scraping:\n{$mortgagesOutput}" : null,
            ])->filter()->implode("\n\n");

            AdminNotifier::scraperRunFailed($summary);
        }
    }
}
