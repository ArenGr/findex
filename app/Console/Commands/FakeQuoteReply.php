<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\QuoteResponse;
use Illuminate\Console\Command;

/**
 * Local-testing tool: a real offer normally arrives by a partner submitting
 * the secure response form (see PartnerResponseController), which requires
 * clicking a real link a live Telegram send would have delivered. Demo
 * partners (see TourismDemoSeeder) have a fake telegram_chat_id, so
 * SendQuoteRequestToPartnersJob's real send to them fails - but the pending
 * QuoteResponse row (with its response_token) is still created regardless of
 * delivery success, so this command just fills that row in directly to
 * preview the results page without a real Telegram round trip.
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
        {--price= : Price amount (defaults to a random sample price)}
        {--currency=USD : Price currency (AMD, USD, or EUR)}
        {--decline : Mark the response as declined instead of offering a quote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a partner submitting their secure response form, for local testing without a live Telegram bot';

    public function handle(): int
    {
        $organization = Organization::where('slug', $this->argument('organization_slug'))->first();

        if (!$organization) {
            $this->error("Organization with slug '{$this->argument('organization_slug')}' not found.");

            return self::FAILURE;
        }

        $response = QuoteResponse::where('quote_request_id', $this->argument('quote_request_id'))
            ->where('organization_id', $organization->id)
            ->first();

        if (!$response) {
            $this->error("No pending response found for quote request #{$this->argument('quote_request_id')} and organization '{$organization->slug}'. Run SendQuoteRequestToPartnersJob for that request first.");

            return self::FAILURE;
        }

        if ($this->option('decline')) {
            $response->update(['status' => QuoteResponse::STATUS_DECLINED]);
            $this->info("Marked {$organization->name}'s response to quote request #{$response->quote_request_id} as declined.");

            return self::SUCCESS;
        }

        $response->update([
            'reply_text' => "Thanks for your request! Let us know if you'd like to book.",
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $response->suggestions()->create([
            'price_amount' => $this->option('price') ?: $this->samplePrice(),
            'price_currency' => $this->option('currency'),
            'offered_hotel_name' => 'Sample Hotel',
            'flight_details' => 'Direct flight, sample airline',
            'inclusions' => 'Breakfast, airport transfer',
        ]);

        $this->info("Recorded {$organization->name}'s offer for quote request #{$response->quote_request_id}.");
        $this->line('View it at: ' . $response->quoteRequest->signedResultsUrl());

        return self::SUCCESS;
    }

    private function samplePrice(): int
    {
        return random_int(4, 12) * 50 + 400;
    }
}
