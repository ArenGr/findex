<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuoteRequest $quoteRequest,
        public readonly string $resultsUrl,
        public readonly int $partnerCount
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.submitted_subject'))
            ->view('emails.quote-request-submitted');
    }
}
