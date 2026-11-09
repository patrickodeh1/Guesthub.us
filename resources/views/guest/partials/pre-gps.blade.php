@if(count($preGpsSteps ?? []) > 0)
@if(! empty($secondary))
<p id="pregps-cta" class="mt-3 text-center text-sm text-slate-500">Need the parking details again? <button id="pregps-show-btn" type="button" style="background:none;border:0;padding:0;cursor:pointer;font:inherit;font-weight:600;color:#334155;text-decoration:underline;" aria-haspopup="dialog" aria-controls="pregps-overlay">View instructions</button></p>
@else
<div id="pregps-cta" class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-center">
    <button id="pregps-show-btn" type="button" class="guest-primary-btn is-go w-full" aria-haspopup="dialog" aria-controls="pregps-overlay">Show instructions and parking details</button>
    <p class="mt-3 text-xs leading-5 text-slate-500">A few things to know before you arrive.</p>
</div>
@endif

<div id="pregps-overlay" role="dialog" aria-modal="true" aria-label="Before you arrive" hidden
     style="position:fixed;inset:0;z-index:9990;overflow-y:auto;background:rgba(15,23,42,.6);padding:16px;-webkit-overflow-scrolling:touch;">
    <div style="max-width:560px;margin:0 auto 8px;text-align:right;">
        <button id="pregps-close-btn" type="button" class="guest-outline-btn" style="background:#fff;">Close ✕</button>
    </div>
    <div id="step-wizard-pregps-wrapper" style="max-width:560px;margin:0 auto;">
        <x-step-wizard :steps="$preGpsSteps" type="pregps" kicker="Before you arrive" done-label="Close" next-section="pregps-cta" />
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('pregps-overlay');
    var show = document.getElementById('pregps-show-btn');
    var closeBtn = document.getElementById('pregps-close-btn');
    var done = document.getElementById('wizard-done-pregps');
    if (!overlay || !show) return;
    var isOpen = false, pushed = false, viewedSent = false;

    function markViewed() {
        if (viewedSent) return;
        viewedSent = true;
        document.dispatchEvent(new CustomEvent('pregps:viewed'));
        try {
            fetch("{{ route('guest.pre-arrival-viewed', [$booking->booking_id, $booking->token]) }}", {
                method: 'POST',
                keepalive: true,
                headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' }
            }).catch(function () {});
        } catch (e) {}
    }
    function checkReachedEnd() {
        var d = document.getElementById('wizard-done-pregps');
        if (d && d.style.display !== 'none') markViewed();
    }

    function resetWizard() {
        var card = document.getElementById('step-wizard-pregps');
        if (card) card.style.display = '';
        var wrap = document.getElementById('step-wizard-pregps-wrapper');
        if (wrap) wrap.style.display = '';
        var prev = document.getElementById('wizard-prev-pregps');
        var n = 0;
        while (prev && prev.style.display !== 'none' && n++ < 50) prev.click();
        overlay.scrollTop = 0;
    }
    function openPanel() {
        if (isOpen) return;
        isOpen = true;
        resetWizard();
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        try { history.pushState({ pregps: 1 }, ''); pushed = true; } catch (e) { pushed = false; }
        if (closeBtn) closeBtn.focus();
        checkReachedEnd();
    }
    function closePanel(fromPop) {
        if (!isOpen) return;
        isOpen = false;
        overlay.hidden = true;
        document.body.style.overflow = '';
        if (pushed && !fromPop) { pushed = false; history.back(); } else { pushed = false; }
        show.focus();
    }

    show.addEventListener('click', openPanel);
    if (closeBtn) closeBtn.addEventListener('click', function () { closePanel(false); });
    if (done) done.addEventListener('click', function () { closePanel(false); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closePanel(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanel(false); });
    window.addEventListener('popstate', function () { if (isOpen) closePanel(true); });
    var nextBtn = document.getElementById('wizard-next-pregps');
    if (nextBtn) nextBtn.addEventListener('click', checkReachedEnd);
})();
</script>
@endif