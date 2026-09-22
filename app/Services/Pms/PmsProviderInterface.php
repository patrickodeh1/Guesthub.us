<?php

namespace App\Services\Pms;

/**
 * Implemented by each channel-manager integration (Channex now, NextPax
 * later). Consuming code (sync job, webhook controller, admin sync UI) only
 * ever depends on this interface — never on a concrete provider — so
 * switching providers is: write a new class implementing this interface,
 * then flip PMS_PROVIDER in config. No other code should need to change.
 *
 * Historically this interface was documented as strictly read-only/
 * import-only. That changed with the addition of the availability/rates
 * manager: Guesthub now pushes ARI (Availability, Rates, Inventory) data
 * outward through this interface too, because Airbnb (once mapped through
 * Channex as the property's channel manager) locks its own calendar for
 * manual edits and expects updates to originate from the channel manager
 * API instead. Reservation *booking* data itself is still never written
 * back — only availability/rate/inventory data is now two-way.
 */
interface PmsProviderInterface
{
    /**
     * Fetch bookings changed since a given point (or all, if null) — used by
     * the scheduled poll job. Each provider decides what "changed since"
     * means in its own terms (e.g. Channex's booking revisions feed).
     *
     * @return PmsBooking[]
     */
    public function getBookings(?\DateTimeInterface $since = null): array;

    public function getBooking(string $externalBookingId): ?PmsBooking;

    /**
     * Some providers (Channex) require every pulled booking to be
     * acknowledged or they keep re-sending it. Providers that don't need
     * this (e.g. NextPax may not) can make this a no-op.
     */
    public function acknowledgeBooking(string $externalBookingId): void;

    /**
     * Parse an incoming webhook payload into a normalized PmsBooking (or
     * null if the payload isn't a booking event this provider cares about).
     */
    public function handleWebhookPayload(array $payload): ?PmsBooking;

    /**
     * BACKFILL ONLY. Fetch the full/current list of bookings, bypassing
     * whatever "changed since" mechanism getBookings() normally uses.
     *
     * This exists for one-time historical imports (e.g. a Channex gap where
     * bookings existed before revision tracking started and can never be
     * recovered via the revision feed, since acknowledged/older revisions
     * are permanently dropped from it). Providers whose regular
     * getBookings() already returns everything (no revision/ack concept)
     * may simply delegate this to getBookings($since = null).
     *
     * Must NOT be called from the regular scheduled sync -- only from a
     * manual, explicitly-invoked backfill command.
     *
     * @param array{arrival_date_from?: string, arrival_date_to?: string, departure_date_from?: string, departure_date_to?: string}|null $dateRange
     * @return PmsBooking[]
     */
    public function getAllBookings(?array $dateRange = null): array;

    /**
     * Fetch the room types the channel manager already has on file for a
     * given external property, so the admin availability page can offer
     * them as a pick-list instead of requiring manual UUID entry. Returns
     * a flat array of ['room_type_id' => string, 'room_type_title' =>
     * string]. Returns [] on failure (never throws) -- the UI just shows
     * "not mapped yet" in that case; there is no manual-entry fallback.
     *
     * Rate plans are deliberately not fetched/returned here: Guesthub never
     * pushes rates (rate tools like PriceLabs push rates directly into
     * Channex), so there is nothing on the rate side for Guesthub to map.
     */
    public function getRoomTypes(string $externalPropertyId): array;

    /**
     * Push availability (open/closed per date) for a given room type to the
     * channel manager, which propagates it to every connected OTA (Airbnb
     * included). $dates is a map of 'Y-m-d' => bool (true = open/bookable).
     * $externalPropertyId is required alongside $roomTypeId -- Channex's
     * Update Availability endpoint requires both property_id and
     * room_type_id on every change object, not room_type_id alone.
     * Returns true if the channel manager accepted the update.
     */
    public function pushAvailability(string $externalPropertyId, string $roomTypeId, array $dates): bool;
}
