<?php

namespace App\Mail;

use App\Models\QuoteResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an agency a travel request is waiting for it.
 *
 * The fallback for agencies with no Telegram chat connected, which used to
 * be nobody - matching required a connected chat, so Telegram was the only
 * channel that could ever be needed. Now that an agency can work entirely
 * from its dashboard inbox (see Organization::tourismPartnersForDestination),
 * one that never connected Telegram would otherwise be matched to requests
 * and never told about them.
 */
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
