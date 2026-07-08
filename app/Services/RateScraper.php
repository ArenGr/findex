<?php

namespace App\Services;

use App\Enums\CurrencyCode;
use App\Enums\RateType;
use App\Models\Organization;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\ScrapingJob;
use App\Parsers\RateParserFactory;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RateScraper
{
    /**
     * Currency-code aliases. Some banks still publish the legacy RUR code for
     * the Russian ruble; we store everything under the current ISO code.
     */
    private const CURRENCY_ALIASES = [
        'RUR' => 'RUB',
    ];

    /**
     * Retries for transient failures only (connection/timeout errors, 5xx,
     * 429) - a plain 403/404 means the site is actively blocking us or the
     * URL is wrong, and hammering it again won't help. Kept short since
     * this runs in a daily cron job for many organizations in sequence, not
     * as a background retry queue.
     */
    private const MAX_RETRIES = 2;

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

    public function __construct(private RateParserFactory $parsers)
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
            // Some sites (e.g. Ameriabank) gate the first request behind a
            // WAF challenge that sets a cookie and redirects to the same
            // URL; the retry only succeeds if that cookie is sent back.
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                // Only advertise encodings Guzzle/cURL can transparently decode.
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

        // A network-level failure (DNS, connection refused, timeout, ...)
        // has no response at all - always worth a retry.
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
     * Scrape currency rates for an organization.
     */
    public function scrape(Organization $organization, string $sourceType = 'currency_rates'): ScrapingJob
    {
        // Create a new scraping job
        $job = $organization->scrapingJobs()->create([
            'source_type' => $sourceType,
            'status' => 'pending',
        ]);

        try {
            $job->markAsRunning();
            $job->log('info', "Starting to scrape {$organization->name} - {$sourceType}");

            // Get the organization source
            $source = $organization->sources()
                ->where('source_type', $sourceType)
                ->where('is_active', true)
                ->first();

            if (!$source) {
                throw new \RuntimeException("Source '{$sourceType}' not found for {$organization->name}");
            }

            $url = $source->getFullUrl();
            $job->log('info', "Fetching from: {$url}");

            // Fetch HTML (local fixture first, network only on a cache miss)
            $html = $this->getHtml($url, $job);

            // Parse and extract rates
            $recordsFound = $this->parseAndSaveRates($organization, $html, $url, $job);

            $job->log('info', "Successfully parsed {$recordsFound} records");

            // Mark source as last scraped
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
            // Storage::put creates the directory automatically.
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
     * Parse HTML and save currency rates to database.
     *
     * @return int Number of records saved
     */
    private function parseAndSaveRates(
        Organization $organization,
        string $html,
        string $sourceUrl,
        ScrapingJob $job
    ): int {
        $recordsCount = 0;

        // Each organization has its own HTML/JSON structure, so parsing is
        // delegated to an organization-specific parser.
        $rows = $this->parsers->for($organization)->parse($html);

        foreach ($rows as $row) {
            try {
                $currencyCode = $this->normalizeCurrencyCode($row['code']);
                $rateType = $row['rate_type'] ?? RateType::CASH->value;
                $buyRate = (float) $row['buy'];
                $sellRate = (float) $row['sell'];

                // Enforced for every organization, regardless of what its
                // parser extracted - only these currencies are tracked.
                if (!in_array($currencyCode, CurrencyCode::codes(), true)) {
                    $job->log('debug', "Skipping untracked currency: {$currencyCode}");
                    continue;
                }

                if ($buyRate <= 0 || $sellRate <= 0) {
                    continue;
                }

                $currency = Currency::firstOrCreate(
                    ['code' => $currencyCode],
                    [
                        'name' => $currencyCode,
                        'is_active' => true,
                        'sort_order' => array_search($currencyCode, CurrencyCode::codes(), true) + 1,
                    ]
                );

                $rate = CurrencyRate::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'currency_id' => $currency->id,
                        'rate_type' => $rateType,
                    ],
                    [
                        'buy_rate' => $buyRate,
                        'sell_rate' => $sellRate,
                        'source_url' => $sourceUrl,
                        'scraped_at' => now(),
                    ]
                );

                // Only append history when the rate is new or actually changed,
                // so the history table doesn't fill up with identical rows.
                if ($rate->wasRecentlyCreated || $rate->wasChanged(['buy_rate', 'sell_rate'])) {
                    CurrencyRateHistory::createFromRate($rate);
                }

                $recordsCount++;

                $job->log('debug', "Saved rate: {$currencyCode} ({$rateType}) - Buy: {$buyRate}, Sell: {$sellRate}");
            } catch (\Throwable $e) {
                $job->log('warning', "Error parsing rate row: {$e->getMessage()}");
            }
        }

        return $recordsCount;
    }

    /**
     * Normalize a currency code to its canonical ISO form.
     */
    private function normalizeCurrencyCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return self::CURRENCY_ALIASES[$code] ?? $code;
    }
}
