<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PhotoIdDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $side,
        public string $reason,
    ) {}

    public function build()
    {
        $sideLabel = $this->side === 'back' ? 'back' : 'front';

        return $this->subject('Action needed: your ID photo was not approved')
            ->markdown('emails.photo-id-declined')
            ->with([
                'guestName' => $this->booking->guest_name,
                'propertyName' => $this->booking->property?->name,
                'sideLabel' => $sideLabel,
                'reason' => $this->reason,
                'reuploadUrl' => route('guest.show', [
                    'booking_id' => $this->booking->booking_id,
                    'token' => $this->booking->token,
                ]),
            ]);
    }
}
