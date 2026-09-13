<?php

namespace App\Jobs;

use App\Services\AdminNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

// Backs the "Run All Scrapers" button on the admin scraping jobs page (see ListScrapingJobs).
class RunAllScrapersJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $ratesExit = Artisan::call('scrape:rates');
        $ratesOutput = Artisan::output();

        $mortgagesExit = Artisan::call('scrape:mortgages');
        $mortgagesOutput = Artisan::output();

        if ($ratesExit !== 0 || $mortgagesExit !== 0) {
            $summary = collect([
                $ratesExit !== 0 ? "Rate scraping:\n{$ratesOutput}" : null,
                $mortgagesExit !== 0 ? "Mortgage scraping:\n{$mortgagesOutput}" : null,
            ])->filter()->implode("\n\n");

            AdminNotifier::scraperRunFailed($summary);
        }
    }
}
