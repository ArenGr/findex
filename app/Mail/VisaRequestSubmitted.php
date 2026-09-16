<?php

namespace App\Mail;

use App\Models\VisaRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisaRequestSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly VisaRequest $visaRequest,
        public readonly string $resultsUrl,
        public readonly int $partnerCount
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('visa.email.submitted_subject'))
            ->view('emails.visa-request-submitted');
    }
}
