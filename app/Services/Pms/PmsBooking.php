<?php

namespace App\Services\Pms;

/**
 * A single reservation as normalized from whichever PMS provider is active.
 * Controllers/jobs only ever see this shape — never Channex's or NextPax's
 * raw payload — so swapping providers later never touches consuming code.
 */
class PmsBooking
{
    public function __construct(
        public readonly string $externalBookingId,
        public readonly string $externalPropertyId,
        public readonly ?string $guestName,
        public readonly ?string $guestEmail,
        public readonly ?string $guestPhone,
        public readonly string $checkInDate,
        public readonly string $checkOutDate,
        public readonly ?string $status = null,
        public readonly array $raw = [],
        // Channex-specific: the Booking Revision ID, distinct from the
        // Booking ID. Acknowledgement must target the revision, not the
        // booking (POST /booking_revisions/:id/ack) — using externalBookingId
        // there hits the wrong endpoint/resource. Null for providers (e.g.
        // NextPax) with no revision concept.
        public readonly ?string $revisionId = null,
        // The guest-facing reservation code from the OTA itself (e.g. Airbnb's
        // confirmation code). Distinct from externalBookingId, which is
        // Channex's own internal booking ID. Null for offline/non-OTA bookings.
        public readonly ?string $otaReservationCode = null,
        public readonly ?string $otaName = null,
    ) {
    }
}
