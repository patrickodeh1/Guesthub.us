<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingConflictService
{
    /** Serialize all writes for one property, then run $fn inside the transaction. */
    public static function withPropertyLock(int $propertyId, callable $fn)
    {
        return DB::transaction(function () use ($propertyId, $fn) {
            Property::whereKey($propertyId)->lockForUpdate()->first();

            return $fn();
        });
    }

    /**
     * For PMS imports: never reject (the guest really booked on the OTA), but
     * make an overlap impossible to miss. Never throws, so it can't break a sync.
     */
    public static function flagImportOverlap(Booking $booking): ?Booking
    {
        try {
            if ($booking->status === 'cancelled') {
                return null;
            }

            $c = self::find((int) $booking->property_id, $booking->check_in_date, $booking->check_out_date, $booking->id);
            if (! $c) {
                return null;
            }

            $msg = "Possible double booking: {$booking->guest_name} ({$booking->booking_id}, "
                .$booking->check_in_date->format('M j').' – '.$booking->check_out_date->format('M j, Y')
                .") overlaps {$c->guest_name} ({$c->booking_id}, "
                .$c->check_in_date->format('M j').' – '.$c->check_out_date->format('M j, Y').').';

            \Illuminate\Support\Facades\Log::warning('PMS import: '.$msg, [
                'booking_id' => $booking->id,
                'conflicts_with' => $c->id,
            ]);

            $booking->update(['notes' => trim('⚠ '.$msg."\n".($booking->notes ?? ''))]);

            try {
                \App\Services\ActivityLogService::admin('booking_overlap_detected', $msg, 'guests', [
                    'subject_type' => Booking::class,
                    'subject_id'   => $booking->id,
                    'booking_id'   => $booking->id,
                    'property_id'  => $booking->property_id,
                    'severity'     => 'warning',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            return $c;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function find(int $propertyId, $in, $out, ?int $ignoreId = null): ?Booking
    {
        return Booking::overlapping($propertyId, $in, $out, $ignoreId)->first();
    }

    public static function assertFree(int $propertyId, $in, $out, ?int $ignoreId = null, string $field = 'check_in_date'): void
    {
        $c = self::find($propertyId, $in, $out, $ignoreId);
        if ($c) {
            throw ValidationException::withMessages([
                $field => "These dates overlap {$c->guest_name}'s booking ({$c->booking_id}, "
                    .$c->check_in_date->format('M j').' – '.$c->check_out_date->format('M j, Y').').',
            ]);
        }
    }
}
