# Guesthub

A Laravel 12 / PHP 8.2 / MySQL application that runs two connected systems for
short-term rental, serviced residence, and hotel-style properties out of one
codebase and one database:

1. **Guest portal & booking operations** — a public, token-protected guest
   flow (ID verification, digital rental agreement, deposit/incidentals
   payment, smart-lock check-in, checkout) plus an admin side for managing
   bookings, properties, and PMS sync.
2. **Cleaning operations** — an internal housekeeping system: properties →
   rooms → tasks, cleaning sessions with a photo checklist, staff training,
   and reporting.

These were originally two separate applications and have been merged into a
single app: one login, one role system, one navigation, one settings page,
one activity log, and one properties table serving both sides.

## What it actually does

This section is based on reading the code, not the feature list from before
the merge — a few real, substantial pieces of functionality were undocumented
previously.

### Guest-facing booking flow
- Public guest URL, gated by booking ID + secure token:
  `/guest/{booking_id}/{token}`.
- Date-aware flow: pre-arrival ID upload → check-in day → in-stay welcome
  guide → checkout day, each shown/hidden based on the booking's dates.
- Government-ID scan (name / DOB / expiry) via **Google Cloud Vision OCR**.
- Optional **background-check step**, gating deposit verification.
- **Digital rental agreement**: contract text filled in from booking/property
  variables, captured signature (name, device ID, timestamp, agreement
  version), rendered on-screen or as a PDF (`dompdf`).
- **Payments via Stripe**: deposit and incidentals charges (parking, early
  check-in, late checkout) using PaymentIntents; guests can alternatively pay
  incidentals on the booking platform itself.
- **Smart-lock check-in via Seam**: lock/unlock actions from the guest portal,
  with live lock status kept in sync through Seam webhooks (`PropertyLock`
  model, `SeamWebhookController`).
- GPS verification on check-in day, with a manual-approval fallback and admin
  override.
- SMS (Telnyx) and email notifications; a check-in reminder and a
  "checkout available tomorrow" reminder, both sent once per booking on a
  schedule.
- Auto-checkout: bookings are auto-completed 30 minutes after checkout time if
  the guest never confirms, and archived automatically afterward.

### Booking / PMS side (admin)
- **PMS sync**: a provider-agnostic booking importer (`Channex` implemented,
  `NextPax` scaffolded — `PmsProviderInterface` / `config/pms.php`) that polls
  the active PMS for new/changed bookings and imports them; availability is
  pushed back two-way. Rates are never pushed by Guesthub. Backed by both a
  scheduled poll (`pms:sync`) and a webhook for faster updates.
- Guest, property, category, content, and amenity management.
- Photo ID storage, private and only downloadable through authenticated admin
  routes.

### Cleaning operations (internal, authenticated)
- Property → room → task hierarchy, with per-property task assignment and
  ordering.
- Cleaning sessions: stage-based checklist, photo evidence per item,
  GPS-aware start/completion with admin override.
- Assignment grouping and a calendar for scheduling housekeepers.
- Staff training: instructional videos (background-processed/optimized via
  `ffmpeg`), completion tracking, and per-task instruction-familiarity checks
  that can gate a task until the housekeeper has reviewed it.
- Scheduled cleanup: old session photos pruned automatically; training
  reminder emails sent hourly.

### Shared across both sides
- Roles via `spatie/laravel-permission`: `admin`, `company`, `owner`,
  `manager`, `staff`, `housekeeper`, `viewer` — one role system, one login,
  gates both the guest-booking admin and cleaning ops.
- One Settings page (branding, legal, notifications for both sides).
- One Properties system — a property carries both guest-facing content
  (guide, instructions, locks, availability) and cleaning setup (rooms,
  tasks) on the same record.
- One activity log combining events from both sides (`spatie/laravel-activitylog`
  plus a custom activity table, unified at read time).
- Light/dark theme with a configurable brand color applied across both sides.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=welcome_guide
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

Then:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Run the scheduler locally with `php artisan schedule:work` (or set up cron in
production — see Deployment below) so reminders, auto-checkout, PMS sync,
photo pruning, and training reminders actually fire.

### Environment variables

`.env.example` documents the core app/mail/DB settings. These integrations
are wired into the code but **not yet listed in `.env.example`** — add them
manually until that's fixed, or the corresponding feature silently no-ops:

| Feature | Variables |
|---|---|
| ID-scan OCR | `GOOGLE_VISION_API_KEY`, `GOOGLE_VISION_DEBUG_LOG` (in `.env.example`) |
| Property geocoding | `GOOGLE_GEOCODING_API_KEY` (in `.env.example`) |
| SMS (Telnyx) | `TELNYX_API_KEY`, `TELNYX_PUBLIC_KEY`, `TELNYX_FROM_NUMBER`, `TELNYX_MESSAGING_PROFILE_ID`, `TELNYX_ADMIN_NOTIFY_NUMBER` (in `.env.example`) |
| Payments | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` (**missing from `.env.example`**) |
| Smart locks | `SEAM_API_KEY`, `SEAM_WEBHOOK_SECRET` (**missing from `.env.example`**) |
| PMS sync | `PMS_PROVIDER` (`channex`/`nextpax`, default `channex`), `PMS_POLL_INTERVAL_MINUTES` (default `15`) — plus whatever the Channex provider itself needs (read `app/Services/Pms/ChannexProvider.php`); **none of this is in `.env.example`** |
| S3 storage | `AWS_*` (in `.env.example`, only needed if `FILESYSTEM_DISK` is switched to `s3`) |

`ffmpeg` must be installed and on `PATH` (used directly via PHP's `Process`,
not a Composer package) for instructional-video optimization.

## Demo Login

- URL: `/login`
- Email: `admin@example.com`
- Password: `password`
- Seeded by `WelcomeGuideSeeder`, role `admin`. Seeded guest URL:
  `/guest/LUMINA-DEMO/lumina-demo-secure-token`.

## Webhooks

Four inbound webhook endpoints, all under `/webhooks/*`, unauthenticated by
route middleware (each controller verifies its own provider's signature):
`/webhooks/seam`, `/webhooks/channex`, `/webhooks/stripe`,
`/webhooks/telnyx/sms`. Point the corresponding provider dashboard at
`{APP_URL}/webhooks/{provider}` and configure the matching `*_WEBHOOK_SECRET`.

## Scheduled Commands

Registered in `routes/console.php` — needs `php artisan schedule:work`
locally or a cron entry in production:

| Command | Cadence | Purpose |
|---|---|---|
| `bookings:auto-checkout` | every 5 min | Auto-checkout 30 min after checkout time if unconfirmed |
| `bookings:archive-overdue` | every 5 min | Archive bookings past checkout |
| `bookings:send-checkin-reminders` | daily 08:00 | "Time to check in" alert |
| `bookings:send-checkout-reminders` | daily 18:00 | "Checkout available tomorrow" alert |
| `pms:sync` | per `PMS_POLL_INTERVAL_MINUTES` | Poll active PMS for new/changed bookings |
| `photos:prune-old --days=14` | daily 02:00 | Delete old cleaning-session photos |
| `training:send-reminders` | hourly | Training reminder emails |

## Client Preview Screenshots & PDF

```bash
npm run build
npm run preview:screenshots
npm run preview:pdf
```

Runs `php artisan migrate --seed --force`, starts Laravel locally if needed,
logs in as the demo admin, and captures screens. Set `CHROME_PATH` if Chrome
isn't found automatically. Outputs to `public/client-preview/`.

## Deployment Notes (AlmaLinux / GoDaddy VPS, cPanel)

- PHP 8.2+ with: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL,
  PDO, Tokenizer, XML.
- Web server document root → the Laravel `public` directory.
- `storage` and `bootstrap/cache` writable by the web server.
- `composer install --no-dev --optimize-autoloader`, `npm install && npm run build`.
- Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct
  `APP_URL`, MySQL credentials, plus every variable in the table above that
  the deployment actually uses (Stripe/Seam/PMS keys are live-money and
  live-hardware integrations — do not deploy without them configured and
  each webhook secret verified against the provider dashboard).
- `php artisan migrate --force`, `php artisan storage:link`,
  `php artisan config:cache`, `php artisan route:cache`,
  `php artisan view:cache`.
- Cron: `* * * * * php artisan schedule:run` — required for PMS sync,
  auto-checkout, reminders, and training emails to fire at all.
- HTTPS required — browser geolocation (guest check-in GPS, cleaning-session
  GPS) needs a secure context.
- `ffmpeg` installed on the server — not yet confirmed on the target
  production server as of this writing.

## Data Migration (deployment stage, not yet run)

Five data-fixup migrations from the pre-merge cleaning app are held in
`deferred-migrations/` at the repo root, outside the normal
`database/migrations/` set. They are written to run once, against the
client's real imported data, during the deployment-stage database import —
not against demo/seed data:

1. `convert_sporadic_tasks_to_compound_keys.php` — run first; the current
   session code already expects this format.
2. `clone_shared_tasks_per_room.php`
3. `isolate_shared_rooms.php`
4. `deduplicate_default_rooms_and_tasks.php`
5. `convert_property_timezone_to_iana.php` — defaults any property without a
   valid timezone to `America/New_York`, matching the app's default
   everywhere else.

See `notes.md` and the merge task files (`TASKS_A.md`–`TASKS_D.md`) for the
full decision record on each.

## Project Status

The code/feature merge is complete or in final cleanup — see `TASKS_D.md` for
what's still open before this is considered fully tested. Two things remain
outside the code merge, gated on the client rather than on this repo:

- **Data import** — the client's real live data hasn't been imported yet;
  everything running today is demo/seed data.
- **Deployment** — not yet pushed to the production server; the integrations
  above (Stripe, Seam, PMS, webhooks) need real credentials and verified
  webhook endpoints before go-live, not just an `.env` copy.

`MERGE_PLAN.md` and `TASKS_A.md`–`TASKS_D.md` track the full merge history.
`notes.md` has the running log of what's actually been done, and is more
current than any status label inside the task files.

## Testing Checklist

**Guest / booking flow**
- Invalid booking/token combinations are rejected.
- ID upload + OCR parsing works; background-check step (if enabled) gates
  deposit verification correctly.
- Rental agreement renders and captures a signature; PDF export works.
- Stripe deposit and incidentals (parking/early check-in/late checkout)
  charges complete, and the Stripe webhook updates charge status.
- Seam lock/unlock works from the guest portal and lock status stays in sync
  via the Seam webhook.
- GPS check-in verification succeeds within radius; manual-approval path
  works when it doesn't; admin override works.
- Check-in and checkout reminders fire on schedule; auto-checkout and
  archive-overdue run correctly.
- PMS sync pulls new/changed bookings from Channex without duplicating them;
  availability pushes back correctly.

**Cleaning operations**
- Login works for all seven roles, landing on the correct dashboard/panels.
- A cleaning session can be started, checklist completed with photos, and
  finished, with GPS and admin override both working.
- Session-started/completed notifications (email + SMS) send.
- Video upload/processing and training-completion tracking work; instruction
  familiarity gates the tasks it's supposed to.
- Old session photos are pruned on schedule; training reminders send hourly.

**Shared**
- Light/dark theme renders correctly everywhere, for every role.
- Settings changes (branding, legal, notifications) persist and are reflected
  everywhere they're used.
- All four webhook endpoints reject requests with an invalid signature.
