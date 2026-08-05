<?php

namespace App\Mail;

use App\Models\ExchangeQuoteResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExchangeQuoteResponseReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExchangeQuoteResponse $exchangeQuoteResponse,
        public readonly string $resultsUrl
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('exchange_quotes.email.response_received_subject'))
            ->view('emails.exchange-quote-response-received');
    }
}
