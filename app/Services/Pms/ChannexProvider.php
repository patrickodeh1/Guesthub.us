<?php

namespace App\Services\Pms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Channex implementation of PmsProviderInterface. Reservation *booking*
 * data is still import-only -- we never write bookings back to Channex.
 * Availability is two-way: see getRoomTypes() and pushAvailability()
 * below. Rates are NOT pushed by Guesthub at all -- rate tools (e.g.
 * PriceLabs) push rates directly into Channex.
 *
 * Verified against Channex's live API docs (docs.channex.io) as of
 * 2026-08-26. Channex's own PMS certification notes explicitly say not to
 * use the /bookings endpoints — use /booking_revisions instead — so this
 * provider is built entirely around Booking Revisions:
 *   - GET  /booking_revisions/feed      pull unacknowledged revisions
 *   - GET  /booking_revisions/:id       pull one revision by its own ID
 *   - POST /booking_revisions/:id/ack   acknowledge a revision
 *
 * A Booking Revision's `id` (used everywhere above) is the *revision* ID,
 * NOT the booking ID — the booking ID is a separate sibling field
 * (`booking_id`) inside each revision's attributes. Mixing these up (e.g.
 * acknowledging using the booking ID) will 404 against the real API.
 */
class ChannexProvider implements PmsProviderInterface
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.channex.base_url'), '/');
        $this->apiKey = config('services.channex.api_key');
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['user-api-key' => $this->apiKey])
            ->acceptJson();
    }

    /**
     * Pulls unacknowledged Booking Revisions. This is Channex's documented
     * primary way to fetch bookings — the feed only ever contains revisions
     * not yet acknowledged, so `$since` doesn't map to a query param here;
     * it's accepted for interface compatibility but revisions are removed
     * from the feed entirely once acked, making an additional time filter
     * redundant.
     */
    public function getBookings(?\DateTimeInterface $since = null): array
    {
        $response = $this->client()->get('/booking_revisions/feed', [
            'order[inserted_at]' => 'asc',
        ]);

        if (! $response->successful()) {
            Log::warning('Channex getBookings (booking_revisions/feed) failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return collect($response->json('data', []))
            ->map(fn (array $item) => $this->normalizeRevision($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Fetches a single Booking Revision by its own (revision) ID.
     */
    public function getBooking(string $externalBookingId): ?PmsBooking
    {
        $response = $this->client()->get("/booking_revisions/{$externalBookingId}");

        if (! $response->successful()) {
            Log::warning('Channex getBooking (booking_revisions/:id) failed', [
                'revision_id' => $externalBookingId,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $this->normalizeRevision($response->json('data', []));
    }

    /**
     * Acknowledges a Booking Revision. IMPORTANT: $externalBookingId here
     * must be the revision ID (PmsBooking::$revisionId), not the booking
     * ID — Channex's ack endpoint is scoped to revisions, and passing the
     * booking ID will 404.
     */
    public function acknowledgeBooking(string $externalBookingId): void
    {
        $response = $this->client()->post("/booking_revisions/{$externalBookingId}/ack");

        if (! $response->successful()) {
            Log::warning('Channex acknowledgeBooking (booking_revisions/:id/ack) failed', [
                'revision_id' => $externalBookingId,
                'status' => $response->status(),
            ]);
        }
    }

    /**
     * Channex booking webhooks (booking / booking_new / booking_modification
     * / booking_cancellation) do NOT carry the full booking record — only a
     * pointer: { "event": "...", "payload": { "booking_id", "property_id",
     * "revision_id" }, ... }. The webhook exists purely to trigger a pull of
     * the full revision via the API, which we do here using revision_id
     * (falling back to booking_id only if revision_id is somehow absent,
     * though that shouldn't happen for booking_new/modification/cancellation).
     */
    public function handleWebhookPayload(array $payload): ?PmsBooking
    {
        $inner = $payload['payload'] ?? null;

        if (! is_array($inner)) {
            return null;
        }

        $revisionId = $inner['revision_id'] ?? $inner['booking_id'] ?? null;

        if (! $revisionId) {
            return null;
        }

        return $this->getBooking((string) $revisionId);
    }

    /**
     * BACKFILL ONLY -- do not call this from the regular scheduled sync.
     *
     * Channex's own certification guidance says NOT to use GET /bookings
     * for ongoing polling; /booking_revisions/feed must remain the only
     * source for regular syncs. The one documented exception is an
     * initial/one-time historical pull to populate a system with bookings
     * that already existed before revision tracking started -- which is
     * exactly what this method is for. It is intentionally not part of
     * PmsProviderInterface's getBookings() contract/usage pattern; it's
     * invoked directly (see channex:backfill-bookings) and only ever run
     * manually, once, per gap.
     *
     * Handles pagination and optional arrival_date/departure_date range
     * filtering via Channex's Bookings Collection endpoint.
     *
     * @return PmsBooking[]
     */
    public function getAllBookings(?array $dateRange = null): array
    {
        $results = [];
        $page = 1;
        $perPage = 100;

        do {
            $query = [
                'page' => $page,
                'limit' => $perPage,
            ];

            if (! empty($dateRange['arrival_date_from'])) {
                $query['filter[arrival_date_from]'] = $dateRange['arrival_date_from'];
            }
            if (! empty($dateRange['arrival_date_to'])) {
                $query['filter[arrival_date_to]'] = $dateRange['arrival_date_to'];
            }
            if (! empty($dateRange['departure_date_from'])) {
                $query['filter[departure_date_from]'] = $dateRange['departure_date_from'];
            }
            if (! empty($dateRange['departure_date_to'])) {
                $query['filter[departure_date_to]'] = $dateRange['departure_date_to'];
            }

            $response = $this->client()->get('/bookings', $query);

            if (! $response->successful()) {
                Log::warning('Channex getAllBookings (bookings, backfill) failed', [
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json('data', []);

            foreach ($data as $item) {
                $booking = $this->normalizeBooking($item);
                if ($booking) {
                    $results[] = $booking;
                }
            }

            $count = count($data);
            $page++;
        } while ($count === $perPage);

        return $results;
    }

    /**
     * Normalizes a Bookings Collection object (as returned by GET /bookings)
     * into a PmsBooking. Distinct from normalizeRevision() because the shape
     * differs: here `id` IS the booking ID and `revision_id` is a separate
     * sibling field -- the inverse of the revision feed shape, where `id` is
     * the revision ID and `booking_id` is separate. Mixing these two
     * normalizers up will silently produce wrong IDs.
     *
     * revisionId is intentionally left null on the returned PmsBooking:
     * these records did not come from the revision feed, so there is
     * nothing to acknowledge, and BookingImportService/SyncPmsBookings must
     * never attempt to ack a backfilled record.
     */
    private function normalizeBooking(array $item): ?PmsBooking
    {
        $attributes = $item['attributes'] ?? $item;

        $bookingId = $attributes['id'] ?? $item['id'] ?? null;

        if (empty($bookingId) || empty($attributes['property_id'])) {
            return null;
        }

        $guest = $attributes['customer'] ?? [];

        return new PmsBooking(
            externalBookingId: (string) $bookingId,
            externalPropertyId: (string) $attributes['property_id'],
            guestName: trim(($guest['name'] ?? '') . ' ' . ($guest['surname'] ?? '')) ?: null,
            guestEmail: $guest['mail'] ?? null,
            guestPhone: $guest['phone'] ?? null,
            checkInDate: $attributes['arrival_date'] ?? '',
            checkOutDate: $attributes['departure_date'] ?? '',
            status: $attributes['status'] ?? null,
            raw: $attributes,
            revisionId: null,
            otaReservationCode: $attributes['ota_reservation_code'] ?? null,
            otaName: $attributes['ota_name'] ?? null,
        );
    }

    /**
     * Normalizes a Booking Revision object (as returned by
     * /booking_revisions/feed and /booking_revisions/:id) into a PmsBooking.
     * The revision's own `id` is the revision ID; `booking_id` is the
     * separate, stable booking identifier used elsewhere (e.g. imports).
     */
    private function normalizeRevision(array $item): ?PmsBooking
    {
        // Both feed/get-by-id responses wrap fields under "attributes";
        // guard for either shape defensively.
        $attributes = $item['attributes'] ?? $item;

        if (empty($attributes['booking_id']) || empty($attributes['property_id'])) {
            return null;
        }

        $guest = $attributes['customer'] ?? [];

        return new PmsBooking(
            externalBookingId: (string) $attributes['booking_id'],
            externalPropertyId: (string) $attributes['property_id'],
            guestName: trim(($guest['name'] ?? '') . ' ' . ($guest['surname'] ?? '')) ?: null,
            guestEmail: $guest['mail'] ?? null,
            guestPhone: $guest['phone'] ?? null,
            checkInDate: $attributes['arrival_date'] ?? '',
            checkOutDate: $attributes['departure_date'] ?? '',
            status: $attributes['status'] ?? null,
            raw: $attributes,
            revisionId: isset($attributes['id']) ? (string) $attributes['id'] : null,
            otaReservationCode: $attributes['ota_reservation_code'] ?? null,
            otaName: $attributes['ota_name'] ?? null,
        );
    }
    /**
     * Fetches room types Channex already has on file for a property, so
     * the admin can pick from a list instead of typing UUIDs. Rate plans
     * are deliberately not fetched -- Guesthub never pushes rates.
     */
    public function getRoomTypes(string $externalPropertyId): array
    {
        $response = $this->client()->get('/room_types', [
            'filter[property_id]' => $externalPropertyId,
        ]);

        if (! $response->successful()) {
            Log::warning('Channex getRoomTypes (room_types) failed', [
                'property_id' => $externalPropertyId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $results = [];

        foreach ($response->json('data', []) as $roomType) {
            $rtAttributes = $roomType['attributes'] ?? [];
            $roomTypeId = $roomType['id'] ?? null;

            if (! $roomTypeId) {
                continue;
            }

            $results[] = [
                'room_type_id' => (string) $roomTypeId,
                'room_type_title' => $rtAttributes['title'] ?? '(untitled room type)',
            ];
        }

        return $results;
    }

    /**
     * Pushes availability for a room type via Channex's dedicated Update
     * Availability endpoint (POST /availability) -- NOT /restrictions,
     * which is a separate, rate-plan-scoped endpoint for stop_sell/rate/
     * min_stay and has no "availability" field at all. An earlier version
     * of this method posted to /restrictions with an "availability" key,
     * which Channex silently ignored as an unrecognized field, returning
     * 200 OK while applying nothing -- this is why early pushes appeared
     * to succeed but never showed up on Airbnb. Verified against Channex's
     * live docs (docs.channex.io/api-v.1-documentation/ari) as of
     * 2026-09-07: each change object requires property_id, room_type_id,
     * date, and availability (non-negative integer -- 0 or 1 for a
     * single-unit property).
     */
    public function pushAvailability(string $externalPropertyId, string $roomTypeId, array $dates): bool
    {
        $values = [];

        foreach ($dates as $date => $isAvailable) {
            $values[] = [
                'property_id' => $externalPropertyId,
                'room_type_id' => $roomTypeId,
                'date' => $date,
                'availability' => $isAvailable ? 1 : 0,
            ];
        }

        if (empty($values)) {
            return true;
        }

        $response = $this->client()->post('/availability', [
            'values' => $values,
        ]);

        if (! $response->successful()) {
            Log::warning('Channex pushAvailability (availability) failed', [
                'property_id' => $externalPropertyId,
                'room_type_id' => $roomTypeId,
                'date_count' => count($values),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
    }
}
