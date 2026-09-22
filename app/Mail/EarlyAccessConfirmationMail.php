<?php

namespace App\Mail;

use App\Models\EarlyAccessLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EarlyAccessConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EarlyAccessLead $lead,
    ) {}

    public function build()
    {
        return $this->subject('We received your request for early access')
            ->markdown('emails.early-access-confirmation')
            ->with([
                'lead' => $this->lead,
            ]);
    }
}
