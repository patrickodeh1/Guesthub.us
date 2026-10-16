# cPanel: video upload limits and conversion

The live site runs on cPanel, not Docker. The Dockerfile and docker-compose.yml are local-only
and have no effect there. On cPanel, PHP limits come from the domain's PHP settings.

## Problem
Uploads over 14 MB fail with "The file you uploaded is too large. Please upload a file smaller than 14MB."
The limit is PHP's post_max_size, which was 14M (upload_max_filesize 12M).

## Fix on cPanel
1. Deploy `public/.user.ini` (and `public/.htaccess`) with these values:
   - upload_max_filesize = 200M
   - post_max_size = 210M
   - memory_limit = 512M
   - max_execution_time = 900
   - display_errors = Off
   - log_errors = On
2. If cPanel > MultiPHP INI Editor has values set for the domain, set the same values there.
   They can override the file.
3. Wait up to 5 minutes for .user.ini to apply, then retest with a clip over 14 MB.

## Verify what the web server really uses
`php -i` in Terminal reads a different config than the site. Use a temporary page in the
document root that prints ini_get() for the values above, then delete it.

## Conversion needs (iPhone .mov / HEVC -> web MP4)
Conversion runs on the server with ffmpeg after the upload finishes. Confirm on the host:
- `ffmpeg` and `ffprobe` exist (`command -v ffmpeg`). If not, ask the host to install them or
  place a static binary in the home folder and point the job at it.
- `proc_open` is not in disable_functions, or Laravel cannot start ffmpeg.
- `fastcgi_finish_request` or `litespeed_finish_request` exists, or the "after response"
  progress bar will not work. Fallback: cron running
  `php artisan queue:work --stop-when-empty` every minute.

## Other limits in front of PHP
- Cloudflare free plan caps request bodies at 100 MB.
- Any nginx or proxy layer needs `client_max_body_size 210M;`.

## Notes
- `app/Http/Middleware/CheckPostSize.php` reads post_max_size at runtime, so its message
  updates automatically.
- Images are shrunk in the browser; video cannot be, which is why the higher limit is needed.
