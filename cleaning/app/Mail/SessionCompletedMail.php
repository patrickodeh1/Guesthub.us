<?php

namespace App\Mail;

use App\Helpers\TimezoneHelper;
use App\Models\CleaningSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $propertyName;
    public string $cleanerName;
    public string $completionTime;
    public string $reportUrl;
    public int $sessionId;
    public string $statusText;

    public function __construct(public CleaningSession $session)
    {
        $session->loadMissing(['property', 'housekeeper']);

        $this->propertyName = $session->property->name ?? 'Unknown Property';
        $this->cleanerName = $session->housekeeper->name ?? 'Unassigned';

        $tz = $session->property->timezone ?? config('app.timezone');
        $this->completionTime = TimezoneHelper::format(
            $session->ended_at ?? now(),
            $tz,
            TimezoneHelper::DATETIME
        );
        $this->sessionId = $session->id;

        $session->ensureReportToken();
        $this->reportUrl = route('reports.sessions.show', ['token' => $session->report_token]);

        // Determine status text from ChecklistReport data
        $hasUnresolved = \App\Models\ChecklistReport::where('session_id', $session->id)
            ->whereIn('type', ['damage', 'maintenance', 'supplies', 'safety'])
            ->where('status', '!=', 'resolved')
            ->exists();

        $this->statusText = $hasUnresolved ? 'Ready with Exceptions' : 'Ready';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Cleaning Completed – {$this->propertyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.session-completed',
        );
    }
}
