<?php

namespace App\Services;

use App\Enums\CurrencyCode;
use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use App\Models\ScrapingJob;
use App\Parsers\RateParserFactory;

class RateScraper
{
    // Currency-code aliases, applied before a row is matched against CurrencyCode::codes().
    private const CURRENCY_ALIASES = [
        'RUB' => 'RUR',

        // Armswissbank quotes the offshore yuan (CNH) where every other bank quotes CNY.
        'CNH' => 'CNY',
    ];

    public function __construct(
        private RateParserFactory $parsers,
        private ScraperHttpClient $http,
    ) {}

    // Scrape currency rates for an organization.
    public function scrape(Organization $organization, string $sourceType = 'currency_rates'): ScrapingJob
    {
        $job = ScrapingJob::updateOrCreate(
            ['organization_id' => $organization->id, 'source_type' => $sourceType],
            ['status' => 'pending', 'started_at' => null, 'finished_at' => null, 'records_found' => 0, 'error_message' => null],
        );
        $job->logs()->delete();

        try {
            $job->markAsRunning();
            $job->log('info', "Starting to scrape {$organization->name} - {$sourceType}");

            // Get the organization source
            $source = $organization->sources()
                ->where('source_type', $sourceType)
                ->where('is_active', true)
                ->first();

            if (! $source) {
                throw new \RuntimeException("Source '{$sourceType}' not found for {$organization->name}");
            }

            $url = $source->getFullUrl();
            $job->log('info', "Fetching from: {$url}");

            $html = $this->http->get($url, $source->request_headers ?? []);

            // Parse and extract rates
            $recordsFound = $this->parseAndSaveRates($organization, $html, $url, $job);

            $job->log('info', "Successfully parsed {$recordsFound} records");

            if ($recordsFound === 0) {
                $job->log('warning', 'Zero records parsed - the source markup may have changed');
                AdminNotifier::zeroRecordsScraped($organization->name, $sourceType);
            }

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

        $rows = $this->parsers->for($organization)->parse($html);

        foreach ($rows as $row) {
            try {
                $currencyCode = $this->normalizeCurrencyCode($row['code']);
                $rateType = $row['rate_type'] ?? RateType::CASH->value;
                $buyRate = (float) $row['buy'];
                $sellRate = (float) $row['sell'];

                if (! in_array($currencyCode, CurrencyCode::codes(), true)) {
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

    // Normalize a currency code to its canonical ISO form.
    private function normalizeCurrencyCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return self::CURRENCY_ALIASES[$code] ?? $code;
    }
}
