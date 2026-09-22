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
        $websiteName = Setting::getValue('site_name') ?: config('app.name');
        $idType = $booking->id_type === 'passport' ? 'Passport' : 'State-issued ID';

        $contractHtml = strtr(
            Setting::getValue('legal_rental_contract_content', '<p>Rental contract content has not been configured yet.</p>'),
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
            ]
        );

        return [
            'booking' => $booking,
            'property' => $property,
            'hostName' => $hostName,
            'websiteName' => $websiteName,
            'contractHtml' => $contractHtml,
            'idType' => $idType,
            'signedAt' => $booking->localTimestamp($booking->contract_accepted_at),
            'agreementVersion' => $booking->contract_version,
            'idImage' => $this->idImageDataUri($booking),
        ];
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
