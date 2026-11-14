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
            if ($b->isPendingPayment()) {
                return null;
            }

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

            if ($in < $today || ($b->isMarkedCheckedIn() && ! $b->checked_out_at)) {
                return null; // in house
            }

            $next = Booking::where('property_id', $b->property_id)->notArchived()
                ->whereNull('cancelled_at')->whereNull('checked_out_at')
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', Booking::STATUS_PENDING_PAYMENT))
                ->whereDate('check_in_date', '>=', $today)
                ->orderBy('check_in_date')->orderBy('id')->first();
            if (! $next || $next->id !== $b->id) {
                return null; // only the next guest shows this
            }

            $prev = Booking::where('property_id', $b->property_id)->where('id', '<>', $b->id)
                ->whereNull('cancelled_at')->whereDate('check_out_date', '<=', $in)
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', Booking::STATUS_PENDING_PAYMENT))
                ->orderByDesc('check_out_date')->orderByDesc('id')->first();
            if (! $prev) {
                return null;
            }

            $s = self::turnoverSession($prev, $b);
            return self::turnoverLabel($s, $b, $today);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** The clean that prepares the unit for $b: any live session at the property between prev checkout and arrival. */
    private static function turnoverSession(Booking $prev, Booking $b): ?CleaningSession
    {
        $sessions = CleaningSession::with('housekeeper:id,name')
            ->where('property_id', $b->property_id)
            ->where('status', '<>', 'cancelled')
            ->whereDate('scheduled_date', '>=', $prev->check_out_date->toDateString())
            ->whereDate('scheduled_date', '<=', $b->check_in_date->toDateString())
            ->get();

        return $sessions
            ->sortByDesc(fn ($s) => [
                ($s->started_at && $s->status !== 'completed') ? 1 : 0, // in progress first
                $s->scheduled_date?->toDateString(),                      // then newest date
                $s->id,
            ])
            ->first();
    }

    private static function turnoverLabel(?CleaningSession $s, Booking $b, string $today): array
    {
        $name = $s?->housekeeper?->name;
        $in = $b->check_in_date->toDateString();
        $date = $s?->scheduled_date?->toDateString();

        if ($b->unit_ready_sent_at) {
            $done = $s && $s->status === 'completed' && $name;
            return ['text' => 'Unit ready'.($done ? ' - cleaned by '.$name : ''), 'tone' => 'ok'];
        }
        if (! $s) {
            return ['text' => 'Not ready - no cleaning scheduled', 'tone' => 'warn'];
        }
        if (! $name) {
            return ['text' => 'Not ready - no cleaner assigned', 'tone' => 'warn'];
        }
        if ($s->status === 'completed') {
            if ($date && $date < $in) {
                return ['text' => 'Checkout clean done '.$s->scheduled_date->format('M j')." ({$name}) - no arrival clean yet", 'tone' => 'warn'];
            }
            return ['text' => "Cleaned by {$name} - awaiting ready check", 'tone' => 'warn'];
        }
        if ($s->started_at) {
            return ['text' => "Not ready - being cleaned now by {$name}", 'tone' => 'warn'];
        }
        $when = $date === $today ? 'today' : ($s->scheduled_date?->format('M j') ?? 'soon');
        $unconfirmed = $s->assignment_status === 'confirmed' ? '' : ' (unconfirmed)';
        return ['text' => "Not ready - cleaning {$when} by {$name}{$unconfirmed}", 'tone' => 'warn'];
    }

    private static function cleanerLabel(CleaningSession $s): array
    {
        $name = $s->housekeeper?->name ?? 'unknown';

        return $s->assignment_status === 'confirmed'
            ? ['text' => $name, 'tone' => 'green']
            : ['text' => $name, 'tone' => 'red'];
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
            return self::cleanerLabel($s);
        }
        return ['text' => $none, 'tone' => 'info'];
    }
}
