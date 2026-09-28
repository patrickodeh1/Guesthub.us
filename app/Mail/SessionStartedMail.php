<?php

namespace App\Mail;

use App\Helpers\TimezoneHelper;
use App\Models\CleaningSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $propertyName;
    public string $cleanerName;
    public string $startTime;
    public int $sessionId;

    public function __construct(public CleaningSession $session)
    {
        $session->loadMissing(['property', 'housekeeper']);

        $this->propertyName = $session->property->name ?? 'Unknown Property';
        $this->cleanerName = $session->housekeeper->name ?? 'Unassigned';

        $tz = $session->property->timezone ?? config('app.timezone');
        $this->startTime = TimezoneHelper::format(
            $session->started_at ?? now(),
            $tz,
            TimezoneHelper::DATETIME
        );
        $this->sessionId = $session->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Cleaning Started – {$this->propertyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.session-started',
        );
    }
}
