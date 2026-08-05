<?php

namespace App\Console\Commands;

use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Local-testing tool: a real offer normally arrives by a partner submitting
 * the secure response form (see ExchangePartnerResponseController), which
 * requires clicking a real link a live Telegram send would have delivered.
 * Demo partners (see ExchangeOrgSeeder) have a fake telegram_chat_id, so
 * TelegramExchangeNotifier's real send to them fails - but the pending
 * ExchangeQuoteResponse row (with its response_token and posted_rate
 * snapshot) is still created regardless of delivery success, so this
 * command just fills that row in directly to preview the results page
 * without a real Telegram round trip. Mirrors tourism:fake-reply
 * (FakeQuoteReply.php) for the exchange-quote domain.
 */
class ExchangeFakeReply extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:fake-reply
        {exchange_quote_request_id : ID of the exchange quote request to reply to}
        {organization_slug : Slug of the partner organization replying}
        {--rate= : Offered rate (defaults to a small realistic bump over the posted rate)}
        {--decline : Mark the response as declined instead of offering a rate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an exchange office submitting their secure response form, for local testing without a live Telegram bot';

    public function handle(): int
    {
        $organization = Organization::where('slug', $this->argument('organization_slug'))->first();

        if (! $organization) {
            $this->error("Organization with slug '{$this->argument('organization_slug')}' not found.");

            return self::FAILURE;
        }

        $response = ExchangeQuoteResponse::where('exchange_quote_request_id', $this->argument('exchange_quote_request_id'))
            ->where('organization_id', $organization->id)
            ->first();

        if (! $response) {
            $this->error("No pending response found for exchange quote request #{$this->argument('exchange_quote_request_id')} and organization '{$organization->slug}'. Run SendExchangeQuoteToPartnersJob for that request first.");

            return self::FAILURE;
        }

        if ($this->option('decline')) {
            $response->update(['status' => ExchangeQuoteResponse::STATUS_DECLINED]);
            $this->info("Marked {$organization->name}'s response to exchange quote request #{$response->exchange_quote_request_id} as declined.");

            return self::SUCCESS;
        }

        $postedRate = (float) $response->posted_rate;
        $offeredRate = $this->option('rate') !== null ? (float) $this->option('rate') : $this->sampleRate($postedRate);

        // Mirrors ExchangePartnerResponseController::store()'s own
        // 'min:' . $response->posted_rate validation rule - fake data
        // shouldn't be able to produce a state the real form can't.
        if ($offeredRate < $postedRate) {
            $this->error("Offered rate ({$offeredRate}) can't be below the posted rate ({$postedRate}).");

            return self::FAILURE;
        }

        $response->update([
            'offered_rate' => $offeredRate,
            'reply_text' => 'Happy to offer you this rate today.',
            'status' => ExchangeQuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $this->info("Recorded {$organization->name}'s offer ({$offeredRate}) for exchange quote request #{$response->exchange_quote_request_id}.");
        $this->line('View it at: '.$response->exchangeQuoteRequest->signedResultsUrl());

        return self::SUCCESS;
    }

    private function sampleRate(float $postedRate): float
    {
        return round($postedRate + random_int(2, 15) / 10, 4);
    }
}
