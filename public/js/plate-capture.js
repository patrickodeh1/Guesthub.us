/* Vehicle photo capture with live distance coaching. Loaded by guest/show.blade.php and
   guest/partials/vehicle-info-fields.blade.php. Tunables are the constants just below. */
(function () {
    "use strict";
    var openBtn = document.getElementById("plate-cam-open");
    var photoInput = document.getElementById("license_plate_photo");
    if (!openBtn || !photoInput) { return; }

    var LIVE_INTERVAL_MS = 1300;      // pause between coaching frames
    var LIVE_MAX_TRIES = 30;          // stop coaching after this many frames and let them capture
    var UNLOCK_CAPTURE_AFTER_MS = 12000;
    var STATE_GIVE_UP = 4;            // state unreadable this many times: let them pick it from the list
    var OK_STREAK_TO_CAPTURE = 2;     // consecutive good frames before auto-capture

    var scanUrl = openBtn.getAttribute("data-scan-url") || "";
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : "";
    var fieldsEl = document.getElementById("plate-scan-fields");
    var plateEl = document.getElementById("license_plate");
    var stateEl = document.getElementById("license_plate_state");
    var mmEl = document.getElementById("vehicle_make_model");
    var form = photoInput.form;
    var hasCam = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    var forceNative = !hasCam;
    var stream = null;
    var live = { active: false };

    window.plateStatus = function (msg, tone) {
        var el = document.getElementById("plate-scan-status");
        if (!el) { return; }
        var cls = tone === "warn" ? "bg-amber-50 border-amber-200 text-amber-800"
            : tone === "ok" ? "bg-green-50 border-green-200 text-green-800"
            : tone === "bad" ? "bg-red-50 border-red-200 text-red-800"
            : "bg-slate-50 border-slate-200 text-slate-700";
        el.className = "rounded-lg border p-3 text-sm " + cls;
        el.textContent = msg;
    };

    function reveal() { if (fieldsEl) { fieldsEl.classList.add("plate-revealed"); } }
    function mark(el, bad) { if (el) { el.style.borderColor = bad ? "#dc2626" : ""; } }
    [plateEl, stateEl, mmEl].forEach(function (el) {
        if (!el) { return; }
        el.addEventListener("input", function () { mark(el, false); delete el.dataset.auto; });
        el.addEventListener("change", function () { mark(el, false); delete el.dataset.auto; });
    });
    if (plateEl) {
        plateEl.addEventListener("input", function () {
            plateEl.value = plateEl.value.toUpperCase().replace(/[^A-Z0-9 \-]/g, "");
        });
    }

    // ---- camera modal -------------------------------------------------------------------
    var modal = document.createElement("div");
    modal.id = "plate-cam-modal";
    modal.style.cssText = "display:none;position:fixed;inset:0;z-index:99998;background:#000;flex-direction:column;";
    modal.innerHTML =
        '<div style="position:relative;flex:1;min-height:0;overflow:hidden;">' +
        '<video id="plate-cam-video" playsinline muted autoplay style="width:100%;height:100%;object-fit:cover;"></video>' +
        '<div id="plate-cam-guide" style="position:absolute;left:4%;right:4%;top:50%;transform:translateY(-50%);aspect-ratio:4/3;border:4px solid rgba(255,255,255,.9);border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,.45);pointer-events:none;transition:border-color .2s;"></div>' +
        '<div id="plate-cam-coach" style="position:absolute;left:12px;right:12px;top:12px;padding:12px 14px;border-radius:12px;text-align:center;font-size:17px;font-weight:700;line-height:1.3;color:#fff;background:rgba(15,23,42,.88);"></div>' +
        '<p style="position:absolute;left:12px;right:12px;bottom:10px;text-align:center;color:#fff;font-size:13px;margin:0;">Keep the whole back of the vehicle inside the box, close enough that the plate is easy to read.</p>' +
        '</div>' +
        '<div style="position:relative;z-index:2;flex-shrink:0;display:flex;gap:12px;padding:16px;padding-bottom:calc(16px + env(safe-area-inset-bottom,0px));background:#000;">' +
        '<button type="button" id="plate-cam-cancel" style="flex:1;padding:14px;border-radius:12px;background:#374151;color:#fff;font-weight:600;">Cancel</button>' +
        '<button type="button" id="plate-cam-capture" disabled style="flex:2;padding:14px;border-radius:12px;background:#fff;color:#111;font-weight:700;opacity:.45;">Capture</button>' +
        '</div>';
    document.body.appendChild(modal);
    var video = document.getElementById("plate-cam-video");
    var guideEl = document.getElementById("plate-cam-guide");
    var coachEl = document.getElementById("plate-cam-coach");
    var capBtn = document.getElementById("plate-cam-capture");

    var COACH = {
        no_vehicle: ["Point the camera at the back of the vehicle."],
        move_back: ["Move back a little: the whole vehicle must fit in the frame.", "Back up a bit more.", "A little farther back."],
        move_closer: ["Move closer to the vehicle.", "Closer...", "Keep coming closer: the plate should be easy to read.", "A little more..."],
        plate_unclear: ["Can't read the plate yet. Move a little closer and hold steady.", "Hold the phone steady and keep the plate in view."],
        state_unclear: ["Almost there. Step a little closer so the state name on the plate is readable.", "Just a bit closer..."]
    };

    function setCoach(msg, tone) {
        coachEl.textContent = msg;
        coachEl.style.background = tone === "ok" ? "rgba(22,163,74,.92)" : tone === "warn" ? "rgba(217,119,6,.92)" : "rgba(15,23,42,.88)";
        guideEl.style.borderColor = tone === "ok" ? "#4ade80" : tone === "warn" ? "#fbbf24" : "rgba(255,255,255,.9)";
    }
    function enableCapture(on, label) {
        // Auto-capture is the main path. The button only appears as a fallback.
        capBtn.disabled = !on;
        capBtn.style.display = on ? "" : "none";
        capBtn.style.opacity = "1";
        if (label) { capBtn.textContent = label; }
    }
    function stopLive() {
        if (live) { live.active = false; clearTimeout(live.timer); clearTimeout(live.unlockTimer); }
    }
    function closeModal() {
        stopLive();
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        video.srcObject = null;
        modal.style.display = "none";
    }

    function grab(maxSide, quality, cb) {
        var vw = video.videoWidth, vh = video.videoHeight;
        if (!vw || !vh) { cb(null); return; }
        var sc = Math.min(1, maxSide / Math.max(vw, vh));
        var c = document.createElement("canvas");
        c.width = Math.round(vw * sc); c.height = Math.round(vh * sc);
        c.getContext("2d").drawImage(video, 0, 0, c.width, c.height);
        c.toBlob(function (b) { cb(b); }, "image/jpeg", quality);
    }

    function startLive() {
        live = { active: true, tries: 0, okStreak: 0, last: "", repeat: 0, stateMiss: 0, busy: false, timer: null, unlockTimer: null };
        enableCapture(false, "Capture");
        setCoach("Point the camera at the back of the vehicle.", "info");
        live.unlockTimer = setTimeout(function () { if (live.active) { enableCapture(true, "Capture anyway"); } }, UNLOCK_CAPTURE_AFTER_MS);
        schedule(700);
    }
    function schedule(ms) {
        if (!live.active) { return; }
        live.timer = setTimeout(tick, ms || LIVE_INTERVAL_MS);
    }
    function tick() {
        if (!live.active || !stream) { return; }
        if (live.busy || !video.videoWidth) { schedule(500); return; }
        live.busy = true; live.tries++;
        grab(1600, 0.7, function (blob) {
            if (!blob) { live.busy = false; schedule(); return; }
            var fd = new FormData();
            fd.append("frame", blob, "live.jpg");
            fd.append("live", "1");
            fd.append("_token", csrf);
            fetch(scanUrl, { method: "POST", body: fd, headers: { "Accept": "application/json" }, credentials: "same-origin" })
                .then(function (r) { return r.json(); })
                .then(function (res) { live.busy = false; handleLive(res); })
                .catch(function () { live.busy = false; handleLive(null); });
        });
    }
    function handleLive(res) {
        if (!live.active) { return; }
        var hint = res && res.hint ? res.hint : "unavailable";
        if (hint === "unavailable") {
            setCoach("Make sure the whole back of the vehicle is in view, then tap Capture.", "info");
            enableCapture(true, "Capture");
            stopLive();
            return;
        }
        if (hint === live.last) { live.repeat++; } else { live.repeat = 0; live.last = hint; }

        if (hint === "ok") {
            live.okStreak++;
            setCoach("Perfect. Hold still...", "ok");
            if (live.okStreak >= OK_STREAK_TO_CAPTURE) { doCapture(); return; }
        } else {
            live.okStreak = 0;
            if (hint === "state_unclear") { live.stateMiss++; }
            if (hint === "state_unclear" && live.stateMiss >= STATE_GIVE_UP) {
                setCoach("We can't read the state. Tap Capture and you'll pick it from a list.", "warn");
                enableCapture(true, "Capture");
            } else {
                var list = COACH[hint] || COACH.no_vehicle;
                setCoach(list[live.repeat % list.length], hint === "no_vehicle" ? "info" : "warn");
            }
        }
        if (live.tries >= LIVE_MAX_TRIES) {
            setCoach("Take the photo when the whole back of the vehicle is in view.", "info");
            enableCapture(true, "Capture anyway");
            stopLive();
            return;
        }
        schedule();
    }

    function doCapture() {
        if (!stream || !video.videoWidth) { return; }
        stopLive();
        grab(2600, 0.92, function (blob) {
            closeModal();
            if (!blob) { return; }
            var file = new File([blob], "plate.jpg", { type: "image/jpeg" });
            var dt = new DataTransfer(); dt.items.add(file);
            photoInput.files = dt.files;
            photoInput.dispatchEvent(new Event("change", { bubbles: true }));
        });
    }
    capBtn.addEventListener("click", doCapture);
    document.getElementById("plate-cam-cancel").addEventListener("click", closeModal);

    openBtn.addEventListener("click", function () {
        if (forceNative) {
            photoInput.setAttribute("capture", "environment");
            photoInput.click();
            return;
        }
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: "environment" }, width: { ideal: 3840 }, height: { ideal: 2160 }, advanced: [{ focusMode: "continuous" }] },
            audio: false
        }).then(function (st) {
            stream = st; video.srcObject = st;
            modal.style.display = "flex";
            video.play().catch(function () {});
            try {
                var tr = st.getVideoTracks()[0];
                if (tr && tr.applyConstraints) { tr.applyConstraints({ advanced: [{ focusMode: "continuous" }] }).catch(function () {}); }
            } catch (e) {}
            startLive();
        }).catch(function () {
            forceNative = true;
            window.plateStatus("Camera access was blocked. Tap the button again to use your phone's camera instead, and stand back far enough that the whole back of the vehicle, the plate, make and model are readable.", "warn");
        });
    });

    // ---- scan result handling -----------------------------------------------------------
    function downscale(file, maxSide, quality, cb) {
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            var sc = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
            var c = document.createElement("canvas");
            c.width = Math.round(img.naturalWidth * sc); c.height = Math.round(img.naturalHeight * sc);
            c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob(function (b) { cb(b || file); }, "image/jpeg", quality);
        };
        img.onerror = function () { URL.revokeObjectURL(url); cb(file); };
        img.src = url;
    }
    function setAuto(el, val) {
        if (!el || !val) { return; }
        el.value = val; el.dataset.auto = "1";
    }
    function clearAuto() {
        [plateEl, stateEl, mmEl].forEach(function (el) {
            if (el && el.dataset.auto) { el.value = ""; delete el.dataset.auto; }
        });
    }
    function applyResult(res) {
        if (res && res.reason === "not_whole_vehicle") {
            photoInput.value = "";
            clearAuto();
            window.plateStatus("Photo rejected. Please stand back and photograph the entire back of your vehicle, with the plate, make and model all visible.", "bad");
            return;
        }
        reveal();
        clearAuto();
        if (res) {
            setAuto(plateEl, res.plate ? String(res.plate) : "");
            setAuto(stateEl, res.state ? String(res.state) : "");
            setAuto(mmEl, res.make_model ? String(res.make_model) : "");
        }
        var missing = [];
        if (plateEl && !plateEl.value) { missing.push("plate number"); mark(plateEl, true); }
        if (stateEl && !stateEl.value) { missing.push("state"); mark(stateEl, true); }
        if (mmEl) {
            var words = mmEl.value.trim().split(/\s+/).filter(Boolean).length;
            if (words < 2) { missing.push(words ? "model" : "make and model"); mark(mmEl, true); }
        }
        if (missing.length) {
            window.plateStatus("Please check the details below. We couldn't read your " + missing.join(", ") + ", so please fill that in. Every character of the plate must match your vehicle.", "warn");
        } else {
            window.plateStatus("We read " + (plateEl ? plateEl.value : "") + (stateEl && stateEl.value ? " (" + stateEl.value + ")" : "") + ". Please check every character, then tap Submit.", "ok");
        }
        openBtn.textContent = "Retake photo";
        if (window.plateShowSubmit) { window.plateShowSubmit(); }
    }

    photoInput.addEventListener("change", function () {
        if (!photoInput.files || !photoInput.files[0]) { return; }
        window.plateStatus("Reading your plate...");
        downscale(photoInput.files[0], 2600, 0.9, function (blob) {
            var fd = new FormData();
            fd.append("frame", blob, "plate.jpg");
            fd.append("_token", csrf);
            fetch(scanUrl, { method: "POST", body: fd, headers: { "Accept": "application/json" }, credentials: "same-origin" })
                .then(function (r) { return r.json(); })
                .then(applyResult)
                .catch(function () { applyResult(null); });
        });
    });

    // ---- submit guard (only on the standalone vehicle-info page) -------------------------
    if (form && window.plateEnforceOnSubmit) {
        form.noValidate = true;
        form.addEventListener("submit", function (e) {
            var err = null, focusEl = null, hasPhoto = !!(photoInput.files && photoInput.files[0]);
            if (!hasPhoto) {
                err = "Please take a photo of the back of your vehicle first.";
            } else if (!plateEl || plateEl.value.replace(/[^A-Za-z0-9]/g, "").length < 2) {
                err = "Please enter your plate number."; focusEl = plateEl;
            } else if (!stateEl || !stateEl.value) {
                err = "Please choose the state on your plate."; focusEl = stateEl;
            } else if (!mmEl || mmEl.value.trim().split(/\s+/).filter(Boolean).length < 2) {
                err = "Please enter your vehicle's make and model, for example Toyota Camry."; focusEl = mmEl;
            }
            if (err) {
                e.preventDefault();
                if (hasPhoto) { reveal(); }
                window.plateStatus(err, "warn");
                if (focusEl) { mark(focusEl, true); focusEl.focus(); }
                return;
            }
            var b = form.querySelector('button[type="submit"]');
            if (b) { b.disabled = true; }
        });
    }
})();
