<?php

namespace App\Services;

use App\Models\MortgageOffer;
use App\Models\MortgageOfferHistory;
use App\Models\Organization;
use App\Models\ScrapingJob;
use App\Parsers\MortgageParserFactory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    private Client $httpClient;

    public function __construct(private MortgageParserFactory $parsers)
    {
        $this->httpClient = new Client([
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
     */
    private function getHtml(string $url, ScrapingJob $job): string
    {
        $path = $this->fixturePath($url);
        $disk = Storage::disk(self::FIXTURE_DISK);

        if ($disk->exists($path)) {
            $job->log('info', "Using local fixture for: {$url}");
            return $disk->get($path);
        }

        $job->log('info', "No local fixture - fetching from site: {$url}");
        $html = (string) $this->httpClient->get($url)->getBody();

        $disk->put($path, $html);

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
