<?php

namespace App\Services;

use App\Mail\DirectBookingMail;
use App\Models\LeaseApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeaseApplicationNotificationService
{
    public function submitted(LeaseApplication $application): void
    {
        $application->loadMissing(['property.owner', 'listing']);
        $url = route('public.lease-application.fee', [$application->id, $application->token]);
        $this->send(
            (string) $application->applicant_email,
            'Lease application received',
            'Your application for '.$application->listing->public_title.' has been received. The host will review it and contact you with any updates.',
            $url,
            'View application',
            $application,
        );

        $owner = $application->property->owner;
        if ($owner && filled($owner->email)) {
            $this->send(
                $owner->email,
                'New lease application',
                'A new lease application was submitted for '.$application->listing->public_title.'.',
                route('admin.lease-applications.show', $application),
                'Review application',
                $application,
            );
        }
    }

    public function statusChanged(LeaseApplication $application): void
    {
        $this->send(
            (string) $application->applicant_email,
            'Lease application status updated',
            'The host updated the status of your lease application to '.str_replace('_', ' ', $application->status).'.',
            route('public.lease-application.fee', [$application->id, $application->token]),
            'View application',
            $application,
        );
    }

    public function feePaid(LeaseApplication $application): void
    {
        $this->send(
            (string) $application->applicant_email,
            'Lease application fee received',
            'Your lease application fee has been received. The fee is non-refundable unless the host states otherwise.',
            route('public.lease-application.fee', [$application->id, $application->token]),
            'View application',
            $application,
        );
    }

    public function screeningRequested(LeaseApplication $application): void
    {
        $this->send(
            (string) $application->applicant_email,
            'Complete your rental screening',
            'The host has asked you to complete screening using their screening provider.',
            (string) $application->listing->screening_provider_url,
            'Continue to screening',
            $application,
        );
    }

    private function send(
        string $recipient,
        string $subject,
        string $message,
        string $url,
        string $action,
        LeaseApplication $application,
    ): void {
        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->send(new DirectBookingMail($subject, $message, $url, $action));
        } catch (Throwable $exception) {
            Log::warning('Lease application notification failed.', [
                'application_id' => $application->id,
                'subject' => $subject,
                'exception' => $exception::class,
            ]);
        }
    }
}
