<div class="rounded-xl border border-slate-200 p-4">
    <p class="text-sm font-bold">Vehicle Information <span class="text-red-600">*</span></p>
    <p class="mt-1 text-sm text-slate-600">Please take a clear photo of your vehicle's license plate with your camera.</p>

    <div id="plate-scan-fields" class="my-3 space-y-3">
<style>#plate-scan-fields > :not(#plate-scan-status){display:none}</style>
        <div id="plate-scan-status" class="hidden rounded-lg border p-3 text-sm"></div>
        <div>
            <label for="license_plate" class="guest-stay-tile-label block mb-1">Plate number</label>
            <input type="text" name="license_plate" id="license_plate" value="{{ old('license_plate', $booking->license_plate) }}" maxlength="12" autocapitalize="characters" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase tracking-wider">
        </div>
        <div>
            <label for="license_plate_state" class="guest-stay-tile-label block mb-1">State</label>
            <select name="license_plate_state" id="license_plate_state" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">Select state</option>
                @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $st)
                    <option value="{{ $st }}" @selected(old('license_plate_state', $booking->license_plate_state) === $st)>{{ $st }}</option>
                @endforeach
                <option value="OT" @selected(old('license_plate_state', $booking->license_plate_state) === 'OT')>Other / not US</option>
            </select>
        </div>
    </div>

    <script>
    (function () {
        var scanUrl = "{{ route('guest.scan-plate', [$booking->booking_id, $booking->token]) }}";
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.content : "";
        window.plateStatus = function (msg, tone) {
            var el = document.getElementById("plate-scan-status");
            if (!el) return;
            el.className = "rounded-lg border p-3 text-sm " + (tone === "warn" ? "bg-amber-50 border-amber-200 text-amber-800" : tone === "ok" ? "bg-green-50 border-green-200 text-green-800" : "bg-slate-50 border-slate-200 text-slate-700");
            el.textContent = msg;
        };
        function downscale(file, cb) {
            var img = new Image();
            var url = URL.createObjectURL(file);
            img.onload = function () {
                var max = 1600, sc = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                var c = document.createElement("canvas");
                c.width = Math.round(img.naturalWidth * sc); c.height = Math.round(img.naturalHeight * sc);
                c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(url);
                c.toBlob(function (b) { cb(b || file); }, "image/jpeg", 0.88);
            };
            img.onerror = function () { URL.revokeObjectURL(url); cb(file); };
            img.src = url;
        }
        document.addEventListener("change", function (e) {
            var el = e.target;
            if (!el || el.id !== "license_plate_photo" || !el.files || !el.files[0]) return;
            window.plateStatus("Reading your plate…");
            downscale(el.files[0], function (blob) {
                var fd = new FormData();
                fd.append("frame", blob, "plate.jpg");
                fd.append("_token", csrf);
                fetch(scanUrl, { method: "POST", body: fd, headers: { "Accept": "application/json" } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.state) { document.getElementById("license_plate_state").value = res.state; }
                        if (res && res.ok && res.plate) {
                            document.getElementById("license_plate").value = res.plate;
                            window.plateStatus((res.confidence || 0) >= 0.8 ? "Plate read ✓" : "Plate photo captured ✓", (res.confidence || 0) >= 0.8 ? "ok" : "warn");
                        } else {
                            window.plateStatus("Photo captured ✓", "ok");
                        }
                    })
                    .catch(function () { window.plateStatus("Photo captured ✓", "ok"); });
            });
        });
    })();
    </script>

    <button type="button" id="plate-cam-open" class="mb-3 w-full rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white">Take plate photo with camera</button>
    <div id="plate-cam-modal" class="hidden" style="position:fixed;inset:0;z-index:99998;background:#000;flex-direction:column;">
        <div style="position:relative;flex:1;min-height:0;overflow:hidden;">
            <video id="plate-cam-video" playsinline muted autoplay style="width:100%;height:100%;object-fit:cover;"></video>
            <div style="position:absolute;left:8%;right:8%;top:50%;transform:translateY(-50%);aspect-ratio:2/1;border:3px solid rgba(255,255,255,.9);border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,.45);pointer-events:none;"></div>
            <p style="position:absolute;left:0;right:0;top:16px;text-align:center;color:#fff;font-size:14px;padding:0 16px;">Fit the whole plate inside the frame. Avoid glare.</p>
        </div>
        <div style="position:relative;z-index:2;flex-shrink:0;display:flex;gap:12px;padding:16px;padding-bottom:calc(16px + env(safe-area-inset-bottom,0px));background:#000;">
            <button type="button" id="plate-cam-cancel" style="flex:1;padding:14px;border-radius:12px;background:#374151;color:#fff;font-weight:600;">Cancel</button>
            <button type="button" id="plate-cam-capture" style="flex:2;padding:14px;border-radius:12px;background:#fff;color:#111;font-weight:700;">Capture</button>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById("plate-cam-modal");
        var video = document.getElementById("plate-cam-video");
        var openBtn = document.getElementById("plate-cam-open");
        var stream = null;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { openBtn.style.display = "none"; return; }
        function stop() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            video.srcObject = null;
            modal.classList.add("hidden"); modal.style.display = "";
        }
        openBtn.addEventListener("click", function () {
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: "environment" }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false
            }).then(function (st) {
                stream = st; video.srcObject = st;
                modal.classList.remove("hidden"); modal.style.display = "flex";
                video.play().catch(function () {});
            }).catch(function () {
                window.plateStatus("Camera isn't available. Please allow camera access in your browser, or tap Call Guest Services and we'll help.", "warn");
            });
        });
        document.getElementById("plate-cam-cancel").addEventListener("click", stop);
        document.getElementById("plate-cam-capture").addEventListener("click", function () {
            if (!video.videoWidth) return;
            var vw = video.videoWidth, vh = video.videoHeight, cw = video.clientWidth, ch = video.clientHeight;
            var k = Math.max(cw / vw, ch / vh);
            var fw = cw * 0.84, fh = fw / 2, fx = cw * 0.08, fy = (ch - fh) / 2;
            var ox = (cw - vw * k) / 2, oy = (ch - vh * k) / 2;
            var sx = Math.max(0, (fx - ox) / k), sy = Math.max(0, (fy - oy) / k);
            var sw = Math.min(vw - sx, fw / k), sh = Math.min(vh - sy, fh / k);
            var sc = Math.min(1, 1600 / sw);
            var c = document.createElement("canvas");
            c.width = Math.round(sw * sc); c.height = Math.round(sh * sc);
            c.getContext("2d").drawImage(video, sx, sy, sw, sh, 0, 0, c.width, c.height);
            c.toBlob(function (blob) {
                stop();
                if (!blob) return;
                var file = new File([blob], "plate.jpg", { type: "image/jpeg" });
                var input = document.getElementById("license_plate_photo");
                var dt = new DataTransfer(); dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event("change", { bubbles: true }));
            }, "image/jpeg", 0.9);
        });
    })();
    </script>
    <input type="file" name="license_plate_photo" id="license_plate_photo" accept="image/*" class="hidden">

    <div class="guest-detail-banner mt-2">
        <span class="guest-detail-banner-icon"><x-icon name="phone" class="h-5 w-5" /></span>
        <div>
            <p class="guest-detail-banner-title">Can't provide this right now?</p>
            <p class="guest-detail-banner-sub">Use the Call Guest Services button and we'll take care of it for you.</p>
        </div>
    </div>
</div>
