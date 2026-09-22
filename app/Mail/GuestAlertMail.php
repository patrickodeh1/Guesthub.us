<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GuestAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $eventLabel,
        public string $message,
        public ?string $subjectLine = null,
        public ?string $propertyName = null,
    ) {}

    public function build()
    {
        // The sender and the email header already show the app name, so the
        // subject leads with the guest instead of repeating it.
        return $this->subject($this->subjectLine ?: $this->eventLabel)
            ->markdown('emails.guest-alert')
            ->with([
                'eventLabel' => $this->eventLabel,
                'message' => $this->message,
                'propertyName' => $this->propertyName,
            ]);
    }
}
