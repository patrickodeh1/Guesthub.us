<?php

namespace App\Traits;

use App\Models\Booking;
use App\Models\PropertyLock;
use App\Services\SeamService;
use Carbon\Carbon;

trait BuildsGuestPortalData
{
    protected array $lockStatusCache = [];

    protected function resolveLocks(Booking $booking)
    {
        return $booking->property->locks->map(fn ($lock) => [
            'lock'   => $lock,
            'status' => $this->lockStatusFor($booking, $lock),
        ]);
    }

    protected function lockStatusFor(Booking $booking, ?PropertyLock $lock = null): ?bool
    {
        $lock = $lock ?: $booking->property->locks()->first();
        if (! $lock) {
            return null;
        }
        if (array_key_exists($lock->id, $this->lockStatusCache)) {
            return $this->lockStatusCache[$lock->id];
        }
        try {
            $status = app(SeamService::class)->getLockStatus($lock->seam_device_id);
        } catch (\Throwable $e) {
            $status = null;
        }
        return $this->lockStatusCache[$lock->id] = $status;
    }

    protected function checkinTimeOptions(): array
    {
        $options = [];
        // 8am through 12am (midnight), hourly
        $hours = array_merge(range(8, 23), [0]);
        foreach ($hours as $hour) {
            $value = sprintf('%02d:00', $hour);
            $label = Carbon::createFromTime($hour, 0)->format('g:i A');
            if ($hour === 10) {
                $label .= ' (Recommended)';
            }
            $options[$value] = $label;
        }
        return $options;
    }

    protected function checkoutTimeOptions(): array
    {
        $options = [];
        // 10am through 8pm, hourly
        for ($hour = 10; $hour <= 20; $hour++) {
            $value = sprintf('%02d:00', $hour);
            $label = Carbon::createFromTime($hour, 0)->format('g:i A');
            if ($hour === 10) {
                $label .= ' (Recommended)';
            }
            $options[$value] = $label;
        }
        return $options;
    }
}
