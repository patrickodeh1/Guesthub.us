@if(count($preGpsSteps ?? []) > 0)
<div id="pregps-cta" class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-center">
    <button id="pregps-show-btn" type="button" class="guest-primary-btn is-go w-full">Show instructions and parking details</button>
    <p class="mt-3 text-xs leading-5 text-slate-500">A few things to know before you arrive.</p>
</div>
<div id="step-wizard-pregps-wrapper" style="display:none">
    <x-step-wizard :steps="$preGpsSteps" type="pregps" kicker="Before you arrive" done-label="Back" next-section="pregps-cta" />
</div>
<script>
(function () {
    var main = document.getElementById('pregps-main');
    var cta = document.getElementById('pregps-cta');
    var wrap = document.getElementById('step-wizard-pregps-wrapper');
    var show = document.getElementById('pregps-show-btn');
    var done = document.getElementById('wizard-done-pregps');
    if (show) show.addEventListener('click', function () {
        if (main) main.style.display = 'none';
        if (cta) cta.style.display = 'none';
        if (wrap) wrap.style.display = '';
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
    if (done) done.addEventListener('click', function () {
        if (main) main.style.display = '';
        if (wrap) wrap.style.display = 'none';
        if (cta) cta.style.display = '';
    });
})();
</script>
@endif
