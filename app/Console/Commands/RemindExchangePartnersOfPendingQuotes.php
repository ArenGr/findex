<?php

namespace App\Console\Commands;

use App\Models\ExchangeQuoteResponse;
use App\Services\Notifications\ExchangeNotifierInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemindExchangePartnersOfPendingQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:remind-partners';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a one-time nudge to exchange offices with a rate request still pending after 24 hours';

    private const REMIND_AFTER_HOURS = 24;

    public function handle(ExchangeNotifierInterface $notifier): int
    {
        $responses = ExchangeQuoteResponse::where('status', ExchangeQuoteResponse::STATUS_PENDING)
            ->whereNull('reminded_at')
            ->where('created_at', '<=', now()->subHours(self::REMIND_AFTER_HOURS))
            ->whereHas('exchangeQuoteRequest', fn ($query) => $query->where('expires_at', '>', now()))
            ->with(['organization', 'exchangeQuoteRequest'])
            ->get();

        foreach ($responses as $response) {
            if (! $notifier->remind($response)) {
                Log::warning('Exchange quote partner reminder failed', [
                    'exchange_quote_response_id' => $response->id,
                    'organization_id' => $response->organization_id,
                ]);
            }

            $response->update(['reminded_at' => now()]);
        }

        $this->info("Reminded {$responses->count()} exchange partner(s) of pending rate requests.");

        return self::SUCCESS;
    }
}
