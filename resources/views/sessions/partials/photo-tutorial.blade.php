@php
    $roomInfo = $session->property->rooms->mapWithKeys(fn ($rm) => [$rm->id => [
        'name' => $rm->name,
        'seconds' => max(1, (int) ($rm->photo_guide_seconds ?? 8)),
    ]]);
    $refs = $session->property->photoReferences->map(fn ($r) => [
        'url' => $r->imageUrl(),
        'caption' => $r->caption,
        'instructions' => $r->instructions,
        'room_id' => $r->room_id,
    ])->values();
    $isCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
    $active = $session->status === 'in_progress';
    $viewed = !is_null($session->photo_tutorial_viewed_at);
@endphp

@if ($refs->count() && $isCleaner && $active)
<style>
    #photo-tutorial:not(.hidden) { display: flex; }
    #pt-note { color: #1e293b; font-size: 1.05rem; line-height: 1.5; }
    #pt-note ul { list-style: disc; padding-left: 1.4em; }
    #pt-note ol { list-style: decimal; padding-left: 1.4em; }
    #pt-note p { margin: 0 0 .6em; }
    #pt-note h1, #pt-note h2, #pt-note h3 { font-weight: 800; margin: 0 0 .4em; }
    #pt-note img { max-width: 100%; height: auto; }
    #pt-ok:disabled { opacity: .5; }
</style>
<div id="photo-tutorial" class="hidden" style="position:fixed;inset:0;z-index:50;background:rgba(15,23,42,.75);align-items:center;justify-content:center;padding:12px">
    <div style="background:#fff;border-radius:18px;width:100%;max-width:520px;max-height:94vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.4)">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px">
            <h2 id="pt-title" style="font-size:1.25rem;font-weight:900;color:#020617;margin:0"></h2>
            <span id="pt-counter" style="font-size:.85rem;font-weight:700;color:#64748b"></span>
        </div>

        <div style="position:relative;background:#0f172a;height:40vh;min-height:200px;display:flex;align-items:center;justify-content:center;flex:none">
            <img id="pt-img" src="" alt="" style="max-width:100%;max-height:100%;object-fit:contain">
            <button type="button" id="pt-prev" aria-label="Previous" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#fff;font-size:2rem;background:rgba(0,0,0,.5);width:40px;height:56px;border-radius:10px">&#8249;</button>
            <button type="button" id="pt-next" aria-label="Next" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#fff;font-size:2rem;background:rgba(0,0,0,.5);width:40px;height:56px;border-radius:10px">&#8250;</button>
        </div>

        <div style="flex:1 1 auto;overflow:auto;padding:16px">
            <div id="pt-note"></div>
        </div>

        <div style="padding:12px 16px 16px;border-top:1px solid #e2e8f0">
            <p id="pt-error" style="color:#dc2626;font-weight:700;font-size:.85rem;margin:0 0 8px;text-align:center"></p>
            <button type="button" id="pt-ok" style="width:100%;background:#4f46e5;color:#fff;font-weight:800;font-size:1rem;padding:14px;border-radius:12px">I understand</button>
        </div>
    </div>
</div>

<button type="button" id="pt-reopen" aria-label="Photo guide" title="Photo guide"
    class="flex items-center justify-center w-14 h-14 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 hover:scale-110 active:scale-95"
    style="position:fixed;right:1.5rem;bottom:10.5rem;z-index:45;display:none">
    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
</button>

<script>
(function () {
    var all = @json($refs), info = @json($roomInfo), list = all, i = 0, modal = document.getElementById("photo-tutorial");
    var viewed = @json($viewed), stage = null, curRoom = null, curRid = null, timer = null, seenKey = "pt_seen_{{ $session->id }}";
    var seen = {}; try { if (location.search.indexOf("ptreset=1") > -1) localStorage.removeItem(seenKey); seen = JSON.parse(localStorage.getItem(seenKey) || "{}"); } catch (e) {}
    function $(id) { return document.getElementById(id); }
    function roomInfo(rid) { return (info && rid != null) ? info[String(rid)] : null; }
    function listFor(rid) {
        var l = all.filter(function (r) { return r.room_id == null || String(r.room_id) === String(rid); });
        if (!l.length && !viewed) l = all;
        return l;
    }
    function showPic(n) {
        i = n; var r = list[i], note = $("pt-note");
        $("pt-img").src = r.url;
        if (r.instructions) { note.innerHTML = r.instructions; note.parentNode.style.display = ""; }
        else if (r.caption) { note.textContent = r.caption; note.parentNode.style.display = ""; }
        else { note.innerHTML = ""; note.parentNode.style.display = "none"; }
        note.parentNode.scrollTop = 0;
        $("pt-counter").textContent = list.length > 1 ? (i + 1) + " / " + list.length : "";
        $("pt-prev").style.display = i === 0 ? "none" : "";
        $("pt-next").style.display = i === list.length - 1 ? "none" : "";
    }
    function unlock() { var ok = $("pt-ok"); ok.disabled = false; ok.textContent = "I understand"; }
    function startTimer(rid) {
        clearInterval(timer);
        var ok = $("pt-ok"), r = roomInfo(rid);
        if (seen[rid]) { unlock(); return; }
        var left = Math.max(1, parseInt(r && r.seconds, 10) || 8);
        ok.disabled = true; ok.textContent = "I understand (" + left + "s)";
        timer = setInterval(function () {
            left--;
            if (left <= 0) { clearInterval(timer); unlock(); return; }
            ok.textContent = "I understand (" + left + "s)";
        }, 1000);
    }
    function finish() {
        clearInterval(timer);
        $("pt-error").textContent = "";
        var rid = curRid;
        function done() {
            modal.classList.add("hidden");
            viewed = true;
            if (rid != null) { seen[rid] = 1; try { localStorage.setItem(seenKey, JSON.stringify(seen)); } catch (e) {} }
        }
        if (viewed) { done(); return; }
        fetch("{{ route('sessions.photo-tutorial', $session) }}", {
            method: "POST",
            headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" }
        }).then(function (r) { if (!r.ok) throw new Error(); done(); })
          .catch(function () { $("pt-error").textContent = "Something went wrong. Please try again."; });
    }
    function open(l, rid) {
        list = l; curRid = rid;
        var r = roomInfo(rid);
        $("pt-title").textContent = (r && r.name) ? r.name : "Photo guide";
        $("pt-error").textContent = "";
        modal.classList.remove("hidden");
        showPic(0);
        startTimer(rid);
    }
    $("pt-ok").onclick = finish;
    $("pt-next").onclick = function () { if (i < list.length - 1) showPic(i + 1); };
    $("pt-prev").onclick = function () { if (i > 0) showPic(i - 1); };
    $("pt-reopen").onclick = function () {
        var l = curRoom ? listFor(curRoom) : all; if (!l.length) l = all; open(l, curRoom);
    };

    var sx = null, card = modal;
    card.addEventListener("touchstart", function (e) { sx = e.touches[0].clientX; }, { passive: true });
    card.addEventListener("touchend", function (e) {
        if (sx === null) return;
        var dx = e.changedTouches[0].clientX - sx; sx = null;
        if (Math.abs(dx) < 50) return;
        if (dx < 0 && i < list.length - 1) showPic(i + 1);
        else if (dx > 0 && i > 0) showPic(i - 1);
    }, { passive: true });
    document.addEventListener("keydown", function (e) {
        if (modal.classList.contains("hidden")) return;
        if (e.key === "ArrowRight" && i < list.length - 1) showPic(i + 1);
        if (e.key === "ArrowLeft" && i > 0) showPic(i - 1);
    });

    function check() {
        if (stage !== "photos") return;
        var el = document.querySelector("[data-room-photos]");
        var rid = el ? el.getAttribute("data-room-id") : null;
        if (!rid || rid === curRoom) return;
        curRoom = rid;
        var l = listFor(rid);
        if (l.length && !seen[rid]) open(l, rid);
    }
    var t = null;
    new MutationObserver(function () { clearTimeout(t); t = setTimeout(check, 150); })
        .observe(document.body, { childList: true, subtree: true });
    window.addEventListener("session-stage", function (e) {
        stage = e.detail.stage;
        var onPhotos = stage === "photos";
        $("pt-reopen").style.display = onPhotos ? "" : "none";
        if (!onPhotos) { clearInterval(timer); modal.classList.add("hidden"); curRoom = null; }
        else { setTimeout(check, 150); }
    });
})();
</script>
@endif
