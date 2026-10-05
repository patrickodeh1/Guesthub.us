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
                'message' => $this->linkify($this->message),
                'propertyName' => $this->propertyName,
            ]);
    }

    /** Email can show link text, unlike SMS, so turn bare URLs into markdown links. */
    protected function linkify(string $text): string
    {
        $text = preg_replace('/Check in now:\s*(https?:\/\/\S+)/i', '[Check in now]($1)', $text) ?? $text;

        return preg_replace('/(?<!\]\()(https?:\/\/[^\s)]+)/', '[Open link]($1)', $text) ?? $text;
    }
}
