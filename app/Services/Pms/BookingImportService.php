<?php

namespace App\Services\Pms;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Pms\PmsProviderInterface;

/**
 * Turns a normalized PmsBooking into a Guesthub Booking row.
 *
 * Deliberately narrow: only ever creates/updates bookings that are
 * themselves PMS-sourced (source = the provider's name + matching
 * channex_booking_id). A manually-created booking is never touched by this,
 * even if names/dates happen to look similar — there's no fuzzy matching
 * here on purpose, to avoid ever silently overwriting an admin's manual
 * entry.
 */
class BookingImportService
{
    public function __construct(
        private readonly PmsProviderInterface $provider,
        private readonly string $providerName = 'channex',
    ) {
    }

    /**
     * @return Booking|null null if the booking's property isn't mapped yet
     *                       (admin hasn't set channex_property_id) — logged,
     *                       not thrown, so one unmapped property doesn't
     *                       break the rest of the sync batch.
     */
    public function import(PmsBooking $pmsBooking): ?Booking
    {
        $property = Property::query()
            ->where('channex_property_id', $pmsBooking->externalPropertyId)
            ->first();

        if (! $property) {
            Log::warning('PMS booking import skipped: no property mapped for external property id', [
                'external_property_id' => $pmsBooking->externalPropertyId,
                'external_booking_id' => $pmsBooking->externalBookingId,
            ]);
            return null;
        }

        // check_in_date/check_out_date are NOT NULL on bookings. Some PMS
        // revisions (e.g. cancelled Channex test/staging bookings) arrive
        // with no arrival/departure dates at all -- there is nothing useful
        // to store yet, so skip rather than violate the DB constraint.
        if (! $pmsBooking->checkInDate || ! $pmsBooking->checkOutDate) {
            Log::warning('PMS booking import skipped: missing check-in/check-out date', [
                'external_property_id' => $pmsBooking->externalPropertyId,
                'external_booking_id' => $pmsBooking->externalBookingId,
                'status' => $pmsBooking->status,
            ]);

            // Unlike the "property not mapped" skip above, this is never
            // going to resolve itself on a future poll -- a cancelled/dateless
            // revision won't retroactively grow dates. Acknowledge it now so
            // Channex stops re-delivering the same revision on every cycle.
            // (revisionId is null for providers with no revision concept,
            // e.g. NextPax -- nothing to ack there.)
            if ($pmsBooking->revisionId) {
                $this->provider->acknowledgeBooking($pmsBooking->revisionId);
            }

            return null;
        }

        $booking = Booking::query()
            ->where('source', $this->providerName)
            ->where('channex_booking_id', $pmsBooking->externalBookingId)
            ->first();

        $attributes = [
            'source' => $this->providerName,
            'channex_booking_id' => $pmsBooking->externalBookingId,
            'property_id' => $property->id,
            'check_in_date' => $pmsBooking->checkInDate ?: null,
            'check_out_date' => $pmsBooking->checkOutDate ?: null,
        ];

        // Name the OTA (Airbnb, Vrbo, Booking.com, …) the channel manager
        // reports, so the guest payment flow can label itself correctly
        // instead of always saying "Airbnb".
        if ($pmsBooking->otaName) {
            $attributes['booking_platform'] = $pmsBooking->otaName;
        }

        // Guest name is the one field we always trust from the PMS — it's
        // needed to even show a placeholder booking in the admin list.
        // Contact info (email/phone) is treated as prefill only: the guest
        // still self-reports at precheckin regardless of source (Airbnb
        // proxy numbers etc. shouldn't be trusted as the final record), so
        // we only set it on first import and never overwrite what the
        // guest has since entered themselves.
        if ($pmsBooking->guestName) {
            $attributes['guest_name'] = $pmsBooking->guestName;
        }

        if (! $booking) {
            $attributes['booking_id'] = 'CX-' . Str::upper(Str::random(8));
            $attributes['reservation_id'] = $pmsBooking->otaReservationCode ?: $pmsBooking->externalBookingId;
            $attributes['token'] = (string) Str::uuid();

            // Normally a fresh PMS-sourced booking starts 'pending' for staff
            // review. But if this is the very first revision Guesthub has
            // ever seen for this external_booking_id and it already arrives
            // cancelled/declined (guest booked and cancelled on the OTA
            // before our webhook/poll caught the original creation), don't
            // manufacture a live 'pending' booking for something that's
            // already dead on arrival.
            $arrivesCancelled = in_array($pmsBooking->status, ['cancelled', 'declined'], true);
            $attributes['status'] = $arrivesCancelled ? 'cancelled' : 'pending';
            if ($arrivesCancelled) {
                $feeApplies = $this->cancellationFeeApplies($pmsBooking->checkInDate);

                $attributes['cancelled_at'] = now();
                $attributes['cancelled_by_guest'] = true;
                $attributes['cancellation_fee_applies'] = $feeApplies;
                // Inside the 30-day window we're owed money, so keep it on the
                // active list (read-only) instead of archiving.
                $attributes['archived_at'] = $feeApplies ? null : now();
            }

            if ($pmsBooking->guestEmail) {
                $attributes['email'] = $pmsBooking->guestEmail;
            }
            if ($pmsBooking->guestPhone) {
                $attributes['phone'] = $pmsBooking->guestPhone;
            }

            $booking = Booking::create($attributes);

            if ($arrivesCancelled) {
                // Don't tell staff "new booking" for something that was
                // already cancelled before we ever saw it -- that's
                // misleading and would make them go look for a live
                // reservation that doesn't exist. Send the cancellation
                // notice instead so they at least know it happened.
                Log::info('PMS booking created already cancelled (first-ever revision)', [
                    'booking_id' => $booking->id,
                    'external_booking_id' => $pmsBooking->externalBookingId,
                ]);
                \App\Services\GuestAlertService::send('pms_booking_cancelled', $booking);
            } else {
                // Notify staff (owner/contact desk, email + SMS) that a booking
                // just arrived from the channel manager -- otherwise a
                // PMS-sourced booking can sit unnoticed until someone happens
                // to open the admin panel. Guest is never messaged for this
                // event; they only hear from us once staff has reviewed it.
                \App\Services\GuestAlertService::send('pms_booking_received', $booking);
            }
        } else {
            // Cancellation always wins, regardless of how far the booking
            // has progressed internally (approved/checked-in/etc.) -- a
            // guest who cancelled on the OTA needs staff to see that, not
            // have it silently swallowed because the workflow had moved on.
            // Once cancelled, the booking is terminal: further stale
            // revisions (retries, late polls) are ignored rather than
            // reviving it.
            if ($booking->status === 'cancelled') {
                Log::info('PMS booking import skipped: booking already cancelled', [
                    'booking_id' => $booking->id,
                    'external_booking_id' => $pmsBooking->externalBookingId,
                ]);
                return $booking->fresh();
            }

            if (in_array($pmsBooking->status, ['cancelled', 'declined'], true)) {
                $feeApplies = $this->cancellationFeeApplies($booking->check_in_date?->toDateString() ?: $pmsBooking->checkInDate);

                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by_guest' => true,
                    'cancellation_fee_applies' => $feeApplies,
                    // Inside the 30-day pre-arrival window we're owed money, so
                    // keep it unarchived (read-only) rather than filing it away.
                    // Outside that window, archive immediately rather than
                    // waiting for the checkout date to pass (archiveOverdue()).
                    'archived_at' => $feeApplies ? null : now(),
                ]);

                Log::info('PMS booking marked cancelled', [
                    'booking_id' => $booking->id,
                    'external_booking_id' => $pmsBooking->externalBookingId,
                    'cancellation_fee_applies' => $feeApplies,
                ]);

                \App\Services\GuestAlertService::send('pms_booking_cancelled', $booking);

                return $booking->fresh();
            }

            // Existing PMS-sourced booking, still active on the OTA: refresh
            // dates/property/name as soon as a webhook or poll picks up a
            // change -- no longer gated on 'pending' only, since a guest can
            // change dates on the OTA after Guesthub has already moved the
            // booking further along (approved, checked in, etc.), and staff
            // need to see that change immediately, not just on first import.
            // Guest-entered contact info is still never touched here (see
            // $attributes construction above).
            //
            // Only the fields that are actually visible/meaningful to staff
            // are compared here (dates, property, name) -- this doubles as
            // the double-ack/double-import guard: if the webhook and the
            // poller both process the same revision (or a poll re-delivers
            // a revision we already applied), the second call finds nothing
            // changed and exits quietly instead of writing a duplicate
            // update + duplicate log line + duplicate staff alert.
            $changed = [];
            foreach (['check_in_date', 'check_out_date', 'property_id', 'guest_name'] as $field) {
                $new = $attributes[$field] ?? null;
                $old = $field === 'check_in_date' || $field === 'check_out_date'
                    ? $booking->{$field}?->format('Y-m-d')
                    : $booking->{$field};

                if ($new != $old) {
                    $changed[$field] = ['old' => $old, 'new' => $new];
                }
            }

            if (empty($changed)) {
                // Nothing staff-visible changed. Silently backfill the OTA name
                // for bookings imported before booking_platform existed, so
                // the guest payment screen can name the right platform without
                // firing a "booking updated" alert for every old reservation.
                if (array_key_exists('booking_platform', $attributes)
                    && $booking->booking_platform !== $attributes['booking_platform']) {
                    $booking->update(['booking_platform' => $attributes['booking_platform']]);
                }

                Log::info('PMS booking import skipped: no changes on re-delivered revision', [
                    'booking_id' => $booking->id,
                    'external_booking_id' => $pmsBooking->externalBookingId,
                ]);

                return $booking->fresh();
            }

            $booking->update($attributes);

            Log::info('PMS booking updated from channel manager', [
                'booking_id' => $booking->id,
                'external_booking_id' => $pmsBooking->externalBookingId,
                'changed' => $changed,
            ]);

            \App\Services\GuestAlertService::send('pms_booking_updated', $booking->fresh());
        }

        return $booking->fresh();
    }

    /**
     * Whether a guest cancellation falls inside the 30-day window before
     * arrival (or after the stay was due to start), where we're still owed
     * money and the booking must not be archived.
     */
    private function cancellationFeeApplies($checkInDate): bool
    {
        if (! $checkInDate) {
            return false;
        }

        $today = now()->setTimezone(config('app.display_timezone'))->startOfDay();

        return \Carbon\Carbon::parse($checkInDate)->startOfDay()->lte($today->copy()->addDays(30));
    }
}
