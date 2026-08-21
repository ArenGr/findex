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
    /**
     * Currency-code aliases, applied before a row is matched against
     * CurrencyCode::codes().
     *
     * This app canonicalises the Russian ruble on the legacy RUR, not the
     * current ISO RUB - see CurrencyCode::RUB and the
     * fix_rub_currency_code_to_rur migration. Armenian bank sites use both
     * spellings, so the ISO one has to be folded in here.
     *
     * It previously mapped only 'RUR' => 'RUR', which is a no-op: every bank
     * publishing RUB (IDBank and AMIO among them) had its ruble row quietly
     * discarded as an untracked currency, and the comparison pages simply
     * showed no ruble for those banks.
     */
    private const CURRENCY_ALIASES = [
        'RUB' => 'RUR',

        // Armswissbank quotes the offshore yuan (CNH) where every other
        // bank quotes CNY. They are the same currency traded in two
        // markets; for a retail exchange comparison the distinction is
        // immaterial, and without this the bank shows no yuan at all.
        'CNH' => 'CNY',
    ];

    public function __construct(
        private RateParserFactory $parsers,
        private ScraperHttpClient $http,
    ) {}

    /**
     * Scrape currency rates for an organization.
     */
    public function scrape(Organization $organization, string $sourceType = 'currency_rates'): ScrapingJob
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

            // The fetch succeeded and the parser didn't throw, but found
            // nothing - most likely the site's markup changed under the
            // parser. Left unflagged, this looks identical to "rates didn't
            // change since last time" with no error anywhere.
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
