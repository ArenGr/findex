<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class TripReviewPrompt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Organization>  $organizations  Every
     *                                                        agency that actually replied to this request - we don't know
     *                                                        which one the customer ended up booking with (booking happens
     *                                                        off-platform), so this links to all of them rather than
     *                                                        guessing one.
     */
    public function __construct(
        public readonly QuoteRequest $quoteRequest,
        public readonly Collection $organizations,
    ) {}

    public function build(): self
    {
        $unsubscribeUrl = $this->quoteRequest->user
            ? URL::signedRoute('tourism.review-prompts.unsubscribe', [
                'locale' => $this->quoteRequest->locale,
                'user' => $this->quoteRequest->user->getKey(),
            ])
            : null;

        return $this
            ->subject(__('tourism.email.review_prompt_subject'))
            ->view('emails.trip-review-prompt', [
                'quoteRequest' => $this->quoteRequest,
                'organizations' => $this->organizations,
                'unsubscribeUrl' => $unsubscribeUrl,
            ]);
    }
}
