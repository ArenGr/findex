<?php

namespace App\Mail;

use App\Models\ExchangeQuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExchangeQuoteLinkResent extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, ExchangeQuoteRequest>  $exchangeQuoteRequests
     *                                                                        All of this guest's still-open requests, not just the one they
     *                                                                        may have had in mind - same reasoning as QuoteRequestLinkResent.
     */
    public function __construct(public readonly Collection $exchangeQuoteRequests) {}

    public function build(): self
    {
        return $this
            ->subject(__('exchange_quotes.email.resend_subject'))
            ->view('emails.exchange-quote-link-resent');
    }
}
