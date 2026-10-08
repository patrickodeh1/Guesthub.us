@php
    $exitSteps = $exitSteps ?? [];
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
@endphp

@if ($isAssignedCleaner && count($exitSteps) > 0)
    <div id="leaving" class="mb-4">
        <x-step-wizard :steps="$exitSteps" type="exit" kicker="Before you leave" done-label="Got it" next-section="leaving" />
    </div>
@elseif (! $isAssignedCleaner)
    <div id="leaving" class="mb-4">
        <details class="guest-portal-card p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-600">Cleaner exit steps (what the cleaner sees before leaving)</summary>
            <div class="mt-4">
                @if (count($exitSteps) > 0)
                    <x-step-wizard :steps="$exitSteps" type="exit" kicker="Before you leave" done-label="Got it" next-section="leaving" />
                @else
                    <p class="text-sm italic text-slate-500">No cleaner exit steps yet. Add a Check-out step set to "Show to cleaners only".</p>
                @endif
            </div>
        </details>
    </div>
@endif
