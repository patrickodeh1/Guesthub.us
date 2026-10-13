@php
    $exitSteps = $exitSteps ?? [];
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
@endphp

@if (false && $isAssignedCleaner && count($exitSteps) > 0)
    <div id="leaving" class="mb-4">
        <x-step-wizard :steps="$exitSteps" type="exit" kicker="Before you leave" done-label="Got it" next-section="leaving" />
    </div>
@endif
