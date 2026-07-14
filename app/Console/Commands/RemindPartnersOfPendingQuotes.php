<?php

namespace App\Console\Commands;

use App\Models\QuoteResponse;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemindPartnersOfPendingQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tourism:remind-partners';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a one-time nudge to partners with a quote request still pending after 24 hours';

    /**
     * A response older than this with no reply yet gets exactly one nudge
     * (see reminded_at) - not a repeating spam loop, and only while the
     * underlying request is still open (a reminder for an already-expired
     * request would just be noise).
     */
    private const REMIND_AFTER_HOURS = 24;

    public function handle(PartnerNotifierInterface $notifier): int
    {
        $responses = QuoteResponse::where('status', QuoteResponse::STATUS_PENDING)
            ->whereNull('reminded_at')
            ->where('created_at', '<=', now()->subHours(self::REMIND_AFTER_HOURS))
            ->whereHas('quoteRequest', fn ($query) => $query->where('expires_at', '>', now()))
            ->with(['organization', 'quoteRequest'])
            ->get();

        foreach ($responses as $response) {
            if (!$notifier->remind($response)) {
                Log::warning('Quote request partner reminder failed', [
                    'quote_response_id' => $response->id,
                    'organization_id' => $response->organization_id,
                ]);
            }

            // Marked regardless of delivery success - a failed reminder
            // (e.g. the partner's chat is gone) will fail identically on
            // every future run, so retrying it isn't a repeating nudge to
            // the partner, just a repeating no-op that clutters the logs.
            $response->update(['reminded_at' => now()]);
        }

        $this->info("Reminded {$responses->count()} partner(s) of pending quote requests.");

        return self::SUCCESS;
    }
}
