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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MortgageScraper
{
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
        // One row per organization+source_type, updated in place on every
        // run - the admin's scraping jobs table is a current-status view,
        // not a growing history log. Updating (rather than deleting the old
        // row and inserting a new one) means the row is never briefly
        // absent from the table while a run is in progress.
        $job = ScrapingJob::updateOrCreate(
            ['organization_id' => $organization->id, 'source_type' => $sourceType],
            ['status' => 'pending', 'started_at' => null, 'finished_at' => null, 'records_found' => 0, 'error_message' => null],
        );
        $job->logs()->delete();

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

            $html = $this->getHtml($url);

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
     * Fetch a URL's HTML. Always live - no caching, so this always reflects
     * whatever the bank is currently publishing.
     */
    private function getHtml(string $url): string
    {
        return (string) $this->httpClient->get($url)->getBody();
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
