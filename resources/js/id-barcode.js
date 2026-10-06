// Decodes the PDF417 barcode on the back of US/Canadian driver licenses (AAMVA).
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

let readerPromise = null;

function loadReader() {
    if (!readerPromise) {
        readerPromise = import('zxing-wasm/reader').then((m) => {
            const overrides = {
                locateFile: (path, prefix) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
            };
            if (typeof m.setZXingModuleOverrides === 'function') {
                m.setZXingModuleOverrides(overrides);
            } else if (typeof m.prepareZXingModule === 'function') {
                m.prepareZXingModule({ overrides, fireImmediately: false });
            }
            return m;
        });
    }
    return readerPromise;
}

const looksAamva = (t) => typeof t === 'string' && /ANSI|DAQ|DCS|DBB/.test(t);

async function decodeCanvas(canvas) {
    const m = await loadReader();
    const img = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
    const results = await m.readBarcodes(img, {
        formats: ['PDF417'],
        tryHarder: true,
        tryRotate: true,
        tryInvert: true,
        tryDownscale: true,
        maxNumberOfSymbols: 1,
    });
    for (const r of results) {
        if (r.isValid !== false && looksAamva(r.text)) return r.text;
    }
    return null;
}

function drawToCanvas(source, w, h, maxW) {
    const scale = w > maxW ? maxW / w : 1;
    const c = document.createElement('canvas');
    c.width = Math.round(w * scale);
    c.height = Math.round(h * scale);
    c.getContext('2d', { willReadFrequently: true }).drawImage(source, 0, 0, c.width, c.height);
    return c;
}

async function decodeVideo(video) {
    if (!video.videoWidth) return null;
    return decodeCanvas(drawToCanvas(video, video.videoWidth, video.videoHeight, 1920));
}

function decodeDataUrl(dataUrl) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            decodeCanvas(drawToCanvas(img, img.naturalWidth, img.naturalHeight, 2200)).then(resolve, reject);
        };
        img.onerror = reject;
        img.src = dataUrl;
    });
}

window.idwBarcode = { decodeVideo, decodeDataUrl, warm: () => loadReader().catch(() => {}) };
