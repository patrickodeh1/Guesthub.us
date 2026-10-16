#!/usr/bin/env python3
"""
Server-side PDF417 (AAMVA) decoder for the back of a US/Canadian ID.

Usage:   barcode_decode.py /path/to/back.jpg [seconds_limit]
Output:  exactly one line of JSON on stdout, nothing else. Never logs the barcode text.

  {"ok": true,  "text": "<raw barcode text>", "recipe": "x1.5 sharpen GlobalHistogram"}
  {"ok": false, "reason": "no_barcode" | "unreadable" | "timeout" | "bad_image" | "too_large"}

"unreadable" means a barcode was located but error correction failed.
Install:  pip install zxing-cpp pillow
"""
import json
import sys
import time

import zxingcpp
from PIL import Image, ImageFilter, ImageOps

Image.MAX_IMAGE_PIXELS = 60_000_000
LIMIT = float(sys.argv[2]) if len(sys.argv) > 2 else 6.0
START = time.time()
AAMVA_MARKERS = ("ANSI", "DAQ", "DCS", "DBB")

BINARIZERS = {
    "GlobalHistogram": zxingcpp.Binarizer.GlobalHistogram,
    "LocalAverage": zxingcpp.Binarizer.LocalAverage,
    "FixedThreshold": zxingcpp.Binarizer.FixedThreshold,
}


def out(obj):
    sys.stdout.write(json.dumps(obj) + "\n")
    sys.exit(0)


def timed_out():
    return time.time() - START > LIMIT


def sharpen(img):
    img = ImageOps.autocontrast(img, cutoff=2)
    return img.filter(ImageFilter.UnsharpMask(radius=2, percent=180, threshold=2))


def plain(img):
    return img


def decode(img, binarizer):
    """Returns (text or None, saw_checksum_error)."""
    saw_error = False
    results = zxingcpp.read_barcodes(
        img,
        formats=zxingcpp.BarcodeFormat.PDF417,
        try_rotate=True,
        try_downscale=True,
        try_invert=True,
        binarizer=BINARIZERS[binarizer],
        text_mode=zxingcpp.TextMode.Plain,
        return_errors=True,
    )
    for r in results:
        text = r.text or ""
        if getattr(r, "valid", True) and any(m in text for m in AAMVA_MARKERS):
            return text, saw_error
        saw_error = True
    return None, saw_error


def passes(base):
    """Yield (label, image, binarizer). Order = verified-best first. Images are built lazily."""
    w, h = base.size
    # full image: the recipe that read the real failed photo comes first
    plan = [
        (1.5, sharpen, "sharpen", "GlobalHistogram"),
        (1.5, sharpen, "sharpen", "LocalAverage"),
        (1.0, sharpen, "sharpen", "GlobalHistogram"),
        (1.0, sharpen, "sharpen", "LocalAverage"),
        (2.0, sharpen, "sharpen", "GlobalHistogram"),
        (0.75, sharpen, "sharpen", "GlobalHistogram"),
        (0.5, sharpen, "sharpen", "GlobalHistogram"),
        (1.0, plain, "plain", "GlobalHistogram"),
        (1.0, plain, "plain", "LocalAverage"),
        (1.5, plain, "plain", "GlobalHistogram"),
        (1.0, sharpen, "sharpen", "FixedThreshold"),
    ]
    cache = {}
    for scale, prep, pname, binarizer in plan:
        nw = int(w * scale)
        if nw < 500 or nw > 4200:
            continue
        key = (scale, pname)
        if key not in cache:
            im = base if scale == 1.0 else base.resize((nw, int(h * scale)), Image.LANCZOS)
            cache[key] = prep(im)
        yield f"x{scale} {pname} {binarizer}", cache[key], binarizer

    # horizontal bands: helps when text blocks confuse the barcode locator
    for frac in (0.4, 0.25):
        bh = int(h * frac)
        step = max(1, bh // 2)
        top = 0
        while top < h:
            bottom = min(h, top + bh)
            band = base.crop((0, top, w, bottom))
            for scale in (1.5, 1.0):
                nw = int(w * scale)
                if nw > 4200:
                    continue
                im = band if scale == 1.0 else band.resize((nw, int((bottom - top) * scale)), Image.LANCZOS)
                yield f"band x{scale} sharpen GlobalHistogram", sharpen(im), "GlobalHistogram"
            if bottom >= h:
                break
            top += step


def main():
    if len(sys.argv) < 2:
        out({"ok": False, "reason": "bad_image"})
    try:
        im = Image.open(sys.argv[1])
        im = ImageOps.exif_transpose(im).convert("L")
    except Image.DecompressionBombError:
        out({"ok": False, "reason": "too_large"})
    except Exception:
        out({"ok": False, "reason": "bad_image"})

    # camera-roll photos can be huge; cap the working size
    if im.size[0] > 3000:
        r = 3000 / im.size[0]
        im = im.resize((3000, int(im.size[1] * r)), Image.LANCZOS)

    saw_error = False
    for label, img, binarizer in passes(im):
        if timed_out():
            out({"ok": False, "reason": "timeout"})
        try:
            text, err = decode(img, binarizer)
        except Exception:
            continue
        saw_error = saw_error or err
        if text:
            out({"ok": True, "text": text, "recipe": label})
    out({"ok": False, "reason": "unreadable" if saw_error else "no_barcode"})


if __name__ == "__main__":
    main()
