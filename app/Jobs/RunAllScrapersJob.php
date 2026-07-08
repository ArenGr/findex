<?php

namespace App\Jobs;

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
        Artisan::call('scrape:rates');
        Artisan::call('scrape:mortgages');
    }
}
