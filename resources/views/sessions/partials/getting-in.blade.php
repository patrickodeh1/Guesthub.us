@php
    $accessSteps = $accessSteps ?? [];
    $canAnswerParking = auth()->check()
        && ((int) $session->housekeeper_id === (int) auth()->id() || auth()->user()->hasRole('admin'));
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
    $adminView = ! $isAssignedCleaner;
@endphp

@if ($isAssignedCleaner && $session->status === 'pending')
<div id="getting-in" class="mb-4">
@if ($adminView)
<details class="guest-portal-card p-4">
    <summary class="cursor-pointer text-sm font-semibold text-slate-600">Cleaner directions (what the cleaner sees)</summary>
    <div class="mt-4">
@endif
        {{-- gh-wizard-flow --}}
    @php
        $giGate = $isAssignedCleaner && $session->status === 'pending' && count($accessSteps) > 0;
        $giVerifiedNow = \App\Services\CleanerAccessSteps::locationVerified($session);
    @endphp
    @if ($giGate)
        <div id="getting-in-start" class="guest-portal-card p-6 text-center">
            <button type="button" id="getting-in-start-btn" class="guest-primary-btn w-full">{{ $giVerifiedNow ? 'Begin' : 'Start' }}</button>
        </div>
    @endif
    <div id="step-wizard-parking-wrapper" @if ($giGate) style="display:none" @endif>
            @php
                $giVerified = \App\Services\CleanerAccessSteps::locationVerified($session);
                $giPre = $isAssignedCleaner && $session->status === 'pending' && ! $giVerified;
                $giKicker = $giPre ? 'Before you arrive' : 'Getting in';
                $giDone = $giPre ? "I'm at the property" : ($isAssignedCleaner && $session->status === 'pending' ? 'Continue' : 'Got it');
            @endphp
            <x-step-wizard :steps="$accessSteps" type="parking" :kicker="$giKicker" :done-label="$giDone" next-section="getting-in-reopen" />
        </div>

        @if (count($accessSteps) === 0)
            <div class="guest-portal-card p-6">
                <p class="guest-status-kicker">Getting in</p>
                @if ($session->status === 'pending' && $isAssignedCleaner)
                    <p class="mt-2 text-sm italic text-slate-500">Verify your location at the property and the rest of your directions will appear here.</p>
                @else
                    <p class="mt-2 text-sm italic text-slate-500">No access instructions have been added for this property yet.</p>
                @endif
            </div>
        @endif



        <script>
        /* gh-dom-ready */
        document.addEventListener("DOMContentLoaded", function () {
            var startCard = document.getElementById("getting-in-start");
            var startBtn = document.getElementById("getting-in-start-btn");
            var wrap = document.getElementById("step-wizard-parking-wrapper");
            var done = document.getElementById("wizard-done-parking");
            if (!wrap || !done) return;
            var key = "getting_in_ack_{{ $session->id }}_{{ (int) \App\Services\CleanerAccessSteps::locationVerified($session) }}";
            function el(id) { return document.getElementById(id); }
            function toTop() { window.scrollTo({ top: 0, behavior: "smooth" }); }
            function showWizard() {
                if (startCard) startCard.style.display = "none";
                wrap.style.display = "";
                var root = el("step-wizard-parking"); if (root) root.style.display = "";
                var g = el("pending-gate"); if (g && startCard) g.style.display = "none";
                toTop();
            }
            function showGate() {
                wrap.style.display = "none";
                var root = el("step-wizard-parking"); if (root) root.style.display = "none";
                if (startCard) startCard.style.display = "none";
                var g = el("pending-gate");
                if (g) {
                    g.style.display = "";
                    if (startCard && !el("gh-gate-back")) {
                        var b = document.createElement("button");
                        b.type = "button"; b.id = "gh-gate-back";
                        b.className = "guest-outline-btn mb-3"; b.style.display = "block"; b.style.margin = "0 auto 12px";
                        b.textContent = "Back to directions";
                        b.addEventListener("click", showWizard);
                        g.insertBefore(b, g.firstChild);
                    }
                }
                toTop();
            }
            if (startBtn) startBtn.addEventListener("click", showWizard);
            done.addEventListener("click", function () {
                try { localStorage.setItem(key, "1"); } catch (e) {}
                showGate();
            });
            try { if (localStorage.getItem(key)) showGate(); } catch (e) {}
        });
        </script>
@if ($adminView)
    </div>
</details>
@endif
</div>
@endif
