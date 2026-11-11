(function () {
    var CFG = window.LEASE_ID_CONFIG || {};
    function idwSaveState() {}
    function idwClearState() {}
    function goToStep() {}
                var currentSide = "front";
                var stream = null;
                var idwCaptureGeneration = 0;
                var photoIdRequired = true;
                var isPassport = !!CFG.isPassport;
                // Which side(s) actually need a (re)capture right now — a side that's
                // already approved (or not cleared by a decline) should never be
                // re-prompted, even after the guest finishes capturing the other side.
                var idwFrontRequired = true;
                var idwBackRequired = !CFG.isPassport;

                // ── Device detection (mobile/tablet vs desktop) ────────────────────
                // Deliberately NOT a screen-width check — that's trivially spoofed by
                // resizing a desktop browser window. Instead this checks the actual
                // device/browser signals: the modern userAgentData.mobile flag where
                // available, falling back to a User-Agent match for older browsers,
                // plus a special case for iPads — they report a desktop "Macintosh"
                // UA by default, but a real Mac never reports multi-touch, so
                // platform === 'MacIntel' + maxTouchPoints > 1 reliably catches them.
                function idwIsMobileOrTablet() {
                    try {
                        if (navigator.userAgentData && typeof navigator.userAgentData.mobile === "boolean") {
                            if (navigator.userAgentData.mobile) return true;
                        }
                        var ua = navigator.userAgent || "";
                        if (/Android|iPhone|iPod|iPad|Mobile|Tablet/i.test(ua)) return true;
                        if (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1) return true;
                        return false;
                    } catch (e) {
                        // If detection throws for any reason, don't block a real guest —
                        // default to showing the camera flow.
                        return true;
                    }
                }

                // ── QR handoff for desktop guests ───────────────────────────────────
                // Photo capture only makes sense on a device with a camera in hand, so
                // desktop guests are pointed to their phone instead of being shown a
                // camera UI. Loaded lazily and only for desktop guests. Generated
                // entirely client-side — no external service call, no cost, and it
                // still works if this CDN is ever unreachable (the plain link below
                // the code is always shown as a fallback).
                var IDW_QRCODE_SRC = "https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js";
                var idwQrLoadStarted = false;

                function idwShowDesktopNotice() {
                    document.getElementById("idw-mobile-capture-ui").classList.add("hidden");
                    document.getElementById("idw-desktop-notice").classList.remove("hidden");
                    document.getElementById("idw-qr-link").textContent = window.location.href;

                    if (idwQrLoadStarted) return;
                    idwQrLoadStarted = true;
                    var script = document.createElement("script");
                    script.src = IDW_QRCODE_SRC;
                    script.onload = function() {
                        try {
                            new QRCode(document.getElementById("idw-qr-canvas"), {
                                text: window.location.href,
                                width: 180,
                                height: 180,
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        } catch (e) {
                            // QR render failed — the plain link underneath still works.
                        }
                    };
                    document.head.appendChild(script);
                }

                (function idwInitDeviceGate() {
                    if (!photoIdRequired) return;
                    if (!idwIsMobileOrTablet()) {
                        idwShowDesktopNotice();
                    }
                    var continueBtn = document.getElementById("idw-continue-desktop-btn");
                    if (continueBtn) {
                        continueBtn.addEventListener("click", function() {
                            document.getElementById("idw-desktop-notice").classList.add("hidden");
                            document.getElementById("idw-mobile-capture-ui").classList.remove("hidden");
                        });
                    }
                })();

                // ── OpenCV.js loader ────────────────────────────────────────────────
                // OpenCV.js is loaded lazily (only once the guest reaches this step) and
                // runs entirely client-side in the browser — this has no server/hosting
                // requirements at all (works the same on any host, cPanel included).
                // If it fails to load (blocked network, offline, slow connection) we fall
                // back to the old manual tap-to-capture button + JS blur check so nobody
                // gets stuck unable to upload their ID.
                var IDW_OPENCV_SRC = "https://docs.opencv.org/4.9.0/opencv.js";
                var idwCvReady = false;
                var idwCvFailed = false;
                var idwCvLoadStarted = false;

                function loadOpenCv(onReady, onFail) {
                    if (idwCvReady) { onReady(); return; }
                    if (idwCvFailed) { onFail(); return; }
                    if (!idwCvLoadStarted) {
                        idwCvLoadStarted = true;
                        var script = document.createElement("script");
                        script.src = IDW_OPENCV_SRC;
                        script.async = true;
                        script.onerror = function() { idwCvFailed = true; onFail(); };
                        document.head.appendChild(script);
                        // Give it a reasonable timeout — slow connections shouldn't block
                        // the guest from uploading their ID indefinitely.
                        setTimeout(function() {
                            if (!idwCvReady && !idwCvFailed) { idwCvFailed = true; onFail(); }
                        }, 8000);
                    }
                    var checkInterval = setInterval(function() {
                        if (window.cv && window.cv.Mat) {
                            // Some builds need onRuntimeInitialized; guard for both cases.
                            if (window.cv.onRuntimeInitialized !== undefined && !idwCvReady) {
                                window.cv["onRuntimeInitialized"] = function() {
                                    idwCvReady = true;
                                    clearInterval(checkInterval);
                                    onReady();
                                };
                            } else if (!idwCvReady) {
                                idwCvReady = true;
                                clearInterval(checkInterval);
                                onReady();
                            }
                        }
                    }, 100);
                }

                /*
                // ── On-device OCR (Tesseract.js) ───────────────────────────────
                // Reads the text off the captured ID in the browser, so the guest
                // gets "we read your ID" feedback before submitting, and the raw
                // text is sent to the server to verify name/DOB/expiry even when
                // the cloud Vision provider is down. Loaded lazily like OpenCV.
                var IDW_TESSERACT_SRC = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";
                var idwTesseractReady = false;
                var idwTesseractLoading = false;
                var idwTesseractFailed = false;

                function loadTesseract(onReady, onFail) {
                    if (window.Tesseract) { idwTesseractReady = true; onReady(); return; }
                    if (idwTesseractFailed) { if (onFail) onFail(); return; }
                    if (!idwTesseractLoading) {
                        idwTesseractLoading = true;
                        var s = document.createElement("script");
                        s.src = IDW_TESSERACT_SRC;
                        s.async = true;
                        s.onload = function() { idwTesseractReady = true; onReady(); };
                        s.onerror = function() { idwTesseractLoading = false; idwTesseractFailed = true; if (onFail) onFail(); };
                        document.head.appendChild(s);
                    }
                    setTimeout(function() {
                        if (!idwTesseractReady && !idwTesseractFailed) {
                            idwTesseractFailed = true;
                            if (onFail) onFail();
                        }
                    }, 8000);
                }

                // Crops the captured photo down to roughly the bottom band where a
                // passport's machine-readable zone (MRZ) sits -- the two/three fixed
                // width OCR-B lines at the very bottom of the data page. Cropping out
                // the header text, photo, and other printed fields before OCR is what
                // actually makes Tesseract usable here: without this it tries to read
                // everything on the page (headers, photo edges, etc.) and produces
                // mostly noise. A standard passport data page (ID-3, ISO/IEC 7810) has
                // its MRZ occupying about the bottom 20% of the page height, spanning
                // nearly the full width.
                // Wide, generous crop of the bottom third of the page -- deliberately
                // NOT tuned tight to the MRZ, since exact MRZ position varies enough
                // across countries' passport layouts and capture angles that a tight
                // crop risks cutting off the name line. Grabbing extra blank margin
                // above the MRZ costs OCR almost nothing; cutting off the name line
                // loses the one field we most need.
                function __idwCropBand(dataUrl, topFraction, cb) {
                    var img = new Image();
                    img.onload = function() {
                        var bandTop = Math.round(img.naturalHeight * topFraction);
                        var bandHeight = img.naturalHeight - bandTop;
                        var canvas = document.createElement("canvas");
                        var scale = 1.5;
                        canvas.width = Math.round(img.naturalWidth * scale);
                        canvas.height = Math.round(bandHeight * scale);
                        var ctx = canvas.getContext("2d");
                        ctx.drawImage(
                            img,
                            0, bandTop, img.naturalWidth, bandHeight,
                            0, 0, canvas.width, canvas.height
                        );
                        cb(canvas.toDataURL("image/jpeg", 0.95));
                    };
                    img.onerror = function() { cb(dataUrl); };
                    img.src = dataUrl;
                }

                // A single fixed "bottom 35%" crop clips the MRZ entirely whenever the
                // guest's capture doesn't perfectly fill the guide box (common -- the
                // guide overlay is decorative, nothing stops the document sitting
                // higher/lower in frame). Try a tight crop first (best signal-to-noise
                // when it lands correctly), and only pay for a second, wider OCR pass
                // if the tight one didn't actually find MRZ-shaped text.
                function __idwLooksLikeMrz(text) {
                    if (!text) return false;
                    var cleaned = text.toUpperCase().replace(/\s+/g, "");
                    // Two ICAO-style anchors: a long run of MRZ filler chars, and the
                    // DOB+check+sex+expiry digit signature used server-side too.
                    return /[A-Z0-9<]{20,}/.test(cleaned) && /\d{6}\d[MF<]\d{6}/.test(cleaned);
                }

                function __idwCropToBottomThird(dataUrl, cb) {
                    __idwCropBand(dataUrl, 0.65, cb);
                }

                // Hard ceiling on the whole OCR attempt. loadTesseract() already times
                // out script *loading* at 8s, but the actual recognize() call has no
                // ceiling of its own -- a stalled worker (slow device, huge image,
                // WASM init hiccup) can hang indefinitely with nothing to fall back to,
                // which is what leaves the guest stuck on "Reading your ID..." forever.
                var IDW_OCR_HARD_TIMEOUT_MS = 20000;

                function ocrIdImage(dataUrl, cb) {
                    var called = false;
                    var hardTimer = setTimeout(function() { finish(null); }, IDW_OCR_HARD_TIMEOUT_MS);
                    function finish(text) {
                        if (called) return;
                        called = true;
                        clearTimeout(hardTimer);
                        cb(text);
                    }

                    // The "mrz" language model (bundled at /tessdata/mrz.traineddata,
                    // BSD-3 licensed, trained by DoubangoTelecom specifically on the
                    // OCR-B font MRZs use) reads the MRZ character set far more
                    // reliably than the generic "eng" prose model -- "eng" was never
                    // trained on this font, which is the root cause behind most of the
                    // "<" -> random-letter misreads we've been patching around in the
                    // parser. Only use it for the whitelisted MRZ-crop passes; the
                    // full-page fallback pass (labels like "DOB", "EXP" on state IDs)
                    // stays on "eng" since that's prose text, not MRZ.
                    function recognizeOne(image, whitelist, onDone, useMrzModel) {
                        var done = false;
                        function once(v) { if (done) return; done = true; onDone(v); }
                        loadTesseract(function() {
                            if (!window.Tesseract || !window.Tesseract.recognize) { once(null); return; }
                            var opts = { logger: null };
                            if (whitelist) opts.tessedit_char_whitelist = whitelist;
                            var lang = "eng";
                            if (useMrzModel) {
                                lang = "mrz";
                                opts.langPath = "/tessdata";
                            }
                            window.Tesseract.recognize(image, lang, opts)
                                .then(function(r) { once(r && r.data && r.data.text ? r.data.text : null); })
                                .catch(function() {
                                    // If the mrz model fails to load (e.g. static asset
                                    // missing), fall back to eng rather than losing the
                                    // read entirely.
                                    if (useMrzModel) {
                                        window.Tesseract.recognize(image, "eng", { logger: null, tessedit_char_whitelist: whitelist })
                                            .then(function(r) { once(r && r.data && r.data.text ? r.data.text : null); })
                                            .catch(function() { once(null); });
                                    } else {
                                        once(null);
                                    }
                                });
                        }, function() { once(null); });
                    }

                    if (!isPassport) {
                        recognizeOne(dataUrl, null, finish);
                        return;
                    }

                    // Passports: run OCR twice and combine the text -- once on a wide
                    // bottom-third crop with the MRZ character set (best chance at a
                    // clean MRZ read regardless of exact line position), and once on
                    // the full page with no restriction (catches printed labelled
                    // fields, and acts as a fallback if the crop missed the MRZ
                    // entirely). The scanner on the server tries multiple parsing
                    // strategies against whatever text it's given, so combining
                    // sources here only helps -- it can't produce a false positive,
                    // it just gives it more to work with.
                    var mrzWhitelist = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<";
                    __idwCropBand(dataUrl, 0.65, function(tightCrop) {
                        recognizeOne(tightCrop, mrzWhitelist, function(tightText) {
                            if (__idwLooksLikeMrz(tightText)) {
                                // Good MRZ signal from the tight crop; still grab the
                                // full-page pass in parallel for printed-label fallback.
                                recognizeOne(dataUrl, null, function(fullText) {
                                    finish([tightText, fullText].filter(Boolean).join("\n"));
                                });
                                return;
                            }
                            // Tight crop missed it -- retake with a wider band (bottom
                            // half) in case the document sat higher in frame than the
                            // guide box assumed, plus the full-page fallback.
                            __idwCropBand(dataUrl, 0.45, function(wideCrop) {
                                var results = [tightText];
                                var remaining = 2;
                                function maybeFinish() {
                                    remaining--;
                                    if (remaining === 0) finish(results.filter(Boolean).join("\n"));
                                }
                                recognizeOne(wideCrop, mrzWhitelist, function(t) { results.push(t); maybeFinish(); }, true);
                                recognizeOne(dataUrl, null, function(t) { results.push(t); maybeFinish(); });
                            });
                        }, true);
                    });
                }

                */

                // Downsamples the captured image, computes a Laplacian-based sharpness
                // score (real focus/blur detection, not just brightness contrast), and a
                // grid edge-density heuristic as a lightweight (non-OCR) "is there
                // text-like structure here" signal.
                // NOTE: this is the legacy JS-only fallback, used only if OpenCV.js fails
                // to load. When OpenCV is available, idwCvCheckFrame() below is used
                // instead — it's faster and considerably more reliable.
                var IDW_DEBUG_LOG_SCORES = false; // tuning logs disabled

                function __idwGetGrayscaleSample(imgEl, targetWidth) {
                    var scale = targetWidth / imgEl.naturalWidth;
                    var w = targetWidth;
                    var h = Math.max(1, Math.round(imgEl.naturalHeight * scale));
                    var canvas = document.createElement("canvas");
                    canvas.width = w;
                    canvas.height = h;
                    var ctx = canvas.getContext("2d");
                    ctx.drawImage(imgEl, 0, 0, w, h);
                    var data = ctx.getImageData(0, 0, w, h).data;
                    var gray = new Float32Array(w * h);
                    for (var i = 0, p = 0; i < data.length; i += 4, p++) {
                        gray[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                    }
                    return { gray: gray, w: w, h: h };
                }

                function __idwLaplacianMap(gray, w, h) {
                    var lap = new Float32Array(w * h);
                    for (var y = 1; y < h - 1; y++) {
                        for (var x = 1; x < w - 1; x++) {
                            var idx = y * w + x;
                            var val =
                                gray[idx - w] + gray[idx + w] + gray[idx - 1] + gray[idx + 1] - 4 * gray[idx];
                            lap[idx] = val;
                        }
                    }
                    return lap;
                }

                function __idwSharpnessVariance(lap, w, h) {
                    var n = w * h;
                    var sum = 0;
                    for (var i = 0; i < n; i++) sum += lap[i];
                    var mean = sum / n;
                    var variance = 0;
                    for (var i = 0; i < n; i++) variance += Math.pow(lap[i] - mean, 2);
                    return variance / n;
                }

                function __idwTextLikeCellRatio(lap, w, h) {
                    var cellSize = 16;
                    var cols = Math.floor(w / cellSize);
                    var rows = Math.floor(h / cellSize);
                    if (cols < 1 || rows < 1) return 0;
                    var textLikeCells = 0;
                    var totalCells = 0;
                    for (var cy = 0; cy < rows; cy++) {
                        for (var cx = 0; cx < cols; cx++) {
                            var edgeCount = 0;
                            var strong = 0;
                            for (var y = cy * cellSize; y < (cy + 1) * cellSize && y < h; y++) {
                                for (var x = cx * cellSize; x < (cx + 1) * cellSize && x < w; x++) {
                                    var v = Math.abs(lap[y * w + x]);
                                    if (v > 15) edgeCount++;
                                    if (v > 60) strong++;
                                }
                            }
                            totalCells++;
                            var cellPixels = cellSize * cellSize;
                            var edgeDensity = edgeCount / cellPixels;
                            if (edgeDensity > 0.12 && edgeDensity < 0.55 && strong < cellPixels * 0.3) {
                                textLikeCells++;
                            }
                        }
                    }
                    return totalCells > 0 ? textLikeCells / totalCells : 0;
                }

                var IDW_SHARPNESS_MIN = 45;      // Laplacian variance floor - retune after real captures
                var IDW_TEXT_RATIO_MIN = 0.03;   // fraction of grid cells that must look text-like

                // Thresholds for the OpenCV auto-capture path — these run on a different
                // scale (full guide-box crop, not a 400px downsample) so they are NOT the
                // same numbers as the legacy JS thresholds above. Untested against real
                // captures yet — retune once real guest photos are reviewed.
                var IDW_CV_FILL_MIN = 0.80;       // detected document must fill ≥80% of the guide box
                var IDW_CV_SHARPNESS_MIN = 120;   // OpenCV Laplacian variance floor
                var IDW_CV_STABLE_FRAMES_NEEDED = 4; // consecutive good frames (~1s) before auto-capture

                function checkBlur(imgEl, warningEl) {
                    var sample = __idwGetGrayscaleSample(imgEl, 400);
                    var lap = __idwLaplacianMap(sample.gray, sample.w, sample.h);
                    var sharpness = __idwSharpnessVariance(lap, sample.w, sample.h);
                    var textRatio = __idwTextLikeCellRatio(lap, sample.w, sample.h);

                    if (IDW_DEBUG_LOG_SCORES) {
                        console.log("[ID capture check] sharpness=" + sharpness.toFixed(2) + " textRatio=" + textRatio.toFixed(3));
                    }

                    if (sharpness < IDW_SHARPNESS_MIN) {
                        warningEl.textContent = "Image is blurry. Please retake.";
                        warningEl.classList.remove("hidden");
                        return false;
                    }
                    if (textRatio < IDW_TEXT_RATIO_MIN) {
                        warningEl.textContent = "No legible ID text detected. Please retake with the ID clearly in frame.";
                        warningEl.classList.remove("hidden");
                        return false;
                    }
                    warningEl.classList.add("hidden");
                    return true;
                }

                // ── OpenCV-based live frame analysis ────────────────────────────────
                // Runs on a downsized crop of the guide-box region while the camera
                // stream is live, every ~250ms. Reports back:
                //   fillRatio  — how much of the guide box the detected document
                //                rectangle occupies (auto-capture requires the ID to
                //                actually fill the frame, not just be present in it)
                //   sharpness  — Laplacian variance (higher = sharper); this uses the
                //                same live frame the guide-box crop will actually use
                //                for the final capture, so by the time we auto-fire,
                //                the frame is already known-sharp (fixes the "haze"
                //                issue caused by capturing before autofocus settles)
                function idwCvCheckFrame(canvas) {
                    var src = cv.imread(canvas);
                    var gray = new cv.Mat();
                    cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

                    // Sharpness via Laplacian variance (OpenCV's own, much faster than
                    // the hand-rolled JS version above).
                    var lap = new cv.Mat();
                    cv.Laplacian(gray, lap, cv.CV_64F);
                    var mean = new cv.Mat();
                    var stddev = new cv.Mat();
                    cv.meanStdDev(lap, mean, stddev);
                    var sharpness = Math.pow(stddev.data64F[0], 2);

                    // Document-fill detection: edge detect, find the largest contour,
                    // compare its bounding box to the full frame.
                    var edges = new cv.Mat();
                    cv.Canny(gray, edges, 50, 150);
                    var contours = new cv.MatVector();
                    var hierarchy = new cv.Mat();
                    cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

                    var frameArea = canvas.width * canvas.height;
                    var bestArea = 0;
                    for (var i = 0; i < contours.size(); i++) {
                        var contour = contours.get(i);
                        var rect = cv.boundingRect(contour);
                        var area = rect.width * rect.height;
                        if (area > bestArea && area < frameArea * 1.02) {
                            bestArea = area;
                        }
                        contour.delete();
                    }
                    var fillRatio = frameArea > 0 ? bestArea / frameArea : 0;

                    src.delete(); gray.delete(); lap.delete(); mean.delete(); stddev.delete();
                    edges.delete(); contours.delete(); hierarchy.delete();

                    return { fillRatio: fillRatio, sharpness: sharpness };
                }

                var idwDetectionTimer = null;
                var idwStableFrameCount = 0;
                var idwAutoCaptureInFlight = false;
                var idwCaptureTimer = null;

                function stopDetectionLoop() {
                    if (idwDetectionTimer) { clearInterval(idwDetectionTimer); idwDetectionTimer = null; }
                    idwStableFrameCount = 0;
                    idwAutoCaptureInFlight = false;
                }

                function startDetectionLoop() {
                    stopDetectionLoop();
                    var video = document.getElementById("camera-stream");
                    var statusEl = document.getElementById("idw-capture-status");
                    var detectCanvas = document.createElement("canvas");

                    idwDetectionTimer = setInterval(function() {
                        if (idwAutoCaptureInFlight || !video.videoWidth) return;
                        var crop = __idwGetGuideCropRect(video);
                        // Downsize for speed — we only need this for detection, not the
                        // final saved image (final capture re-crops at full resolution).
                        var scale = Math.min(1, 260 / crop.w);
                        detectCanvas.width = Math.round(crop.w * scale);
                        detectCanvas.height = Math.round(crop.h * scale);
                        detectCanvas.getContext("2d").drawImage(
                            video, crop.x, crop.y, crop.w, crop.h, 0, 0, detectCanvas.width, detectCanvas.height
                        );

                        var result;
                        try {
                            result = idwCvCheckFrame(detectCanvas);
                        } catch (e) {
                            return; // transient decode error, just skip this frame
                        }

                        if (result.fillRatio >= IDW_CV_FILL_MIN && result.sharpness >= IDW_CV_SHARPNESS_MIN) {
                            idwStableFrameCount++;
                            statusEl.textContent = "Hold steady…";
                        } else if (result.fillRatio < IDW_CV_FILL_MIN) {
                            idwStableFrameCount = 0;
                            statusEl.textContent = "Move the ID closer so it fills the frame";
                        } else {
                            idwStableFrameCount = 0;
                            statusEl.textContent = "Hold steady for a clear photo…";
                        }

                        if (idwStableFrameCount >= IDW_CV_STABLE_FRAMES_NEEDED) {
                            idwAutoCaptureInFlight = true;
                            statusEl.textContent = "Capturing…";
                            performCapture();
                        }
                    }, 250);
                }

                function startCamera(side) {
                    currentSide = side;
                    var container = document.getElementById("camera-container");
                    var btnWrapper = document.getElementById("capture-btn-wrapper");
                    var frontTrigger = document.getElementById("upload-zone-trigger");
                    var backTrigger = document.getElementById("upload-zone-trigger-back");
                    var frontLabel = document.getElementById("upload-zone-trigger-front-label");
                    var backLabel = document.getElementById("upload-zone-trigger-back-label");
                    var activeTrigger = side === "front" ? frontTrigger : backTrigger;

                    frontTrigger.classList.add("hidden");
                    backTrigger.classList.add("hidden");
                    frontLabel.classList.add("hidden");
                    backLabel.classList.add("hidden");

                    activeTrigger.parentNode.insertBefore(container, activeTrigger);
                    activeTrigger.parentNode.insertBefore(btnWrapper, activeTrigger);

                    container.classList.remove("hidden");
                    btnWrapper.classList.remove("hidden");
                    document.getElementById("idw-capture-status").textContent = "Loading camera…";

                    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment", width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false })
                        .then(function(s) {
                            stream = s;
                            document.getElementById("camera-stream").srcObject = s;
                            var label;
                            if (side === "front") {
                                label = isPassport ? "Take a picture of the passport data page" : "Take a picture of the front of ID";
                            } else {
                                label = "Take a picture of the back of ID";
                            }
                            document.getElementById("camera-instruction-label").textContent = label;

                            loadOpenCv(function() {
                                document.getElementById("idw-capture-status").textContent = "Position the ID within the frame";
                                startDetectionLoop();
                            }, function() {
                                // OpenCV unavailable — still auto-capture (there is
                                // no manual shutter button): hold for a moment, then
                                // snap automatically.
                                document.getElementById("idw-capture-status").textContent = "Hold steady — capturing automatically…";
                                if (idwCaptureTimer) clearTimeout(idwCaptureTimer);
                                idwCaptureTimer = setTimeout(function() {
                                    idwCaptureTimer = null;
                                    performCapture();
                                }, 3500);
                            });
                        })
                        .catch(function() { alert("Camera access denied. Please allow camera permissions and try again."); });
                }

                function stopCamera() {
                    if (typeof idwStopPoll === "function") { idwStopPoll(); }
                    if (window.__idwFallbackTimer) { clearTimeout(window.__idwFallbackTimer); window.__idwFallbackTimer = null; }
                    stopDetectionLoop();
                    if (idwCaptureTimer) { clearTimeout(idwCaptureTimer); idwCaptureTimer = null; }
                    if (stream) { stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; }
                    document.getElementById("camera-container").classList.add("hidden");
                    document.getElementById("capture-btn-wrapper").classList.add("hidden");
                }

                // Crops the capture to the same guide-border rectangle the user sees
                // (CSS overlay is decorative only and has no effect on the raw video
                // frame, so we replicate the object-cover + centered-88%-box math here
                // in native video pixel coordinates).
                function __idwGetGuideCropRect(video) {
                    var containerAspect = 16 / 9;
                    var videoAspect = video.videoWidth / video.videoHeight;
                    var visW, visH, offX, offY;
                    if (videoAspect > containerAspect) {
                        visH = video.videoHeight;
                        visW = visH * containerAspect;
                        offX = (video.videoWidth - visW) / 2;
                        offY = 0;
                    } else {
                        visW = video.videoWidth;
                        visH = visW / containerAspect;
                        offX = 0;
                        offY = (video.videoHeight - visH) / 2;
                    }
                    var guideAspect = 1.586;
                    var guideW = visW * 0.88;
                    var guideH = guideW / guideAspect;
                    if (guideH > visH) { guideH = visH; guideW = guideH * guideAspect; }
                    var guideX = offX + (visW - guideW) / 2;
                    var guideY = offY + (visH - guideH) / 2;
                    return { x: guideX, y: guideY, w: guideW, h: guideH };
                }

                if (photoIdRequired) {
                    function performCapture() {
                        var video = document.getElementById("camera-stream");
                        if (currentSide === "back" && !isPassport && !window.__idwBarcodeRaw && !window.__idwForceCapture) {
                            var __waited = Date.now() - (window.__idwBackStartedAt || 0);
                            if (__waited < 10000) {
                                var __st = document.getElementById("idw-capture-status");
                                if (__st) { __st.textContent = "Hold steady \u2014 reading the barcode\u2026"; }
                                if (!window.__idwBlockedLogged) { window.__idwBlockedLogged = true; idwDiag("early_capture_blocked", { ms: __waited }); }
                                if (!window.__idwFallbackTimer) {
                                    window.__idwFallbackTimer = setTimeout(function () {
                                        window.__idwFallbackTimer = null;
                                        if (!stream || currentSide !== "back" || window.__idwBarcodeRaw) { return; }
                                        idwDiag("fallback_capture");
                                        window.__idwForceCapture = true;
                                        try { performCapture(); } finally { window.__idwForceCapture = false; }
                                    }, 10000 - __waited);
                                }
                                return;
                            }
                        }
                        var crop = __idwGetGuideCropRect(video);
                        var canvas = document.createElement("canvas");
                        canvas.width = crop.w;
                        canvas.height = crop.h;
                        canvas.getContext("2d").drawImage(video, crop.x, crop.y, crop.w, crop.h, 0, 0, crop.w, crop.h);
                        var dataUrl = canvas.toDataURL("image/jpeg", 0.92);
                        var side = currentSide;
                        stopCamera();
                        if (side === "front") {
                            var img = document.getElementById("front-preview");
                            img.src = dataUrl;
                            document.getElementById("front-preview-block").classList.remove("hidden");
                            img.onload = function() {
                                var ok = checkBlur(img, document.getElementById("front-blur-warning"));
                                if (ok) {
                                    document.getElementById("photo-id-data").value = dataUrl;
                                    idwSaveState({ photo_id: dataUrl });
                                    if (!isPassport && idwBackRequired) {
                                        document.getElementById("upload-zone-trigger-back").classList.remove("hidden");
                                        document.getElementById("upload-zone-trigger-back-label").classList.remove("hidden");
                                    }
                                }
                            };
                        } else {
                            var img = document.getElementById("back-preview");
                            img.src = dataUrl;
                            document.getElementById("back-preview-block").classList.remove("hidden");
                            img.onload = function() {
                                var ok = checkBlur(img, document.getElementById("back-blur-warning"));
                                if (ok) { document.getElementById("photo-id-back-data").value = dataUrl; idwSaveState({ photo_id_back: dataUrl }); idwBackScan(dataUrl).then(function(scanned) { idwDiag("photo_scan", { ok: !!scanned, fails: window.__idwBackFails || 0 }); if (scanned) idwAutoNext(); }); }
                            };
                        }
                    }

                    document.getElementById("retake-front-btn").addEventListener("click", function() {
                        document.getElementById("front-preview-block").classList.add("hidden");
                        document.getElementById("upload-zone-trigger-back").classList.add("hidden");
                        document.getElementById("upload-zone-trigger-back-label").classList.add("hidden");
                        document.getElementById("photo-id-data").value = "";
                        startCamera("front");
                    });

                    document.getElementById("retake-back-btn").addEventListener("click", function() {
                        window.__idwBarcodeRaw = ""; window.__idwBarcodeTried = false;
                        document.getElementById("back-preview-block").classList.add("hidden");
                        document.getElementById("photo-id-back-data").value = "";
                        startCamera("back");
                    });
                }

                // ── Back-of-ID barcode scanning (PDF417 / AAMVA) ──
                window.__idwBarcodeRaw = "";
                window.__idwBarcodeTried = false;
                window.__idwBackFails = 0;
                var __idwPoll = null, __idwBusy = false;

                function idwStopPoll() { if (__idwPoll) { clearInterval(__idwPoll); __idwPoll = null; } }

                function idwStartPoll() {
                    idwStopPoll();
                    __idwPoll = setInterval(function() {
                        var video = document.getElementById("camera-stream");
                        if (!video || !video.srcObject) { return; }
                        if (currentSide !== "back" || video.paused || video.readyState < 2 || __idwBusy || !window.idwBarcode) { return; }
                        __idwBusy = true;
                        if (!window.__idwVidLogged) { window.__idwVidLogged = true; idwDiag("video", { w: video.videoWidth, h: video.videoHeight }); }
                        window.idwBarcode.decodeVideo(video).then(function(raw) {
                            if (raw && video.srcObject && currentSide === "back") {
                                window.__idwBarcodeRaw = raw;
                                idwStopPoll();
                                idwDiag("live_hit", { ms: Date.now() - (window.__idwBackStartedAt || Date.now()) }); if (typeof performCapture === "function") performCapture();
                            }
                        }).catch(function(e) { if (!window.__idwLiveErrLogged) { window.__idwLiveErrLogged = true; idwDiag("live_decode_error", { msg: String((e && e.message) || e).slice(0, 120) }); } }).then(function() { __idwBusy = false; });
                    }, 600);
                }

                function idwAutoNext() {
                    if (!window.__idwBarcodeRaw) { return; }
                    var nb = document.getElementById("id-capture-next-btn");
                    if (!nb) { return; }
                    setTimeout(function() {
                        if (window.__idwBarcodeRaw && !nb.disabled) { nb.click(); }
                    }, 900);
                }

                window.__idwDiagUrl = CFG.diagUrl || "";

                function idwDiag(ev, extra) {
                    try {
                        var d = window.__idwDiag = window.__idwDiag || { t0: Date.now(), ev: [] };
                        if (d.ev.length >= 40) { return; }
                        var o = { t: Date.now() - d.t0, e: ev };
                        if (extra) { for (var k in extra) { o[k] = extra[k]; } }
                        d.ev.push(o);
                    } catch (e) {}
                }

                function idwDiagJson() {
                    try {
                        var d = window.__idwDiag || { ev: [] };
                        var j = JSON.stringify(d);
                        if (j.length > 5500) { j = JSON.stringify({ t0: d.t0, ev: d.ev.slice(-25) }); }
                        return j.length > 8000 ? "" : j;
                    } catch (e) { return ""; }
                }

                function idwReport(reason) {
                    try {
                        var d = window.__idwDiag;
                        if (!d || !d.ev.length || !window.__idwDiagUrl) { return; }
                        if (d.ev.some(function (x) { return x.e === "submit"; })) { return; }
                        window.__idwReported = window.__idwReported || {};
                        var key = reason + ":" + d.ev.length;
                        if (window.__idwReported[key]) { return; }
                        window.__idwReported[key] = true;
                        var fd = new FormData();
                        var meta = document.querySelector('meta[name="csrf-token"]');
                        fd.append("_token", meta ? meta.content : "");
                        fd.append("reason", reason);
                        fd.append("diag", idwDiagJson());
                        if (navigator.sendBeacon) { navigator.sendBeacon(window.__idwDiagUrl, fd); }
                        else { fetch(window.__idwDiagUrl, { method: "POST", body: fd, keepalive: true }); }
                    } catch (e) {}
                }
                document.addEventListener("visibilitychange", function () {
                    if (document.visibilityState === "hidden") { idwReport("left_page"); }
                });

                function idwHideTips() { var el = document.getElementById("idw-tips-modal"); if (el) el.remove(); }

                function idwShowTips(mode) {
                    idwHideTips();
                    var manual = mode === "manual"; idwDiag("tips", { mode: mode, fails: window.__idwBackFails || 0 }); if (manual) { idwReport("manual_review_popup"); }
                    var el = document.createElement("div");
                    el.id = "idw-tips-modal";
                    el.style.cssText = "position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px;";
                    el.innerHTML = `<div style="background:#fff;color:#111;border-radius:16px;max-width:420px;width:100%;padding:20px;">
                        <div style="font-size:18px;font-weight:700;margin-bottom:8px;">${manual ? "We couldn't read the barcode" : "Let's try that again"}</div>
                        <p style="margin:0 0 10px;font-size:14px;">${manual ? "No problem. You can continue and our team will review your ID manually." : "We couldn't read the barcode on the back of your ID. A few tips:"}</p>
                        <ul style="margin:0 0 14px 18px;padding:0;font-size:14px;list-style:disc;line-height:1.5;">
                            <li>Place the ID on a flat, dark surface</li>
                            <li>Avoid overhead light and glare (tilt slightly if you see a reflection)</li>
                            <li>Rest your elbows on the table to keep your hands steady</li>
                            <li>Fill the frame with the barcode and wait for it to focus</li>
                        </ul>
                        <div style="display:flex;gap:8px;">
                            <button type="button" id="idw-tips-retake" style="flex:1;padding:12px;border-radius:10px;background:#111;color:#fff;font-weight:600;">Retake photo</button>
                            ${manual ? '<button type="button" id="idw-tips-close" style="flex:1;padding:12px;border-radius:10px;background:#e5e7eb;font-weight:600;">Continue</button>' : ""}
                        </div>
                    </div>`;
                    document.body.appendChild(el);
                    document.getElementById("idw-tips-retake").onclick = function() {
                        idwHideTips();
                        var b = document.getElementById("retake-back-btn");
                        if (b) b.click();
                    };
                    var c = document.getElementById("idw-tips-close");
                    if (c) c.onclick = idwHideTips;
                }

                function idwBackScan(dataUrl) {
                    idwStopPoll();
                    window.__idwBarcodeTried = true;
                    if (window.__idwBarcodeRaw) { idwHideTips(); return Promise.resolve(true); }
                    if (!window.idwBarcode) { return Promise.resolve(false); }
                    return window.idwBarcode.decodeDataUrl(dataUrl).then(function(raw) {
                        if (raw) { window.__idwBarcodeRaw = raw; idwHideTips(); return true; }
                        window.__idwBackFails = (window.__idwBackFails || 0) + 1; idwDiag("photo_decode_null", { fails: window.__idwBackFails });
                        idwShowTips(window.__idwBackFails >= 3 ? "manual" : "retry");
                        return false;
                    }).catch(function(e) {
                        console.error("Barcode decode error", e); idwDiag("photo_decode_error", { msg: String((e && e.message) || e).slice(0, 120) });
                        window.__idwBackFails = 3;
                        idwShowTips("manual");
                        return false;
                    });
                }

                try {
                    var __idwOrigStart = startCamera;
                    startCamera = function(side) {
                        var r = __idwOrigStart.apply(this, arguments);
                        if (side === "back" && !isPassport) {
                            window.__idwBarcodeRaw = ""; window.__idwBarcodeTried = false;
                            window.__idwBackStartedAt = Date.now(); window.__idwVidLogged = false; window.__idwBlockedLogged = false; if (window.__idwFallbackTimer) { clearTimeout(window.__idwFallbackTimer); window.__idwFallbackTimer = null; } idwDiag("back_cam", { scanner: !!window.idwBarcode, scr: screen.width + "x" + screen.height }); if (window.idwBarcode) window.idwBarcode.warm();
                            idwStartPoll();
                        }
                        return r;
                    };
                } catch (e) { console.warn("Live barcode scan not attached; capture-time decode still works.", e); }

                function resetIdCapture() {
                    var frontData = document.getElementById("photo-id-data");
                    var backData = document.getElementById("photo-id-back-data");
                    if (frontData) frontData.value = "";
                    if (backData) backData.value = "";
                    var frontBlock = document.getElementById("front-preview-block");
                    var backBlock = document.getElementById("back-preview-block");
                    if (frontBlock) frontBlock.classList.add("hidden");
                    if (backBlock) backBlock.classList.add("hidden");
                    var frontTrigger = document.getElementById("upload-zone-trigger");
                    var frontLabel = document.getElementById("upload-zone-trigger-front-label");
                    if (frontTrigger) frontTrigger.classList.remove("hidden");
                    if (frontLabel) frontLabel.classList.remove("hidden");
                }

                function withButtonBusy(btn, busyLabel, fn) {
                    var origHtml = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="ui-spinner"></span><span>' + busyLabel + '</span>';
                    function restore() {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                    }
                    fn(restore);
                }

    document.getElementById("id-capture-next-btn").addEventListener("click", function () {
        var btn = this;
        var front = document.getElementById("photo-id-data").value;
        var back = document.getElementById("photo-id-back-data").value;
        if (!front) { alert("Please take a photo of the front of your ID."); return; }
        if (!isPassport && !back) { alert("Please take a photo of the back of your ID."); return; }
        if (!document.getElementById("front-blur-warning").classList.contains("hidden")) { alert("Front ID photo is blurry. Please retake."); return; }
        if (!isPassport && !document.getElementById("back-blur-warning").classList.contains("hidden")) { alert("Back ID photo is blurry. Please retake."); return; }
        if (!isPassport && window.idwBarcode && !window.__idwBarcodeRaw) {
            if (back && !window.__idwBarcodeTried) {
                btn.disabled = true;
                idwBackScan(back).then(function (ok) { btn.disabled = false; if (ok) btn.click(); });
                return;
            }
            if ((window.__idwBackFails || 0) < 3) { idwShowTips("retry"); return; }
        }
        function b64toBlob(b64) {
            var arr = b64.split(","), mime = arr[0].match(/:(.*?);/)[1];
            var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
            for (var i = 0; i < n; i++) u8[i] = bstr.charCodeAt(i);
            return new Blob([u8], { type: mime });
        }
        var fd = new FormData();
        var meta = document.querySelector('meta[name="csrf-token"]');
        fd.append("_token", meta ? meta.content : (CFG.csrf || ""));
        fd.append("id_type", isPassport ? "passport" : "state_id");
        fd.append("website", "");
        fd.set("photo_id", b64toBlob(front), "front.jpg");
        if (!isPassport) {
            fd.set("id_barcode_raw", window.__idwBarcodeRaw || "");
            fd.set("photo_id_back", b64toBlob(back), "back.jpg");
        }
        withButtonBusy(btn, "Uploading…", function (restore) {
            fetch(CFG.uploadUrl, { method: "POST", body: fd, headers: { "Accept": "application/json" } })
                .then(function (r) {
                    if (r.status === 422) {
                        return r.json().then(function (b) {
                            restore();
                            alert(b.errors ? Object.values(b.errors).flat().join("\n") : "Please check the photos and try again.");
                        });
                    }
                    if (!r.ok) { restore(); alert("Upload failed. Please try again."); return; }
                    return r.json().then(function (b) {
                        if (b.status === "retry") { restore(); alert(b.message || "We could not read your ID. Please retake the photos."); }
                        if (b.redirect) { window.location.href = b.redirect; } else { restore(); }
                    }, function () { restore(); alert("Something went wrong. Please refresh and try again."); });
                })
                .catch(function () { restore(); alert("Network error. Please try again."); });
        });
    });
    if (typeof startCamera === "function") window.startCamera = startCamera;
})();
