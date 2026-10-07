@php
    $refs = $session->property->photoReferences->map(fn ($r) => ['url' => $r->imageUrl(), 'caption' => $r->caption])->values();
    $isCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
    $active = $session->status === 'in_progress';
    $viewed = !is_null($session->photo_tutorial_viewed_at);
@endphp

@if ($refs->count() && $isCleaner && $active)
<div id="photo-tutorial" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3">
    <div class="guest-portal-card guest-portal-card--wizard w-full max-w-md" style="max-height:95vh;overflow:auto">
        <div class="guest-status-bar">
            <button type="button" id="pt-close" class="text-sm font-semibold text-slate-500 hover:text-slate-800" style="display:none">&times; Close</button>
            <span class="guest-status-pill is-checked"><span id="pt-counter" class="text-xs font-bold">1 / {{ $refs->count() }}</span></span>
        </div>
        <div class="px-6 pt-4">
            <p class="guest-status-kicker">Finished photos</p>
            <h1 class="guest-status-title">Photos you need to take</h1>
            <p class="mt-1 text-sm text-slate-600">Look at every picture. This is exactly what is expected.</p>
        </div>
        <div class="px-6 py-4">
            <img id="pt-img" src="" alt="" class="w-full rounded-xl shadow-sm">
            <p id="pt-caption" class="mt-3 text-base font-semibold text-slate-800"></p>
        </div>
        <div class="wizard-nav">
            <button type="button" id="pt-prev" class="guest-outline-btn flex-1" style="display:none">Previous</button>
            <button type="button" id="pt-next" class="guest-primary-btn flex-1">Next</button>
            <button type="button" id="pt-done" class="guest-primary-btn is-go flex-1" style="display:none">I understand, take photos</button>
        </div>
        <p id="pt-error" class="px-6 pb-3 text-center text-sm font-semibold text-red-600"></p>
    </div>
</div>

<button type="button" id="pt-reopen" aria-label="Photo guide" title="Photo guide"
    class="flex items-center justify-center w-14 h-14 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 hover:scale-110 active:scale-95"
    style="position:fixed;right:1.5rem;bottom:10.5rem;z-index:45;display:none">
    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
</button>

<script>
(function () {
    var refs = @json($refs), i = 0, modal = document.getElementById("photo-tutorial");
    function $(id) { return document.getElementById(id); }
    function show(n) {
        i = n;
        $("pt-img").src = refs[i].url;
        $("pt-caption").textContent = refs[i].caption || "";
        $("pt-counter").textContent = (i + 1) + " / " + refs.length;
        $("pt-prev").style.display = i === 0 ? "none" : "";
        $("pt-next").style.display = i === refs.length - 1 ? "none" : "";
        $("pt-done").style.display = i === refs.length - 1 ? "" : "none";
    }
    $("pt-next").onclick = function () { if (i < refs.length - 1) show(i + 1); };
    $("pt-prev").onclick = function () { if (i > 0) show(i - 1); };
    $("pt-reopen").onclick = function () { show(0); modal.classList.remove("hidden"); };
    $("pt-done").onclick = function () {
        var btn = this; btn.disabled = true; $("pt-error").textContent = "";
        fetch("{{ route('sessions.photo-tutorial', $session) }}", {
            method: "POST",
            headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" }
        }).then(function (r) {
            if (!r.ok) throw new Error();
            modal.classList.add("hidden");
            $("pt-reopen").style.display = "";
            viewed = true; updateClose();
        }).catch(function () {
            $("pt-error").textContent = "Something went wrong. Please try again.";
        }).finally(function () { btn.disabled = false; });
    };
    var viewed = @json($viewed);
    function updateClose() { $("pt-close").style.display = viewed ? "" : "none"; }
    $("pt-close").onclick = function () { modal.classList.add("hidden"); };
    updateClose();
    show(0);
    window.addEventListener('session-stage', function (e) {
        var onPhotos = e.detail.stage === 'photos';
        $("pt-reopen").style.display = onPhotos ? '' : 'none';
        if (onPhotos && !viewed) { modal.classList.remove('hidden'); }
        if (!onPhotos) { modal.classList.add('hidden'); }
    });
})();
</script>
@endif
