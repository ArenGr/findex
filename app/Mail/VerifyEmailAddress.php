<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmailAddress extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user, public readonly string $verificationUrl)
    {
    }

    public function build(): self
    {
        return $this
            ->subject(__('auth.verify_email.subject'))
            ->view('emails.verify-email-address');
    }
}
