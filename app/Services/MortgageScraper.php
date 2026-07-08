<?php

namespace App\Services;

use App\Models\MortgageOffer;
use App\Models\MortgageOfferHistory;
use App\Models\Organization;
use App\Models\ScrapingJob;
use App\Parsers\MortgageParserFactory;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MortgageScraper
{
    /**
     * Disk + directory used to store one local HTML file per source URL.
     *
     * During development these fixtures let us re-run the parser without
     * hitting the external banks on every run (which risks getting blocked).
     * The first request for a URL is fetched and saved; every later request
     * for the same URL reads the saved file instead of going to the network.
     */
    private const FIXTURE_DISK = 'local';
    private const FIXTURE_PATH = 'fixtures/scrapers';

    /**
     * Retries for transient failures only (connection/timeout errors, 5xx,
     * 429) - a plain 403/404 means the site is actively blocking us or the
     * URL is wrong, and hammering it again won't help. Kept short since
     * this runs in a daily cron job for many organizations in sequence, not
     * as a background retry queue.
     */
    private const MAX_RETRIES = 2;

    private Client $httpClient;

    public function __construct(private MortgageParserFactory $parsers)
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            self::shouldRetry(...),
            self::retryDelay(...),
        ));

        $this->httpClient = new Client([
            'handler' => $handlerStack,
            'timeout' => 20,
            'allow_redirects' => ['max' => 5],
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate',
                'Referer' => 'https://www.google.com/',
                'Upgrade-Insecure-Requests' => '1',
            ],
        ]);
    }

    private static function shouldRetry(
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $exception = null,
    ): bool {
        if ($retries >= self::MAX_RETRIES) {
            return false;
        }

        if ($exception !== null) {
            return true;
        }

        $status = $response?->getStatusCode();

        return $status !== null && ($status >= 500 || $status === 429);
    }

    private static function retryDelay(int $retries): int
    {
        // Guzzle passes a 1-based retry count here (1, 2, ...), unlike the
        // 0-based count shouldRetry() sees. Milliseconds: 1s, then 3s.
        return (int) (1000 * (2 * ($retries - 1) + 1));
    }

    /**
     * Scrape mortgage offers for an organization.
     */
    public function scrape(Organization $organization, string $sourceType = 'mortgages'): ScrapingJob
    {
        $job = $organization->scrapingJobs()->create([
            'source_type' => $sourceType,
            'status' => 'pending',
        ]);

        try {
            $job->markAsRunning();
            $job->log('info', "Starting to scrape {$organization->name} - {$sourceType}");

            $source = $organization->sources()
                ->where('source_type', $sourceType)
                ->where('is_active', true)
                ->first();

            if (!$source) {
                throw new \RuntimeException("Source '{$sourceType}' not found for {$organization->name}");
            }

            $url = $source->getFullUrl();
            $job->log('info', "Fetching from: {$url}");

            $html = $this->getHtml($url, $job);

            $recordsFound = $this->parseAndSaveOffers($organization, $html, $url, $job);

            $job->log('info', "Successfully parsed {$recordsFound} records");

            $source->markAsScraped();

            $job->markAsSuccess($recordsFound);

            return $job;
        } catch (\Throwable $e) {
            $job->log('error', $e->getMessage(), ['exception' => get_class($e)]);
            $job->markAsFailed($e->getMessage());

            return $job;
        }
    }

    /**
     * Return the HTML for a URL, backed by a local fixture file.
     *
     * Fixtures are only used in local/testing so repeated runs during
     * development don't hit the external banks (which risks getting
     * blocked). Outside those environments every run fetches live - a
     * production scrape must never serve stale cached HTML.
     */
    private function getHtml(string $url, ScrapingJob $job): string
    {
        $path = $this->fixturePath($url);
        $disk = Storage::disk(self::FIXTURE_DISK);
        $useFixture = app()->environment('local', 'testing');

        if ($useFixture && $disk->exists($path)) {
            $job->log('info', "Using local fixture for: {$url}");
            return $disk->get($path);
        }

        $job->log('info', "Fetching from site: {$url}");
        $html = (string) $this->httpClient->get($url)->getBody();

        if ($useFixture) {
            $disk->put($path, $html);
        }

        return $html;
    }

    /**
     * Build the local fixture path for a URL.
     */
    private function fixturePath(string $url): string
    {
        $host = Str::slug(parse_url($url, PHP_URL_HOST) ?? 'unknown');
        $hash = substr(hash('sha256', $url), 0, 16);

        return self::FIXTURE_PATH . "/{$host}_{$hash}.html";
    }

    /**
     * Parse HTML and save mortgage offers to database.
     *
     * @return int Number of records saved
     */
    private function parseAndSaveOffers(
        Organization $organization,
        string $html,
        string $sourceUrl,
        ScrapingJob $job
    ): int {
        $recordsCount = 0;

        $rows = $this->parsers->for($organization)->parse($html);

        foreach ($rows as $row) {
            try {
                $currency = strtoupper($row['currency']);
                $rateType = $row['rate_type'];
                $rateMin = (float) $row['rate_min'];
                $rateMax = (float) $row['rate_max'];

                if ($rateMin <= 0 || $rateMax <= 0) {
                    continue;
                }

                $offer = MortgageOffer::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'currency' => $currency,
                        'rate_type' => $rateType,
                    ],
                    [
                        'category' => $row['category'] ?? null,
                        'interest_rate_min' => $rateMin,
                        'interest_rate_max' => $rateMax,
                        'term_min_months' => $row['term_min_months'] ?? null,
                        'term_max_months' => $row['term_max_months'] ?? null,
                        'min_down_payment_percent' => $row['min_down_payment_percent'] ?? null,
                        'min_amount' => $row['min_amount'] ?? null,
                        'max_amount' => $row['max_amount'] ?? null,
                        'source_url' => $sourceUrl,
                        'scraped_at' => now(),
                    ]
                );

                // Only append history when the offer is new or its rate
                // actually changed, so history doesn't fill with duplicates.
                if ($offer->wasRecentlyCreated || $offer->wasChanged(['interest_rate_min', 'interest_rate_max'])) {
                    MortgageOfferHistory::createFromOffer($offer);
                }

                $recordsCount++;

                $job->log('debug', "Saved mortgage offer: {$currency} ({$rateType}) - {$rateMin}-{$rateMax}%");
            } catch (\Throwable $e) {
                $job->log('warning', "Error parsing mortgage offer row: {$e->getMessage()}");
            }
        }

        return $recordsCount;
    }
}
