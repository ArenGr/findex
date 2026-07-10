<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteRequestLinkResent extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Models\QuoteRequest> $quoteRequests All of
     *        this guest's still-open requests, not just the one they may
     *        have had in mind - a lost email likely means they've lost the
     *        link to every request they filed, not just the latest.
     */
    public function __construct(public readonly Collection $quoteRequests)
    {
    }

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.resend_subject'))
            ->view('emails.quote-request-link-resent');
    }
}
