@props(['steps', 'type' => 'checkin', 'nextSection' => 'guest-guide-section', 'bookingId' => 'PREVIEW', 'token' => 'preview', 'showBackLink' => false, 'kicker' => null, 'doneLabel' => null])
@php $siteLogo = \App\Models\Setting::getValue('site_logo'); @endphp
@php $total = count($steps); @endphp
@if($total > 0)
<div id="step-wizard-{{ $type }}" class="guest-portal-card guest-portal-card--wizard">
    <div class="guest-status-bar">
        <div>
            @if($siteLogo)
                <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
            @endif
        </div>
        <span class="guest-status-pill is-checked">
            <span id="wizard-counter-{{ $type }}" class="text-xs font-bold">1 / {{ $total }}</span>
        </span>
    </div>
    <div class="px-6 pt-4">
        @if($showBackLink)
            <button type="button" id="wizard-back-to-guide-{{ $type }}" class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-slate-800">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back to guide
            </button>
        @endif
        <p class="guest-status-kicker">{{ $kicker ?? ($type === 'checkout' ? 'Check-out' : ($type === 'parking' ? 'Parking' : 'Check-in')) }}</p>
        <h1 class="guest-status-title" id="wizard-title-{{ $type }}">{{ $steps[0]['title'] }}</h1>
    </div>

    <div class="wizard-body">
        <div class="wizard-scroll">
            @foreach($steps as $i => $step)
            @php $allImages = array_values(array_filter(array_merge([$step['image'] ?? null], $step['images'] ?? []))); @endphp
            <div class="wizard-step" id="wizard-{{ $type }}-step-{{ $i }}" @if($i > 0) style="display:none" @endif>
                @if(count($allImages))
                    <div class="wizard-image-card mb-4">
                        <img id="wizard-main-img-{{ $type }}-{{ $i }}" src="{{ $allImages[0] }}" alt="{{ $step['title'] }}" class="w-full rounded-xl shadow-sm">
                        @if(count($allImages) > 1)
                            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                                @foreach(array_slice($allImages, 1) as $img)
                                    <img src="{{ $img }}" alt="{{ $step['title'] }}" loading="lazy" decoding="async" class="wizard-gallery-thumb h-20 w-20 shrink-0 cursor-pointer rounded-lg object-cover shadow-sm" data-type="{{ $type }}" data-step="{{ $i }}">
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                <div class="wizard-text-card">
                    <div class="prose-welcome text-base text-slate-700">{!! $step['content'] !!}</div>
                    @if(($step['action'] ?? 'content') === 'door_lock' && ($step['lock_id'] ?? null))
                        <x-lock-card class="mt-5" :booking-id="$bookingId" :token="$token" :lock-id="$step['lock_id']" :lock-status="$step['lock_status'] ?? null" :auto-checkin="$type === 'checkin'" :auto-checkout="$type === 'checkout'" />
                    @elseif(($step['action'] ?? 'content') === 'door_lock')
                        <p class="mt-5 text-sm font-bold text-slate-500 text-center">No lock is configured for this property yet.</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="wizard-dots-row">
            @foreach($steps as $i => $step)
                <span class="wizard-dot h-2 w-2 rounded-full {{ $i === 0 ? 'bg-slate-900' : 'bg-slate-200' }}" id="wizard-dot-{{ $type }}-{{ $i }}"></span>
            @endforeach
        </div>

        @if($type === 'checkout')
        <div id="checkout-final-warning" style="display:none" class="checkout-flash mx-6 mb-3 rounded-2xl border-2 border-red-600 p-4 text-center">
            <p class="text-lg font-extrabold uppercase text-red-700">STOP. This is your last chance.</p>
            <p class="mt-1 text-sm font-bold text-red-800">Only press Check out when you are physically walking out of the building. Checking out ends your access to the guest guide, door codes and the unit. Do not check out early. We will not be able to reactivate your access once this process has started.</p>
        </div>
        <div id="checkout-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-red-900/80 p-4">
            <div class="checkout-flash w-full max-w-md rounded-2xl border-4 border-red-600 p-6 text-center">
                <h2 class="text-2xl font-extrabold uppercase text-red-700">Final warning</h2>
                <p class="mt-3 text-base font-bold text-red-900">Only continue if you are physically walking out of the building right now. If you are checking out early, do not check out.</p>
                <p class="mt-3 text-sm font-bold text-red-800">You will lose access to the guest guide, door codes and the unit. We will not be able to reactivate your access once this process has started.</p>
                <div class="mt-6 flex gap-3">
                    <button type="button" id="checkout-confirm-cancel" class="guest-outline-btn flex-1">Not Yet</button>
                    <button type="button" id="checkout-confirm-proceed" class="guest-primary-btn is-go flex-1" disabled style="opacity:.5">Check me out</button>
                </div>
            </div>
        </div>
        <p id="checkout-gate-status" class="px-6 pb-2 text-center text-sm font-semibold text-slate-500"></p>
        <style>@keyframes checkoutFlash{0%,100%{background:#fef2f2}50%{background:#fecaca}}.checkout-flash{animation:checkoutFlash 1s infinite}</style>
        @endif
        <div class="wizard-nav">
            <button type="button" id="wizard-prev-{{ $type }}" class="guest-outline-btn flex-1" style="display:none">Previous</button>
            <button type="button" id="wizard-next-{{ $type }}" class="guest-primary-btn flex-1" @if($total === 1) style="display:none" @endif>Next</button>
            <button type="button" id="wizard-done-{{ $type }}" class="guest-primary-btn is-go flex-1" @if($total > 1) style="display:none" @endif>
                <x-icon name="check" class="h-4 w-4" />
                {{ $doneLabel ?? ($type === "checkout" ? "Check out" : ($type === "checkin" ? "I'm Checked In!" : "Continue to Guide")) }}
            </button>
        </div>
    </div>
</div>


<script>
(function() {
    var type = "{{ $type }}";
    var total = {{ $total }};
    var current = 0;
    var titles = @json(array_column($steps, 'title'));

    document.querySelectorAll('.wizard-gallery-thumb[data-type="' + type + '"]').forEach(function (thumb) {
        thumb.addEventListener("click", function () {
            var step = thumb.dataset.step;
            var main = document.getElementById("wizard-main-img-" + type + "-" + step);
            if (!main) return;
            var swap = main.src;
            main.src = thumb.src;
            thumb.src = swap;
        });
    });

    function goTo(n) {
        document.getElementById("wizard-" + type + "-step-" + current).style.display = "none";
        document.getElementById("wizard-dot-" + type + "-" + current).className = "wizard-dot h-2 w-2 rounded-full bg-slate-200";
        current = n;
        document.getElementById("wizard-" + type + "-step-" + current).style.display = "";
        document.getElementById("wizard-dot-" + type + "-" + current).className = "wizard-dot h-2 w-2 rounded-full bg-slate-900";
        document.getElementById("wizard-title-" + type).textContent = titles[current];
        document.getElementById("wizard-counter-" + type).textContent = (current + 1) + " / " + total;
        document.getElementById("wizard-prev-" + type).style.display = current === 0 ? "none" : "";
        document.getElementById("wizard-next-" + type).style.display = current === total - 1 ? "none" : "";
        document.getElementById("wizard-done-" + type).style.display = current === total - 1 ? "" : "none";
        var scrollEl = document.querySelector("#step-wizard-" + type + " .wizard-scroll");
        if (scrollEl) scrollEl.scrollTo({top: 0, behavior: "smooth"});
        armStep();
    }

    document.getElementById("wizard-next-" + type).addEventListener("click", function() { if (current < total - 1) goTo(current + 1); });

    var stepTimer = null;
    function armStep() {
        if (type !== "checkout") return;
        if (stepTimer) clearInterval(stepTimer);
        var isLast = current === total - 1;
        var btn = document.getElementById(isLast ? "wizard-done-" + type : "wizard-next-" + type);
        var status = document.getElementById("checkout-gate-status");
        var warn = document.getElementById("checkout-final-warning");
        var scrollEl = document.querySelector("#step-wizard-" + type + " .wizard-scroll");
        var left = isLast ? 10 : 8;
        function atBottom() { return !scrollEl || scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight < 24; }
        function refresh() {
            var ok = left <= 0 && atBottom();
            btn.disabled = !ok;
            btn.style.opacity = ok ? "1" : ".5";
            if (status) status.textContent = ok ? "" : (left > 0 ? "Please read this step. You can continue in " + left + "s." : "Scroll to the bottom to continue.");
        }
        if (warn) warn.style.display = isLast ? "" : "none";
        if (scrollEl) scrollEl.onscroll = refresh;
        refresh();
        stepTimer = setInterval(function() {
            var root = document.getElementById("step-wizard-" + type);
            if (root && root.offsetParent !== null && left > 0) left--;
            refresh();
            if (left <= 0) clearInterval(stepTimer);
        }, 1000);
    }
    armStep();
    document.getElementById("wizard-prev-" + type).addEventListener("click", function() { if (current > 0) goTo(current - 1); });

    var backLink = document.getElementById("wizard-back-to-guide-" + type);
    if (backLink) {
        backLink.addEventListener("click", function() {
            var wizardRoot = document.getElementById("step-wizard-" + type);
            var guideSection = document.getElementById("{{ $nextSection }}");
            var wizardWrapper = wizardRoot ? wizardRoot.closest('[id$="-wizard-wrapper"]') : null;
            if (wizardWrapper) {
                wizardWrapper.style.display = "none";
            } else if (wizardRoot) {
                wizardRoot.style.display = "none";
            }
            if (guideSection) {
                guideSection.style.display = "";
            }
        });
    }

    var confirmTimer = null;
    function openFinalConfirm() {
        var modal = document.getElementById("checkout-confirm-modal");
        var proceed = document.getElementById("checkout-confirm-proceed");
        if (!modal || !proceed) return;
        var left = 5;
        modal.classList.remove("hidden");
        proceed.disabled = true;
        proceed.style.opacity = ".5";
        proceed.textContent = "Check me out (" + left + ")";
        if (confirmTimer) clearInterval(confirmTimer);
        confirmTimer = setInterval(function() {
            left--;
            if (left <= 0) {
                clearInterval(confirmTimer);
                proceed.disabled = false;
                proceed.style.opacity = "1";
                proceed.textContent = "Check me out";
            } else {
                proceed.textContent = "Check me out (" + left + ")";
            }
        }, 1000);
    }

    function runConfirm() {
        var confirmUrl = type === "checkin"
            ? "{{ route('guest.confirm-checkin', [$bookingId, $token]) }}"
            : "{{ route('guest.confirm-checkout', [$bookingId, $token]) }}";
        var doneBtn = document.getElementById("wizard-done-" + type);
        doneBtn.disabled = true;
        fetch(confirmUrl, {
            method: "POST",
            headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", "Content-Type": "application/json" }
        }).then(function(response) {
            if (!response.ok) {
                return response.json().catch(function() { return {}; }).then(function(d) {
                    var e = new Error("Request failed with status " + response.status);
                    e.serverMessage = d.message || null;
                    throw e;
                });
            }
            if (type === "checkout") {
                // Reload so the server recomputes state and serves the real
                // "all checked out" locked page immediately — toggling a
                // client-side section here left the guide/menu visible and
                // clickable until the next full navigation (task 29).
                window.location.reload();
                return;
            }
            var modal = document.getElementById("checkout-confirm-modal");
            if (modal) modal.classList.add("hidden");
            document.getElementById("step-wizard-" + type).style.display = "none";
            var wrapper = document.getElementById("step-wizard-" + type + "-wrapper");
            if (wrapper) wrapper.style.display = "none";
            var next = document.getElementById("{{ $nextSection }}-wrapper") || document.getElementById("{{ $nextSection }}");
            if (next) next.style.display = "";
            window.scrollTo({top: 0, behavior: "smooth"});
        }).catch(function(err) {
            var modalEl = document.getElementById("checkout-confirm-modal");
            if (modalEl) modalEl.classList.add("hidden");
            doneBtn.disabled = false;
            var proceedBtn = document.getElementById("checkout-confirm-proceed");
            if (proceedBtn) proceedBtn.disabled = false;
            var errEl = document.getElementById("wizard-error-" + type);
            if (!errEl) {
                errEl = document.createElement("p");
                errEl.id = "wizard-error-" + type;
                errEl.className = "mt-3 text-sm font-semibold text-red-600 text-center";
                doneBtn.insertAdjacentElement("afterend", errEl);
            }
            errEl.textContent = (err && err.serverMessage) ? err.serverMessage : "Something went wrong. Please check your connection and try again, or refresh the page.";
        });
    }

    document.getElementById("wizard-done-" + type).addEventListener("click", function() {
        if (type === "checkout") {
            openFinalConfirm();
            return;
        }
        if (type === "checkin") {
            runConfirm();
        } else {
            document.getElementById("step-wizard-" + type).style.display = "none";
            var wrapper = document.getElementById("step-wizard-" + type + "-wrapper");
            if (wrapper) wrapper.style.display = "none";
            var next = document.getElementById("{{ $nextSection }}-wrapper") || document.getElementById("{{ $nextSection }}");
            if (next) next.style.display = "";
            window.scrollTo({top: 0, behavior: "smooth"});
        }
    });

    if (type === "checkout") {
        var cancelBtn = document.getElementById("checkout-confirm-cancel");
        var proceedBtn = document.getElementById("checkout-confirm-proceed");
        var tickBox = document.getElementById("checkout-confirm-tick");
        if (tickBox && proceedBtn) {
            tickBox.addEventListener("change", function() {
                proceedBtn.disabled = !tickBox.checked;
                proceedBtn.style.opacity = tickBox.checked ? "1" : ".5";
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener("click", function() {
                document.getElementById("checkout-confirm-modal").classList.add("hidden");
            });
        }
        if (proceedBtn) {
            proceedBtn.addEventListener("click", function() {
                proceedBtn.disabled = true;
                runConfirm();
            });
        }
    }
})();
</script>
@endif
