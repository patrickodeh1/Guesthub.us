@php
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
    $doorOpen = $isAssignedCleaner && ($session->status === 'in_progress' || ($session->status === 'pending' && \App\Services\CleanerAccessSteps::locationVerified($session)));
    $doorLock = $doorOpen ? $session->property->locks()->first() : null;
    $doorStatus = null;
    if ($doorLock) {
        try {
            $doorStatus = app(\App\Services\SeamService::class)->getLockStatus($doorLock->seam_device_id);
        } catch (\Throwable $e) {
            $doorStatus = $doorLock->last_known_locked;
        }
    }
@endphp

@if ($doorLock)
    <div id="cleaner-door" class="guest-portal-card mb-4 p-6">
        <p class="guest-status-kicker">Door</p>
        <h2 class="guest-status-title">Lock or unlock the door</h2>
        <x-lock-card
            :lock-id="$doorLock->id"
            :lock-label="$doorLock->label"
            :lock-status="$doorStatus"
            :unlock-url="route('sessions.door.unlock', [$session, $doorLock])"
            :lock-url="route('sessions.door.lock', [$session, $doorLock])"
            :status-url="route('sessions.door.status', [$session, $doorLock])" />
    </div>
@endif
