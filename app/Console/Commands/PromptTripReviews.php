<?php

namespace App\Console\Commands;

use App\Mail\TripReviewPrompt;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class PromptTripReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tourism:prompt-reviews';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email customers whose trip dates have passed, prompting a review of the agency that quoted them';

    public function handle(): int
    {
        $quoteRequests = QuoteRequest::where('check_out', '<', now())
            ->whereNull('review_prompted_at')
            ->whereHas('responses', fn ($query) => $query->where('status', QuoteResponse::STATUS_RESPONDED))
            ->with(['user', 'responses' => fn ($query) => $query->where('status', QuoteResponse::STATUS_RESPONDED)->with('organization')])
            ->get();

        $sent = 0;

        foreach ($quoteRequests as $quoteRequest) {
            $requesterEmail = $quoteRequest->requester_email;

            if ($requesterEmail) {
                $organizations = $quoteRequest->responses->pluck('organization')->unique('id')->values();

                Mail::to($requesterEmail)
                    ->locale($quoteRequest->locale)
                    ->send(new TripReviewPrompt($quoteRequest, $organizations));

                $sent++;
            }

            $quoteRequest->update(['review_prompted_at' => now()]);
        }

        $this->info("Sent {$sent} review prompt(s), out of {$quoteRequests->count()} eligible trip(s).");

        return self::SUCCESS;
    }
}
