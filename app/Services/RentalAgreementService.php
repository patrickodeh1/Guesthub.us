<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the signed rental agreement for a booking: the contract body with
 * the host / guest / stay variables filled in, plus the digital-signature
 * evidence captured when the guest accepted it (typed name, IP, browser,
 * device id, timestamp, agreement version). Rendered on-screen and, when a
 * PDF engine is installed, as a downloadable PDF.
 */
class RentalAgreementService
{
    public function viewData(Booking $booking): array
    {
        $booking->loadMissing('property');

        $property = $booking->property;
        $hostName = User::agreementHostName();
        $websiteName = \App\Support\Branding::siteName();
        $idType = $booking->id_type === 'passport' ? 'Passport' : 'State-issued ID';

        $template = $this->templateFor($booking, $property);
        $fullLayout = str_contains($template, '[[signed_name]]');
        $signedAtObj = $booking->localTimestamp($booking->contract_accepted_at);
        $sigFmt = fn ($v) => e(filled($v) ? $v : '-');
        $idImg = $this->idImageDataUri($booking);

        $contractHtml = strtr(
            $template,
            [
                '[[host_name]]' => $hostName,
                '[[website_name]]' => $websiteName,
                '[[guest_name]]' => $booking->guest_name,
                '[[guest_first_name]]' => trim(explode(' ', trim((string) $booking->guest_name))[0] ?? ''),
                '[[property_name]]' => $property?->name ?? '',
                '[[property_address]]' => $property?->fullAddress() ?? '',
                '[[reservation_id]]' => $booking->reservation_id,
                '[[booking_id]]' => $booking->booking_id,
                '[[check_in_date]]' => $booking->check_in_date?->format('M d, Y') ?? '',
                '[[check_out_date]]' => $booking->check_out_date?->format('M d, Y') ?? '',
                '[[check_in_time]]' => $booking->effectiveCheckinTimeFormatted(),
                '[[check_out_time]]' => $booking->effectiveCheckoutTimeFormatted(),
                '[[guest_id_type]]' => $idType,
                '[[signed_name]]' => e($booking->contract_signed_name ?: $booking->guest_name),
                '[[signed_at]]' => e($signedAtObj?->format('M j, Y g:i A T') ?? '-'),
                '[[signed_ip]]' => $sigFmt($booking->contract_signed_ip),
                '[[signed_device_id]]' => $sigFmt($booking->contract_signed_device_id),
                '[[signed_browser]]' => $sigFmt($booking->contract_signed_user_agent),
                '[[agreement_version]]' => e($booking->contract_version ?: '1'),
                '[[id_photo]]' => $idImg
                    ? '<img src="'.$idImg.'" alt="Government-issued ID" class="idphoto">'
                    : '<span class="muted">Not yet provided</span>',
            ]
        );

        return [
            'booking' => $booking,
            'property' => $property,
            'hostName' => $hostName,
            'websiteName' => $websiteName,
            'contractHtml' => $contractHtml,
            'idType' => $idType,
            'fullLayout' => $fullLayout,
            'signedAt' => $booking->localTimestamp($booking->contract_accepted_at),
            'agreementVersion' => $booking->contract_version,
            'idImage' => $this->idImageDataUri($booking),
        ];
    }

    /** Starter agreement in the same layout as the signed agreement. Hosts edit this per property. */
    public function defaultTemplate(?string $terms = null): string
    {
        $terms = filled($terms) ? $terms : '<p>Enter the rental terms for this property here.</p>';

        $html = <<<'HTML'
<h2>Reservation details</h2>
<table class="meta">
<tr><td class="k">Guest</td><td>[[guest_name]]</td></tr>
<tr><td class="k">Reservation ID</td><td>[[reservation_id]]</td></tr>
<tr><td class="k">Booking ID</td><td>[[booking_id]]</td></tr>
<tr><td class="k">Property</td><td>[[property_name]] - [[property_address]]</td></tr>
<tr><td class="k">Check-in</td><td>[[check_in_date]] at [[check_in_time]]</td></tr>
<tr><td class="k">Check-out</td><td>[[check_out_date]] at [[check_out_time]]</td></tr>
<tr><td class="k">Government ID</td><td>[[guest_id_type]]</td></tr>
</table>
<h2>Terms and conditions</h2>
@@TERMS@@
<h2>Government-issued ID on file</h2>
<p>[[id_photo]]</p>
<h2>Digital signature</h2>
<div class="sig">
<div class="muted">Signed by</div>
<div class="name">[[signed_name]]</div>
<table class="meta">
<tr><td class="k">Signed at</td><td>[[signed_at]]</td></tr>
<tr><td class="k">IP address</td><td>[[signed_ip]]</td></tr>
<tr><td class="k">Device ID</td><td>[[signed_device_id]]</td></tr>
<tr><td class="k">Browser</td><td>[[signed_browser]]</td></tr>
<tr><td class="k">Agreement version</td><td>[[agreement_version]]</td></tr>
</table>
</div>
HTML;

        return str_replace('@@TERMS@@', $terms, $html);
    }

    /** Signed text wins, then the property's own agreement, then the global one. */
    public function templateFor(Booking $booking, $property = null): string
    {
        // Lease bookings must always use the exact contract stored on the offer
        // that was emailed and signed, never the property's short-stay template.
        if ($booking->lease_offer_id) {
            $booking->loadMissing('leaseOffer.installments', 'leaseOffer.application', 'leaseOffer.property.owner');
            if ($booking->leaseOffer) {
                return app(\App\Services\LeaseOfferService::class)->renderContract($booking->leaseOffer);
            }
        }

        if (filled($booking->contract_snapshot)) {
            return $booking->contract_snapshot;
        }
        if ($property && filled($property->rental_agreement)) {
            return $property->rental_agreement;
        }
        $global = (string) Setting::getValue('legal_rental_contract_content', '');
        return filled($global) ? $global : $this->defaultTemplate();
    }

    public function isRequired(Booking $booking): bool
    {
        $booking->loadMissing('property');
        return ! ($booking->property?->skip_rental_agreement);
    }

    public function renderHtml(Booking $booking, bool $pdfMode = false): string
    {
        return view('agreements.rental-agreement', array_merge(
            $this->viewData($booking),
            ['pdfMode' => $pdfMode]
        ))->render();
    }

    public function isPdfAvailable(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public function renderPdf(Booking $booking): string
    {
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ]);

        $dompdf->loadHtml($this->renderHtml($booking, true), 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Front ID photo as a data URI so it can be embedded in the agreement
     * (and the PDF) without exposing the private file publicly.
     */
    public function idImageDataUri(Booking $booking): ?string
    {
        $path = $booking->photo_id_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';
        $data = Storage::disk('local')->get($path);

        if (! $data) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }
}
