<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteRequestLinkResent extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, QuoteRequest>  $quoteRequests  All of
     *                                                        this guest's still-open requests, not just the one they may
     *                                                        have had in mind - a lost email likely means they've lost the
     *                                                        link to every request they filed, not just the latest.
     */
    public function __construct(public readonly Collection $quoteRequests) {}

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.resend_subject'))
            ->view('emails.quote-request-link-resent');
    }
}
