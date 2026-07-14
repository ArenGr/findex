<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DestinationNowAvailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $destinationCountry)
    {
    }

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.destination_available_subject', ['destination' => __('destinations.' . $this->destinationCountry)]))
            ->view('emails.destination-now-available', ['destinationCountry' => $this->destinationCountry]);
    }
}
