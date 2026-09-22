@props(['bookingId', 'token', 'lockId', 'lockLabel' => null, 'lockStatus' => null, 'autoCheckin' => false, 'autoCheckout' => false])
@php $uid = 'lockcard-' . \Illuminate\Support\Str::random(8); @endphp
<div id="{{ $uid }}" class="guest-lock-card flex flex-col items-center gap-3 p-4">
    @if($lockLabel)
        <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">{{ $lockLabel }}</p>
    @endif
    <button
        type="button"
        class="lock-toggle-btn flex items-center justify-center rounded-full text-white transition-colors duration-150 shadow-lg"
        style="width: 128px; height: 128px; background-color: {{ $lockStatus === true ? '#dc2626' : ($lockStatus === false ? '#16a34a' : '#94a3b8') }};"
        data-locked="{{ $lockStatus === true ? 'true' : ($lockStatus === false ? 'false' : '') }}"
        data-unlock-url="{{ route('guest.unlock-door', [$bookingId, $token, $lockId]) }}"
        data-lock-url="{{ route('guest.lock-door', [$bookingId, $token, $lockId]) }}"
        data-auto-checkin="{{ $autoCheckin ? 'true' : 'false' }}"
        data-auto-checkout="{{ $autoCheckout ? 'true' : 'false' }}"
        data-confirm-checkin-url="{{ route('guest.confirm-checkin', [$bookingId, $token]) }}"
        data-confirm-checkout-url="{{ route('guest.confirm-checkout', [$bookingId, $token]) }}"
    >
        <svg class="lock-toggle-icon" width="48" height="48" viewBox="0 0 48 48" fill="none">
            @if($lockStatus === false)
                <path d="M14 22V16C14 10.5 17.5 7 24 7C29 7 32.5 9.5 33.5 13.5" stroke="white" stroke-width="3.5" stroke-linecap="round" fill="none"></path>
            @else
                <path d="M14 22V16C14 10.5 17.5 7 24 7C30.5 7 34 10.5 34 16V22" stroke="white" stroke-width="3.5" stroke-linecap="round" fill="none"></path>
            @endif
            <rect x="10" y="20" width="28" height="21" rx="5" fill="white"></rect>
            <circle class="lock-toggle-keyhole" cx="24" cy="28" r="3" fill="{{ $lockStatus === true ? "#dc2626" : ($lockStatus === false ? "#16a34a" : "#94a3b8") }}"></circle>
            <rect class="lock-toggle-keyhole" x="22.5" y="29" width="3" height="6" rx="1.5" fill="{{ $lockStatus === true ? "#dc2626" : ($lockStatus === false ? "#16a34a" : "#94a3b8") }}"></rect>
        </svg>
    </button>
    <p class="lock-toggle-label text-center font-bold text-base" style="color: {{ $lockStatus === true ? '#dc2626' : ($lockStatus === false ? '#16a34a' : '#64748b') }};">
        {{ $lockStatus === true ? 'UNLOCK DOOR' : ($lockStatus === false ? 'LOCK DOOR' : 'STATUS UNAVAILABLE') }}
    </p>

    <div class="lock-progress-tracker w-full max-w-xs" style="display:none;">
        <div class="lock-progress-steps flex items-center justify-between gap-1">
            <div class="lock-progress-step flex-1 flex flex-col items-center gap-1" data-step="sending">
                <div class="lock-progress-dot rounded-full" style="width:10px;height:10px;background:#cbd5e1;"></div>
                <span class="text-xs text-slate-400">Sending</span>
            </div>
            <div class="lock-progress-line flex-1" style="height:2px;background:#e2e8f0;margin-bottom:16px;"></div>
            <div class="lock-progress-step flex-1 flex flex-col items-center gap-1" data-step="sent">
                <div class="lock-progress-dot rounded-full" style="width:10px;height:10px;background:#cbd5e1;"></div>
                <span class="text-xs text-slate-400">Sent</span>
            </div>
            <div class="lock-progress-line flex-1" style="height:2px;background:#e2e8f0;margin-bottom:16px;"></div>
            <div class="lock-progress-step flex-1 flex flex-col items-center gap-1" data-step="confirming">
                <div class="lock-progress-dot rounded-full" style="width:10px;height:10px;background:#cbd5e1;"></div>
                <span class="text-xs text-slate-400">Confirming</span>
            </div>
            <div class="lock-progress-line flex-1" style="height:2px;background:#e2e8f0;margin-bottom:16px;"></div>
            <div class="lock-progress-step flex-1 flex flex-col items-center gap-1" data-step="confirmed">
                <div class="lock-progress-dot rounded-full" style="width:10px;height:10px;background:#cbd5e1;"></div>
                <span class="text-xs text-slate-400">Confirmed</span>
            </div>
        </div>
        <p class="lock-progress-message mt-2 text-sm text-center text-slate-500"></p>
    </div>
</div>

<script>
(function() {
    var card = document.getElementById("{{ $uid }}");
    if (!card) return;
    var statusUrl = "{{ route('guest.lock-status', [$bookingId, $token, $lockId]) }}";
    var btn = card.querySelector(".lock-toggle-btn");
    var label = card.querySelector(".lock-toggle-label");
    var icon = card.querySelector(".lock-toggle-icon");
    var tracker = card.querySelector(".lock-progress-tracker");
    var progressMsg = card.querySelector(".lock-progress-message");
    var stepEls = {
        sending: card.querySelector('.lock-progress-step[data-step="sending"] .lock-progress-dot'),
        sent: card.querySelector('.lock-progress-step[data-step="sent"] .lock-progress-dot'),
        confirming: card.querySelector('.lock-progress-step[data-step="confirming"] .lock-progress-dot'),
        confirmed: card.querySelector('.lock-progress-step[data-step="confirmed"] .lock-progress-dot')
    };
    var STEP_ORDER = ["sending", "sent", "confirming", "confirmed"];

    var COLORS = { locked: "#dc2626", unlocked: "#16a34a", unknown: "#94a3b8" };
    var ACTIVE_DOT = "#2563eb";
    var DONE_DOT = "#059669";
    var ERROR_DOT = "#dc2626";

    var LOCKED_ICON_PATH = '<path d="M14 22V16C14 10.5 17.5 7 24 7C30.5 7 34 10.5 34 16V22" stroke="white" stroke-width="3.5" stroke-linecap="round" fill="none"></path><rect x="10" y="20" width="28" height="21" rx="5" fill="white"></rect><circle class="lock-toggle-keyhole" cx="24" cy="28" r="3"></circle><rect class="lock-toggle-keyhole" x="22.5" y="29" width="3" height="6" rx="1.5"></rect>';
    var UNLOCKED_ICON_PATH = '<path d="M14 22V16C14 10.5 17.5 7 24 7C29 7 32.5 9.5 33.5 13.5" stroke="white" stroke-width="3.5" stroke-linecap="round" fill="none"></path><rect x="10" y="20" width="28" height="21" rx="5" fill="white"></rect><circle class="lock-toggle-keyhole" cx="24" cy="28" r="3"></circle><rect class="lock-toggle-keyhole" x="22.5" y="29" width="3" height="6" rx="1.5"></rect>';

    var hideTimer = null;

    function showTracker() {
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        tracker.style.display = "";
    }

    function hideTrackerAfter(ms) {
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(function() {
            tracker.style.display = "none";
            resetSteps();
        }, ms);
    }

    function resetSteps() {
        STEP_ORDER.forEach(function(key) { stepEls[key].style.background = "#cbd5e1"; });
        progressMsg.textContent = "";
    }

    function setStep(stepKey, text) {
        showTracker();
        var currentIndex = STEP_ORDER.indexOf(stepKey);
        STEP_ORDER.forEach(function(key, index) {
            if (index < currentIndex) {
                stepEls[key].style.background = DONE_DOT;
            } else if (index === currentIndex) {
                stepEls[key].style.background = ACTIVE_DOT;
            } else {
                stepEls[key].style.background = "#cbd5e1";
            }
        });
        if (text) progressMsg.textContent = text;
    }

    function setStepDone(stepKey, text) {
        showTracker();
        var currentIndex = STEP_ORDER.indexOf(stepKey);
        STEP_ORDER.forEach(function(key, index) {
            stepEls[key].style.background = (index <= currentIndex) ? DONE_DOT : "#cbd5e1";
        });
        if (text) progressMsg.textContent = text;
    }

    function setError(text) {
        showTracker();
        STEP_ORDER.forEach(function(key) { stepEls[key].style.background = "#cbd5e1"; });
        stepEls.confirming.style.background = ERROR_DOT;
        progressMsg.textContent = text;
        progressMsg.className = "lock-progress-message mt-2 text-sm text-center text-red-600";
        // Do NOT auto-hide on error - the reload instruction should stay visible.
    }

    var COOLDOWN_SECONDS = 20;
    var cooldownInterval = null;

    function startCooldown(seconds, restoreLabelFn) {
        var remaining = seconds;
        btn.disabled = true;
        label.style.color = COLORS.unknown;

        function tick() {
            label.textContent = "Wait " + remaining + "s...";
            if (remaining <= 0) {
                clearInterval(cooldownInterval);
                cooldownInterval = null;
                btn.disabled = false;
                restoreLabelFn();
                return;
            }
            remaining -= 1;
        }

        if (cooldownInterval) clearInterval(cooldownInterval);
        tick();
        cooldownInterval = setInterval(tick, 1000);
    }

    function lockOutAfterFailure(message) {
        btn.disabled = true;
        label.textContent = "Reload page";
        label.style.color = COLORS.unknown;
        setError(message + " Please reload this page, then try again.");
    }

    function applyKeyholeColor(color) {
        card.querySelectorAll(".lock-toggle-keyhole").forEach(function(el) { el.setAttribute("fill", color); });
    }

    // When the guest unlocks to enter (check-in) or locks to leave
    // (check-out), close out the stay for them instead of waiting for the
    // "I'm checked in / checked out" button that so many guests never press.
    // Reloads so the server recomputes the portal state and shows the next
    // screen (the guide on arrival, the thank-you page at checkout).
    function autoTransitionIfNeeded(lockedNow) {
        var wantCheckin = lockedNow === false && btn.dataset.autoCheckin === "true";
        var wantCheckout = lockedNow === true && btn.dataset.autoCheckout === "true";
        if (!wantCheckin && !wantCheckout) return false;

        var url = wantCheckin ? btn.dataset.confirmCheckinUrl : btn.dataset.confirmCheckoutUrl;
        progressMsg.className = "lock-progress-message mt-2 text-sm text-center text-emerald-600";
        progressMsg.textContent = wantCheckin
            ? "Checked in! Loading your welcome guide..."
            : "Checked out! Thank you for staying with us...";

        fetch(url, {
            method: "POST",
            headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", "Content-Type": "application/json", "Accept": "application/json" }
        }).then(function() { window.location.reload(); })
          .catch(function() { window.location.reload(); });

        return true;
    }

    function renderButtonState(locked) {
        if (locked === true) {
            btn.dataset.locked = "true";
            btn.style.backgroundColor = COLORS.locked;
            label.style.color = COLORS.locked;
            label.textContent = "UNLOCK DOOR";
            icon.innerHTML = LOCKED_ICON_PATH;
            applyKeyholeColor(COLORS.locked);
        } else if (locked === false) {
            btn.dataset.locked = "false";
            btn.style.backgroundColor = COLORS.unlocked;
            label.style.color = COLORS.unlocked;
            label.textContent = "LOCK DOOR";
            icon.innerHTML = UNLOCKED_ICON_PATH;
            applyKeyholeColor(COLORS.unlocked);
        } else {
            btn.dataset.locked = "";
            btn.style.backgroundColor = COLORS.unknown;
            label.style.color = COLORS.unknown;
            label.textContent = "STATUS UNAVAILABLE";
        }
    }

    // The single source of truth. The button stays disabled the whole time
    // this is running. Only this function ever declares success or failure.
    function pollForResult(expectedLocked, attemptsLeft, cardState) {
        fetch(statusUrl, { headers: { "Accept": "application/json" } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var resolved = data.ok && data.locked === expectedLocked;
                if (resolved) {
                    if (cardState.corrected) return;
                    cardState.corrected = true;
                    renderButtonState(expectedLocked);
                    progressMsg.className = "lock-progress-message mt-2 text-sm text-center text-emerald-600";
                    setStepDone("confirmed", "Confirmed: door is " + (expectedLocked ? "locked" : "unlocked") + ".");
                    hideTrackerAfter(4000);
                    if (autoTransitionIfNeeded(expectedLocked)) {
                        return;
                    }
                    startCooldown(COOLDOWN_SECONDS, function() {
                        renderButtonState(expectedLocked);
                    });
                    return;
                }

                if (attemptsLeft <= 0) {
                    cardState.corrected = true;
                    lockOutAfterFailure("Couldn't confirm the door " + (expectedLocked ? "locked" : "unlocked") + " in time.");
                    return;
                }

                setTimeout(function() {
                    pollForResult(expectedLocked, attemptsLeft - 1, cardState);
                }, 1000);
            })
            .catch(function() {
                if (attemptsLeft <= 0) {
                    cardState.corrected = true;
                    lockOutAfterFailure("We sent the command but couldn't confirm it went through.");
                    return;
                }
                setTimeout(function() {
                    pollForResult(expectedLocked, attemptsLeft - 1, cardState);
                }, 1000);
            });
    }

    btn.addEventListener("click", function() {
        if (btn.dataset.locked === "") return; // status unknown, don't act blindly

        var isCurrentlyLocked = btn.dataset.locked === "true";
        var expectedLocked = !isCurrentlyLocked; // clicking toggles the state
        var url = isCurrentlyLocked ? btn.dataset.unlockUrl : btn.dataset.lockUrl;

        btn.disabled = true;
        btn.style.backgroundColor = COLORS.unknown;
        label.textContent = "Checking location...";
        label.style.color = COLORS.unknown;

        progressMsg.className = "lock-progress-message mt-2 text-sm text-center text-slate-500";
        setStep("sending", "Checking your location...");

        function sendCommand(coords) {
            label.textContent = isCurrentlyLocked ? "Unlocking..." : "Locking...";
            setStep("sending", "Sending command...");

            var body = coords ? JSON.stringify({ latitude: coords.latitude, longitude: coords.longitude }) : "{}";

            fetch(url, {
                method: "POST",
                headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", "Content-Type": "application/json" },
                body: body
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) {
                    renderButtonState(isCurrentlyLocked);
                    setError(data.error || "Something went wrong.");
                    startCooldown(COOLDOWN_SECONDS, function() {
                        renderButtonState(isCurrentlyLocked);
                    });
                    return;
                }

                var cardState = { corrected: false };
                setStep("sent", "Command sent...");

                setTimeout(function() {
                    setStep("confirming", "Waiting for the door to respond (this can take up to 40 seconds)...");
                }, 300);

                // Button stays disabled the ENTIRE time. Nothing here declares
                // success on a timer — only pollForResult, once it gets a real
                // answer, unlocks the button and shows "Confirmed".
                pollForResult(expectedLocked, 40, cardState);
            })
            .catch(function() {
                btn.disabled = false;
                renderButtonState(isCurrentlyLocked);
                setError("Network error. Please try again.");
            });
        }

        // Location is required every time — a stale/one-time verification
        // isn't enough, this must reflect where the guest is right now.
        if (!navigator.geolocation) {
            btn.disabled = false;
            renderButtonState(isCurrentlyLocked);
            setError("Your browser doesn't support location, which is required to lock/unlock the door.");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                sendCommand({ latitude: position.coords.latitude, longitude: position.coords.longitude });
            },
            function() {
                btn.disabled = false;
                renderButtonState(isCurrentlyLocked);
                setError("Location access is required to lock/unlock the door. Please allow location and try again.");
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    });
})();
</script>
