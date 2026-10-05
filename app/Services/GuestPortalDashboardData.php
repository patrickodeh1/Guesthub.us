<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PropertyLock;
use App\Models\User;
use App\Services\SeamService;
use Illuminate\Support\Facades\Cache;

class GuestPortalDashboardData
{
    public function get(User $user): array
    {
        $today = now()->setTimezone(config('app.display_timezone'))->toDateString();

        $todayGuests = Booking::with('property')
            ->notArchived()
            ->whereHas('property', fn ($query) => $query->visibleTo($user))
            ->where(fn ($query) => $query->whereDate('check_in_date', $today)->orWhereDate('check_out_date', $today))
            ->get()
            ->sortBy(fn (Booking $booking) => $this->sortKey($booking, $today))
            ->values();

        $upcomingGuests = Booking::with('property')
            ->notArchived()
            ->whereHas('property', fn ($query) => $query->visibleTo($user))
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', $today)
            ->get()
            ->sortBy(fn (Booking $booking) => $this->sortKey($booking, $today))
            ->values();

        // Priority guests (need approval / host intervention) are pinned into
        // the Today card regardless of their dates until resolved, after
        // which they drop into the normal Today/Tomorrow/Upcoming bucket.
        $priorityGuests = Booking::with('property')
            ->notArchived()
            ->whereHas('property', fn ($query) => $query->visibleTo($user))
            ->whereNull('checked_out_at')
            ->get()
            ->filter(fn (Booking $booking) => $booking->isPriorityGuest())
            ->sortBy(fn (Booking $booking) => $booking->check_in_date?->toDateString())
            ->values();

        $seam = app(SeamService::class);
        $propertyLocks = PropertyLock::with('property')
            ->whereHas('property', fn ($query) => $query->visibleTo($user))
            ->get()
            ->map(function (PropertyLock $lock) use ($seam): PropertyLock {

                try {
                    $live = $seam->getLockStatus($lock->seam_device_id);
                    if ($live !== null && $lock->last_known_locked !== $live) {
                        $lock->update(['last_known_locked' => $live, 'last_status_at' => now()]);
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }

                $cacheKey = "lock_battery_fetched:{$lock->id}";
                if (! Cache::has($cacheKey)) {
                    try {
                        $lock->update(['battery_level' => $seam->getBatteryLevel($lock->seam_device_id)]);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                    Cache::put($cacheKey, true, now()->addMinutes(10));
                }

                return $lock;
            })
            ->groupBy('property.name');

        return compact('todayGuests', 'upcomingGuests', 'priorityGuests', 'today', 'propertyLocks');
    }

    private function sortKey(Booking $booking, string $today): string
    {
        $checkIn = $booking->check_in_date?->toDateString();
        $checkOut = $booking->check_out_date?->toDateString();

        $group = match (true) {
            $checkIn === $today && ! $booking->isMarkedCheckedIn() => 0,
            $checkIn === $today => 1,
            $checkOut === $today => 2,
            default => 3,
        };

        return sprintf('%d|%s|%s', $group, $checkIn, $booking->guest_name);
    }
}
