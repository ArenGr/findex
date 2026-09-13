<?php

namespace App\Mail;

use App\Models\QuoteResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Tells an agency a travel request is waiting for it.
class TravelRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuoteResponse $quoteResponse,
        public readonly string $inboxUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.agency_request_subject'))
            ->view('emails.travel-request-received');
    }
}
