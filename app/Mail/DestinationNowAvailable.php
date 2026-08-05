<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DestinationNowAvailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $destinationCountry,
        public readonly ?string $name = null,
        public readonly ?string $unsubscribeUrl = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('tourism.email.destination_available_subject', ['destination' => __('destinations.'.$this->destinationCountry)]))
            ->view('emails.destination-now-available', [
                'destinationCountry' => $this->destinationCountry,
                'name' => $this->name,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
    }
}
