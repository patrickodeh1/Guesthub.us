@php
    $accessSteps = $accessSteps ?? [];
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
@endphp

@if ($isAssignedCleaner && $session->status === 'pending')
<div id="getting-in" class="mb-4">
    {{-- gh-wizard-flow --}}
    @php
        $giVerified = \App\Services\CleanerAccessSteps::locationVerified($session);
        $giKicker = $giVerified ? 'Getting in' : 'Before you arrive';
        $giDone = $giVerified ? 'Continue' : "I'm at the property";
    @endphp
    <div id="step-wizard-parking-wrapper">
        <x-step-wizard :steps="$accessSteps" type="parking" :kicker="$giKicker" :done-label="$giDone" next-section="getting-in-reopen" />
    </div>

    @if (count($accessSteps) === 0)
        <div class="guest-portal-card p-6">
            <p class="guest-status-kicker">Getting in</p>
            <p class="mt-2 text-sm italic text-slate-500">Verify your location at the property and the rest of your directions will appear here.</p>
        </div>
    @endif

    <script>
    /* gh-dom-ready */
    document.addEventListener("DOMContentLoaded", function () {
        var wrap = document.getElementById("step-wizard-parking-wrapper");
        var done = document.getElementById("wizard-done-parking");
        if (!wrap || !done) return;
        function el(id) { return document.getElementById(id); }
        function toTop() { window.scrollTo({ top: 0, behavior: "smooth" }); }
        function showWizard() {
            wrap.style.display = "";
            var root = el("step-wizard-parking"); if (root) root.style.display = "";
            var g = el("pending-gate"); if (g) g.style.display = "none";
            toTop();
        }
        function showGate() {
            wrap.style.display = "none";
            var root = el("step-wizard-parking"); if (root) root.style.display = "none";
            var g = el("pending-gate");
            if (g) {
                g.style.display = "";
            }
            toTop();
        }
        done.addEventListener("click", showGate);
    });
    </script>
</div>
@endif
