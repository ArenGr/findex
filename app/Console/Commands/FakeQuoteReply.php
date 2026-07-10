<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Console\Command;

/**
 * Local-testing tool: a real reply normally arrives by a partner replying
 * in Telegram (see PartnerReplyHandler), which requires a live chat with the
 * bot. Demo partners (see TourismDemoSeeder) have a fake telegram_chat_id,
 * so SendQuoteRequestToPartnersJob's real send to them fails and no
 * QuoteResponse row gets created - this command creates/updates one
 * directly so the results page can be previewed without a real Telegram round trip.
 */
class FakeQuoteReply extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tourism:fake-reply
        {quote_request_id : ID of the quote request to reply to}
        {organization_slug : Slug of the partner organization replying}
        {--text= : Reply text (defaults to a canned sample quote)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a partner Telegram reply to a quote request, for local testing without a live Telegram bot';

    public function handle(): int
    {
        $quoteRequest = QuoteRequest::find($this->argument('quote_request_id'));

        if (!$quoteRequest) {
            $this->error("Quote request #{$this->argument('quote_request_id')} not found.");

            return self::FAILURE;
        }

        $organization = Organization::where('slug', $this->argument('organization_slug'))->first();

        if (!$organization) {
            $this->error("Organization with slug '{$this->argument('organization_slug')}' not found.");

            return self::FAILURE;
        }

        $text = $this->option('text') ?: "Thanks for your request! We can offer \${$this->samplePrice()} per person for this trip, all-inclusive. Let us know if you'd like to book.";

        QuoteResponse::updateOrCreate(
            ['quote_request_id' => $quoteRequest->id, 'organization_id' => $organization->id],
            ['reply_text' => $text, 'responded_at' => now()]
        );

        $this->info("Recorded {$organization->name}'s reply to quote request #{$quoteRequest->id}.");
        $this->line('View it at: ' . $quoteRequest->signedResultsUrl());

        return self::SUCCESS;
    }

    private function samplePrice(): int
    {
        return random_int(4, 12) * 50 + 400;
    }
}
