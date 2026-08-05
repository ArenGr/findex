<?php

namespace App\Mail;

use App\Models\ExchangeQuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExchangeQuoteRequestSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExchangeQuoteRequest $exchangeQuoteRequest,
        public readonly string $resultsUrl,
        public readonly int $partnerCount
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('exchange_quotes.email.submitted_subject'))
            ->view('emails.exchange-quote-request-submitted');
    }
}
