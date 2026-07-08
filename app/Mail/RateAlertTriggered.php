<?php

namespace App\Mail;

use App\Models\CurrencyRate;
use App\Models\RateAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RateAlertTriggered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RateAlert $alert,
        public readonly CurrencyRate $rate
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(__('alerts.email.subject', ['currency' => $this->alert->currency->code]))
            ->view('emails.rate-alert-triggered');
    }
}
