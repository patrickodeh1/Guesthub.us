<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GuestNotice;
use Illuminate\Support\Collection;

/**
 * Resolves the host-configured conditional guest notices (Admin > Guest
 * Notices) that apply to a booking for a given portal phase, split into
 * pop-ups (modals) and steps (injected into the check-in/out wizard).
 */
class GuestNoticeService
{
    /**
     * @return Collection<int, GuestNotice>
     */
    public static function forBooking(Booking $booking, string $phase): Collection
    {
        return GuestNotice::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (GuestNotice $notice) => $notice->matches($booking, $phase))
            ->values();
    }

    /**
     * @return Collection<int, GuestNotice>
     */
    public static function popups(Booking $booking, string $phase): Collection
    {
        return self::forBooking($booking, $phase)->where('type', 'popup')->values();
    }

    /**
     * Wizard steps for the given phase, in the shape the step-wizard
     * component expects.
     *
     * @return list<array<string, mixed>>
     */
    public static function steps(Booking $booking, string $phase): array
    {
        return self::forBooking($booking, $phase)
            ->where('type', 'step')
            ->map(fn (GuestNotice $notice) => [
                'title' => $notice->title,
                'content' => nl2br(e($notice->body)),
                'image' => null,
                'images' => [],
                'action' => 'content',
                'lock_status' => null,
                'lock_id' => null,
            ])
            ->values()
            ->all();
    }
}
