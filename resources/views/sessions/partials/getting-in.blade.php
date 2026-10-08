@php
    $accessSteps = $accessSteps ?? [];
    $canAnswerParking = auth()->check()
        && ((int) $session->housekeeper_id === (int) auth()->id() || auth()->user()->hasRole('admin'));
    $isAssignedCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
    $adminView = ! $isAssignedCleaner;
@endphp

<div id="getting-in" class="mb-4">
@if ($adminView)
<details class="guest-portal-card p-4">
    <summary class="cursor-pointer text-sm font-semibold text-slate-600">Cleaner directions (what the cleaner sees)</summary>
    <div class="mt-4">
@endif
    @if (is_null($session->parking_needed))
        <div class="guest-portal-card p-6">
            <p class="guest-status-kicker">Getting in</p>
            <h1 class="guest-status-title">Will you be parking at the property?</h1>
            <p class="mt-2 text-sm text-slate-600">We'll show you the right directions for how you're arriving.</p>
            @if ($canAnswerParking)
                <div class="mt-5 flex gap-3">
                    <form method="POST" action="{{ route('sessions.parking', $session) }}" class="flex-1 flex">
                        @csrf
                        <input type="hidden" name="parking_needed" value="1">
                        <button type="submit" class="guest-primary-btn flex-1">Yes, I'll park</button>
                    </form>
                    <form method="POST" action="{{ route('sessions.parking', $session) }}" class="flex-1 flex">
                        @csrf
                        <input type="hidden" name="parking_needed" value="0">
                        <button type="submit" class="guest-outline-btn flex-1">No parking</button>
                    </form>
                </div>
            @endif
        </div>
    @else
        <div id="step-wizard-parking-wrapper">
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

        <div id="getting-in-reopen" style="display:none" class="mt-3 text-center">
            <button type="button" id="getting-in-reopen-btn" class="guest-outline-btn">Show directions again</button>
        </div>

        @if ($canAnswerParking)
            <form method="POST" action="{{ route('sessions.parking', $session) }}" class="mt-3 text-center">
                @csrf
                <input type="hidden" name="parking_needed" value="{{ $session->parking_needed ? 0 : 1 }}">
                <button type="submit" class="text-xs font-semibold text-slate-500 underline">
                    {{ $session->parking_needed ? "Actually, I'm not parking" : "Actually, I need parking" }}
                </button>
            </form>
        @endif

        <script>
        (function () {
            var done = document.getElementById("wizard-done-parking");
            var reopen = document.getElementById("getting-in-reopen");
            var btn = document.getElementById("getting-in-reopen-btn");
            if (!done || !reopen || !btn) return;
            var key = "getting_in_ack_{{ $session->id }}_{{ (int) $session->parking_needed }}_{{ $session->status }}_{{ (int) \App\Services\CleanerAccessSteps::locationVerified($session) }}";
            function reveal() { var g = document.getElementById("pending-gate"); if (g) g.style.display = ""; }
            function collapse() {
                reveal();
                var root = document.getElementById("step-wizard-parking");
                var wrap = document.getElementById("step-wizard-parking-wrapper");
                if (root) root.style.display = "none";
                if (wrap) wrap.style.display = "none";
                reopen.style.display = "";
            }
            try { if (localStorage.getItem(key)) collapse(); } catch (e) {}
            done.addEventListener("click", function () {
                reopen.style.display = "";
                try { localStorage.setItem(key, "1"); } catch (e) {}
                reveal();
            });
            btn.addEventListener("click", function () {
                var root = document.getElementById("step-wizard-parking");
                var wrap = document.getElementById("step-wizard-parking-wrapper");
                if (wrap) wrap.style.display = "";
                if (root) root.style.display = "";
                reopen.style.display = "none";
            });
        })();
        </script>
    @endif
@if ($adminView)
    </div>
</details>
@endif
</div>
