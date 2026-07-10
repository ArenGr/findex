<?php

namespace App\Mail;

use App\Models\QuoteResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteResponseReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuoteResponse $quoteResponse,
        public readonly string $resultsUrl
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.response_received_subject'))
            ->view('emails.quote-response-received');
    }
}
