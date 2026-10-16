# Deploy: server-side ID barcode decoder

What it does: when a guest submits their ID, Laravel decodes the PDF417 barcode on the saved back photo
(`scripts/barcode_decode.py`, run by `App\Services\BarcodeDecoder`). If the server decoder can't run,
the app falls back to the barcode text the browser posted, exactly as before.

## Files that change

| File | Change |
|---|---|
| `scripts/barcode_decode.py` | new |
| `app/Services/BarcodeDecoder.php` | new |
| `config/services.php` | new `barcode_decoder` block |
| `app/Http/Controllers/GuestController.php` | `submitIdentity()` calls the decoder |
| `Dockerfile` | local/Docker only: installs Python + zxing-cpp |

## Local (Docker)

1. `bash apply_decoder_patches.sh` (from the project root, patches controller + Dockerfile)
2. `docker compose build app && docker compose up -d`
3. In `.env`: `BARCODE_PYTHON=/opt/decoder/bin/python`
4. `docker compose exec app php artisan config:clear`
5. `docker compose exec app php -l app/Http/Controllers/GuestController.php`

## Production (cPanel, no Docker)

1. **Check PHP may run processes.** cPanel > MultiPHP INI Editor > `disable_functions`.
   `proc_open` must NOT be listed.
2. **Install the decoder** in Terminal:
   ```bash
   python3 --version
   python3 -m venv ~/decoder-venv
   ~/decoder-venv/bin/pip install zxing-cpp pillow
   ~/decoder-venv/bin/python -c "import zxingcpp, PIL; print('zxing ok')"
   ```
   If pip finds no matching version, note the Python version and get help before continuing.
3. **Deploy the four code files** above the way you normally deploy (git pull or upload).
4. **Set `.env`** on the server: `BARCODE_PYTHON=/home/dzm/decoder-venv/bin/python`
5. **Clear config:** `cd /home/dzm/guesthub && php artisan config:clear`
6. **Test with a photo already in storage** (no new ID copies needed). Prints only the result, never ID data:
   ```bash
   php artisan tinker --execute="echo app(App\Services\BarcodeDecoder::class)->decodeStored('photo-ids/SOME_EXISTING_BACK_PHOTO.jpg')['reason'];"
   ```
   Expect `ok` (or `no_barcode` if you picked a front photo).
7. **Smoke test** one check-in with a test ID. In admin the scan source should read
   "Barcode (back of ID)", and the `id_scan_result` log should show `server_decode: ok`.

## If something goes wrong

- Kill switch: empty `BARCODE_PYTHON` in `.env` + `php artisan config:clear`. The decoder reports
  `not_configured` and the app uses the browser's barcode text again.
- Reasons you may see in the log: `unavailable` (proc_open blocked or wrong Python path),
  `decoder_error` (see `storage/logs/laravel.log`, search "Barcode decoder"),
  `unreadable` / `no_barcode` (photo problem, guest goes to manual review).
- Full rollback: restore `GuestController.php` from git. The other files are inert without it.

## Notes

- A submit now waits for the decode: about 1 second normally, up to about 6 seconds before it gives up.
- Admin approval is unchanged. Admins still approve every ID manually.
- The old site doesn't need any of this; only the new site decodes.
