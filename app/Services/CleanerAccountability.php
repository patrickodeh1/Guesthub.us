<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CleaningSession;

/**
 * One-line cleaner accountability for a booking row.
 * Past guest: who cleaned after their checkout.
 * Next guest at a property: who cleaned after the previous guest, or a not-ready flag.
 * Later guests and in-house guests: nothing.
 * "Ready" here means the previous checkout clean is completed.
 */
class CleanerAccountability
{
    public static function forBooking(Booking $b, string $today): ?array
    {
        try {
            $in = $b->check_in_date?->toDateString();
            $out = $b->check_out_date?->toDateString();
            if (! $in || ! $out) {
                return null;
            }

            $past = $b->checked_out_at || $out < $today || ($out === $today && $in < $today);

            if ($past) {
                $s = self::sessionFor($b->id);
                return self::describe($s, 'Cleaned by', 'No cleaner assigned');
            }

            if ($in < $today) {
                return null; // in house
            }

            $next = Booking::where('property_id', $b->property_id)->notArchived()
                ->whereNull('cancelled_at')->whereNull('checked_out_at')
                ->whereDate('check_in_date', '>=', $today)
                ->orderBy('check_in_date')->orderBy('id')->first();
            if (! $next || $next->id !== $b->id) {
                return null; // only the next guest shows this
            }

            $prev = Booking::where('property_id', $b->property_id)->where('id', '<>', $b->id)
                ->whereNull('cancelled_at')->whereDate('check_out_date', '<=', $in)
                ->orderByDesc('check_out_date')->orderByDesc('id')->first();
            if (! $prev) {
                return null;
            }

            $s = self::sessionFor($prev->id);
            if ($b->unit_ready_sent_at) {
                return ['text' => 'Unit ready'.($s?->housekeeper?->name ? ' - cleaned by '.$s->housekeeper->name : ''), 'tone' => 'ok'];
            }
            if ($s && $s->housekeeper) {
                return ['text' => ($s->status === 'completed' ? 'Not marked ready - cleaned by '.$s->housekeeper->name : 'Not ready - cleaning by '.$s->housekeeper->name.' not completed'), 'tone' => 'warn'];
            }
            return ['text' => 'Not ready - no cleaner assigned', 'tone' => 'warn'];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private static function sessionFor(int $bookingId): ?CleaningSession
    {
        return CleaningSession::with('housekeeper:id,name')
            ->where('booking_id', $bookingId)->where('status', '<>', 'cancelled')
            ->orderByDesc('id')->first();
    }

    private static function describe(?CleaningSession $s, string $doneLabel, string $none): array
    {
        if ($s && $s->status === 'completed') {
            return ['text' => $doneLabel.' '.($s->housekeeper?->name ?? 'unknown'), 'tone' => 'ok'];
        }
        if ($s && $s->housekeeper) {
            return ['text' => 'Cleaning assigned to '.$s->housekeeper->name, 'tone' => 'info'];
        }
        return ['text' => $none, 'tone' => 'info'];
    }
}
