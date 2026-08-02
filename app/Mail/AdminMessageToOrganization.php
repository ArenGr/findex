<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminMessageToOrganization extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * $messageSubject/$messageBody rather than $subject/$body - Mailable
     * already defines a subject() method, so a same-named public property
     * would collide with it.
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly string $messageSubject,
        public readonly string $messageBody,
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {
    }

    public function build(): self
    {
        return $this
            ->from($this->fromAddress, $this->fromName)
            ->subject($this->messageSubject)
            ->view('emails.admin-message-to-organization');
    }
}
