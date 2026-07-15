<?php

namespace App\Mail;

use App\Models\AutoInsuranceQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AutoInsuranceQuoteInterest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly AutoInsuranceQuote $quote)
    {
    }

    public function build(): self
    {
        $request = $this->quote->autoInsuranceRequest;

        return $this
            ->subject(__('auto_insurance.email.interest_subject'))
            ->view('emails.auto-insurance-quote-interest', [
                'quote' => $this->quote,
                'request' => $request,
            ]);
    }
}
