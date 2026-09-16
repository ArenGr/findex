<?php

namespace App\Mail;

use App\Models\VisaResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisaOfferReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly VisaResponse $visaResponse,
        public readonly string $resultsUrl
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('visa.email.offer_subject'))
            ->view('emails.visa-offer-received');
    }
}
