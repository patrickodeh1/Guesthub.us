<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PropertyLock;
use App\Services\SeamService;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private function lockStatuses()
    {
        $seam = app(SeamService::class);

        return PropertyLock::with('property')->get()->map(function (PropertyLock $lock) use ($seam) {
            try {
                $live = $seam->getLockStatus($lock->seam_device_id);
                if ($live !== null && $lock->last_known_locked !== $live) {
                    $lock->update(['last_known_locked' => $live, 'last_status_at' => now()]);
                }
            } catch (\Throwable $e) {
                report($e);
            }

            $cacheKey = "lock_battery_fetched:{$lock->id}";
            if (! Cache::has($cacheKey)) {
                try {
                    $lock->update(['battery_level' => $seam->getBatteryLevel($lock->seam_device_id)]);
                } catch (\Throwable $e) {
                    report($e);
                }
                Cache::put($cacheKey, true, now()->addMinutes(10));
            }

            return $lock;
        })->groupBy('property.name');
    }

    public function __invoke()
    {
        // "Today" is the host's local day, not the server's UTC day — using
        // now()/today() here is what made check-in times and "arriving today"
        // read ~12h off.
        $displayTz = config('app.display_timezone');
        $today = now()->setTimezone($displayTz)->toDateString();

        $todayGuests = Booking::with('property')
            ->notArchived()
            ->where(fn ($q) => $q->whereDate('check_in_date', $today)->orWhereDate('check_out_date', $today))
            ->get()
            ->sortBy(fn (Booking $booking) => $this->dashboardSortKey($booking, $today))
            ->values();

        $upcomingGuests = Booking::with('property')
            ->notArchived()
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', $today)
            ->get()
            ->sortBy(fn (Booking $booking) => $this->dashboardSortKey($booking, $today))
            ->values();

        $todayIds = $todayGuests->pluck('id')->all();

        // Priority items that need admin action regardless of whether the
        // guest's stay is happening today — a pending early-check-in
        // approval or a manual_review ID scan is just as urgent 3 days out
        // as it is on arrival day, and previously wasn't visible here at
        // all until the guest's actual check-in/out date.
        $needsAttentionGuests = Booking::with('property')
            ->notArchived()
            ->whereNull('checked_out_at')
            ->whereNotIn('id', $todayIds)
            ->get()
            ->filter(fn (Booking $booking) => $booking->isPriorityGuest())
            ->sortBy(fn (Booking $booking) => $booking->check_in_date?->toDateString())
            ->values();

        return view('admin.dashboard', [
            'todayGuests'          => $todayGuests,
            'upcomingGuests'       => $upcomingGuests,
            'needsAttentionGuests' => $needsAttentionGuests,
            'today'                => $today,
            'propertyLocks'        => $this->lockStatuses(),
        ]);
    }

    /**
     * Urgency ordering for today's and upcoming guests: pending check-ins
     * first, then approved/checked-in guests, then check-outs, with earlier
     * check-in dates ahead of later ones.
     */
    private function dashboardSortKey(Booking $booking, string $today): string
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
