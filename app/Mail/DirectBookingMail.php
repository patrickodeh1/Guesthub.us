<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DirectBookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $message,
        public string $actionUrl,
        public string $actionLabel,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->markdown('emails.direct-booking')
            ->with([
                'subjectLine' => $this->subjectLine,
                'message' => $this->message,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
            ]);
    }
}
