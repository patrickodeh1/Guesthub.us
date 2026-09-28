# Task Queue — Part C (unification layer)

Read `AGENTS.md`, `MERGE_PLAN.md`, `TASKS_A.md`, and `TASKS_B.md` first. Log
details in `notes.md`, same convention as before — this file stays short.

## Why this file exists

TASKS_B.md's tasks (B001–B010) were about making the merge *not crash*: copying
models/controllers/routes/views/assets, resolving naming collisions, getting
migrations to run, getting pages to render without errors. That work is
verified complete below — I cloned the repo and checked directly rather than
trusting the `status` labels in TASKS_B.md, since several tasks were finished
without their status line being updated.

**What's NOT done, and what feels like "two separate sites," is a different
problem: nothing has unified the parts that are shared *conceptually* but were
built twice.** Two settings pages, two dashboards, two navigation shells, two
sets of branding keys, two user-management screens — all fully working, none
aware the other exists. That's what this file fixes. Do not re-do anything
from TASKS_B.md; where a TASKS_C task depends on a specific B-task's output,
it says so.

---

## Verification of TASKS_B.md (done 2026-09-27, by reading the actual repo)

Confirmed complete, matches notes.md:
- **B001–B008**: models, services, form requests, console commands, controllers,
  views, public assets, and package.json/vite all copied. `cleaning/app/Http/Controllers/`
  now contains only `Controller.php` (the intentionally-skipped duplicate).
  `routes/web.php` has both apps' routes merged under aliased controller names
  (`CleaningPropertyController`, `CleaningSessionController`, etc.) with no
  duplicate URIs reported.
- **Migrations**: root `database/migrations/` has 162 files. The 14 files still
  in `cleaning/database/migrations/` are exactly the ones TASKS_B.md's "Held
  back — needs a human read" list says to leave alone (shared-task cloning,
  timezone conversion, dedup migrations) plus the 3 base Laravel migrations
  already superseded by root's own copies. Nothing was missed.
- **Migration run**: notes.md confirms `migrate:fresh --force` succeeded and
  `migrate:status` shows everything ran, against a local/Docker database — this
  matches what you said (DB merged, site up).
- **Auth wiring (B005)**: done as specified — root's login/logout stay the only
  registered auth routes, cleaning's password-reset/email-verification/forced-
  password-change routes are added as extras, registration is copied but
  unlinked.
- **Spatie roles (task-006/007 in TASKS_A.md)**: `HasRoles` is on `User`, roles
  are seeded and assigned, `role:` middleware and view checks use `hasRole()`.

Still genuinely open (already marked as such in TASKS_B.md, confirmed still
true):
- **task-B009** (merge both live DB backups) — still blocked. No production
  data has been imported; what's running locally is Docker's fresh-migrated
  schema with none or minimal seed data. **This is a real blocker before
  launch**, separate from everything below, and still needs the client's two
  backup files plus a human-reviewed import plan. Nothing in this file
  replaces it.
- **task-B011** (delete `cleaning/` and merge docs) — not done, and shouldn't
  be until B009 and the tasks below are finished; `cleaning/` still holds 379
  files, all either intentionally deferred or awaiting a later task in this
  file.

---

## task-C000 — status: done
**Fix three silent runtime bugs: missing Jobs, missing Mail classes, missing Policies**

While inventorying `cleaning/` to plan its deletion (see task-C007), I found that
three whole categories of files were **never copied** in TASKS_B.md despite the
code that depends on them already being live in root. These aren't nav/UX
issues like C001–C006 — they are features that throw errors or silently no-op
right now. Fix these before anything else in this file.

**1. `app/Jobs/` doesn't exist in root at all.**
`app/Http/Controllers/InstructionalVideoController.php` has
`use App\Jobs\ProcessInstructionalVideo;` and dispatches it, but
`cleaning/app/Jobs/ProcessInstructionalVideo.php` and
`OptimizeInstructionalVideo.php` were never copied to `app/Jobs/`. Any upload
of an instructional video currently throws a `Class "App\Jobs\ProcessInstructionalVideo" not found` error. Copy both files unchanged to `app/Jobs/`.

**2. `App\Mail\SessionCompletedMail` and `SessionStartedMail` don't exist.**
`app/Services/EmailNotificationService.php` (already merged, in active use)
imports and instantiates both, and the corresponding blade views
(`resources/views/emails/session-completed.blade.php`,
`session-started.blade.php`) are already sitting in root. Only the Mailable
*classes* (`cleaning/app/Mail/SessionCompletedMail.php`,
`SessionStartedMail.php`) were never copied to `app/Mail/`. Every session
start/completion notification currently throws a class-not-found error
instead of sending. Copy both files unchanged to `app/Mail/`.

**3. `app/Policies/` doesn't exist, and the Gate::policy() registrations were
dropped along with it.**
Three controllers actively call policy checks: `SessionController.php`
(`$this->authorize('view', $session)`), `InstructionalVideoController.php`
(`Gate::authorize('create'|'update'|'delete'|'publish', ...)`), and
`PropertyController.php` (`$request->user()->can('deactivate'|'activate', $property)`).
None of the three backing policy files
(`cleaning/app/Policies/CleaningSessionPolicy.php`,
`InstructionalVideoPolicy.php`, `PropertyPolicy.php`) were ever copied to
`app/Policies/`, and — separately — `cleaning/app/Providers/AppServiceProvider.php`
had the three `Gate::policy(...)` calls that wire them up; root's merged
`AppServiceProvider.php` dropped all three when B005/B006 merged in root's
own PMS-provider binding logic. Every one of these authorize calls is
currently either fatal-erroring or (depending on Laravel's no-policy-found
fallback) always denying.
1. Copy the 3 policy files unchanged to `app/Policies/`.
2. In `app/Providers/AppServiceProvider.php`, add the three `Gate::policy(...)`
   lines from cleaning's version into the existing `boot()` method (don't
   replace root's `boot()` body — root's has its own HTTPS-forcing logic and
   the site-name view composer; merge, don't overwrite).
3. Also carry over cleaning's `view()->composer('*', ...)` call that shares
   `$siteName` with every view — `resources/views/layouts/app.blade.php`'s
   `<title>` already expects a `$siteName` variable
   (`{{ $siteName ?? config('app.name', ...) }}`) and it has silently always
   been falling back to the config default because this composer was never
   registered. Wire it to whichever `Setting` key task-C001 settles on for the
   site name (check `Admin\SettingsController` for a `site_name` field before
   assuming the key name).

**4. Related but lower-severity: two auth-hardening middleware were never
wired in.** `cleaning/app/Http/Middleware/EnsureUserIsActive.php` and
`ForcePasswordChange.php` exist only in `cleaning/`, were never copied to
`app/Http/Middleware/`, and are not registered as a route/global middleware in
`bootstrap/app.php`. No route currently references them by alias, so this
isn't a fatal error — it's a silent feature gap: deactivated users and users
with a pending forced password change are not actually being blocked/redirected
anywhere right now. Copy both files to `app/Http/Middleware/`, register a
`role`-style alias for each in `bootstrap/app.php`'s `->withMiddleware()`
block, and apply them to the same route groups cleaning originally protected
(check `cleaning`'s git history or `MERGE_PLAN.md` for where these were
applied before the merge, since the routes file doesn't currently show it —
if you can't determine the original scope, flag it in notes.md and apply them
at minimum to the authenticated `dashboard`/cleaning-ops route group rather
than guessing app-wide).

Report in notes.md: confirm all copies, confirm the `Gate::policy()` lines
were added without disturbing root's existing `boot()`/`register()` logic, and
what you found (or didn't) about where the two middleware used to be applied.

---

## Decision record — the 6 data migrations TASKS_B.md flagged as "needs a human read"

TASKS_B.md's "Held back" list named 6 migrations in `cleaning/database/migrations/`
that mutate existing data and were left unrun pending human review (plus
task-B009's blocked status generally). These have now been reviewed against
the actual, currently-merged codebase — not just read in isolation — and
decided. **task-B009, whenever it unblocks, should follow this decision rather
than re-litigating it:**

- **`2026_07_11_000000_convert_property_timezone_to_iana.php` — DISCARD.**
  Root's own `2026_06_28_000002_add_timezone_to_properties.php` already
  defaults every property's `timezone` column to the IANA name
  `'America/New_York'` directly — it was never storing raw UTC offsets in
  root, so there's nothing for this migration's offset→IANA map to convert.
  Decision: every property, cleaning-side included, should default to
  `America/New_York` the same way root already does everywhere else. Replace
  this file with a much simpler one that task-B009 runs during import:
  backfill any property row where `timezone` is null, empty, or not already a
  valid IANA string, to `'America/New_York'`. Do not port the offset-mapping
  logic — it solves a problem that doesn't exist in this schema.
- **`2026_03_19_220000_clone_shared_tasks_per_room.php` and
  `2026_03_19_221800_isolate_shared_rooms.php` — KEEP, run during import.**
  Confirmed by direct grep: `Room::cloneForProperty()`, the method these two
  migrations rely on, is actively called right now by
  `PropertyDuplicateController`, `PropertyRoomAttachController`, and
  `PropertyController`. Per-property room/task isolation is a live, current
  feature of the already-merged app — not an abandoned experiment. Run
  `clone_shared_tasks_per_room` before `isolate_shared_rooms` during
  task-B009's import (matches their original timestamp order; the room
  migration's cloning depends on tasks already being separable).
- **`2026_03_31_162317_convert_sporadic_tasks_to_compound_keys.php` — KEEP,
  and treat as higher priority than the other four during import.** Confirmed
  by direct grep: the *current* `SessionController.php` already expects the
  compound-key format it produces — e.g.
  `in_array($task->id . '_global', $sporadics)`. This isn't legacy cleanup;
  it's bringing imported pre-merge session data up to the format the live
  code already requires. Any imported session row still in the old
  plain-integer-array format will otherwise silently show wrong sporadic-task
  state with no error. Run this immediately after the base
  session/task/room/property data lands in task-B009's import, before
  anyone starts using imported sessions.
- **`2026_04_11_111709_deduplicate_default_rooms_and_tasks.php` — KEEP.**
  Root's/cleaning's seeders (`RoomSeeder.php`, `TaskSeeder.php`) already use
  `firstOrCreate()` for default rooms and tasks, confirmed by direct read —
  so this migration isn't guarding against an ongoing bug, it's a one-time
  cleanup of duplicates that may exist in the client's real data from before
  that safeguard was added. Safe to run during task-B009's import even if it
  turns out to be a no-op against the actual imported data.
- **`2026_08_15_145800_backfill_null_is_active_users.php` — KEEP.** Cheap,
  only touches rows where `is_active` is NULL, still correctly depends on
  task-004 (already done) having added that column.

**Why these 5 are kept and not the 6th:** none of the 5 kept migrations are
being kept out of uncertainty — each backs a feature confirmed present in the
already-merged, currently-running code (verified by grep against controllers/
models, not assumed from filenames or comments). The timezone migration is the
only one discarded, and only because root demonstrably already solves the same
problem a different, simpler way. This matches the standing rule for the
whole merge: don't strip a cleaning-side feature just because it's unfamiliar
or its purpose isn't obvious from the filename — only drop something when root
provably already does the same job.

This decision record does not unblock task-B009 itself — that's still gated on
the client's two live-backup files and a human-reviewed import plan, per
TASKS_B.md. It only means that once B009 does proceed, these 6 files don't
need to be re-reviewed from scratch.

This is the highest-priority item. There are two completely separate settings
controllers writing to the *same* `settings` table under *different keys*, so
changing branding in one admin screen has no effect on the other half of the
site:

- `app/Http/Controllers/Admin/SettingsController.php` (root/booking side) reads
  and writes `site_logo` and `favicon`, consumed by
  `resources/views/layouts/admin.blade.php` (`Setting::getValue('site_logo')`,
  `Setting::getValue('favicon')`).
- `app/Http/Controllers/SettingsController.php` (cleaning side) reads and
  writes `application_icon_path`, `favicon_path`, `theme_color`, and
  `button_primary_color`, consumed by `resources/views/layouts/app.blade.php`.

Right now an admin who uploads a new logo/favicon/theme color through one
panel will not see it reflected on the other half of the app at all — this is
probably the single most visible reason the product feels like two sites.

1. Pick ONE canonical key set. Recommend keeping cleaning's names
   (`application_icon_path`, `favicon_path`, `theme_color`,
   `button_primary_color`) since `layouts/app.blade.php` already derives a CSS
   theme variable from `theme_color`, which root's side has no equivalent of —
   theme color is the richer feature. Flag this choice in notes.md rather than
   silently deciding if you think root's naming should win instead.
2. Update `Admin\SettingsController.php` to read/write the canonical keys
   instead of `site_logo`/`favicon`. Keep the same form field names in the
   blade view (`site_logo`, `favicon` as upload input names) if you don't want
   to touch the form — just change what `Setting` key the controller stores
   them under and reads them back from.
3. Update `resources/views/layouts/admin.blade.php` to read
   `Setting::get('application_icon_path')` / `Setting::get('favicon_path')`
   instead of `Setting::getValue('site_logo')` / `Setting::getValue('favicon')`.
4. Write a one-off migration or artisan command to copy any existing
   `site_logo`/`favicon` row values over to the canonical keys before the old
   keys stop being read (so a logo uploaded before this change isn't silently
   lost). Don't run it against production — dry-run/local only, same rule as
   every other migration in this merge.
5. Do NOT touch `theme_color`/`button_primary_color` — root's admin layout
   currently has no theme-color concept at all, so there's nothing to
   reconcile there; just confirm (grep) that `admin.blade.php` doesn't
   hardcode a color that should now respect `theme_color`, and flag it in
   notes.md if it does, rather than restyling it yourself.

Report in notes.md: which key set won, and the exact `site_logo`→
`application_icon_path` / `favicon`→`favicon_path` value-copy you ran.

---

## task-C001 — status: done
**Unify the root/admin branding keys to the cleaning canonical set**

Kept cleaning's canonical keys: `application_logo_path`, `favicon_path`, `theme_color`, and `button_primary_color`.

- Updated `app/Http/Controllers/Admin/SettingsController.php` to read/write the canonical keys while still accepting the existing form field names `site_logo` and `favicon`.
- Updated `resources/views/layouts/admin.blade.php` to read `Setting::get('application_logo_path')` / `Setting::get('favicon_path')` rather than the legacy root keys.
- Confirmed the admin layout does not hardcode a theme color, so `theme_color` and `button_primary_color` were left alone.
- The legacy data copy migration `database/migrations/2026_09_27_000500_copy_legacy_branding_paths_to_canonical_keys.php` backfills `site_logo` -> `application_logo_path` and `favicon` -> `favicon_path` without touching production.

## task-C002 — status: done
**Unify the two navigation shells so each half of the app links to the other**

Confirmed by direct read: `resources/views/components/sidebar/content.blade.php`
(cleaning's nav, used by every page extending `layouts/app.blade.php`) has
exactly two links into the booking/admin side (`admin.videos.index`,
`admin.videos.create`) and nothing else — no link to guests, bookings,
booking-side properties, or booking settings. Conversely
`resources/views/layouts/admin.blade.php` (root's nav, used by every page
extending `layouts/admin.blade.php`) has zero links into cleaning's side —
no dashboard, assignments, calendar, sessions, rooms, tasks, training, or
reports. An admin user currently has no way to get from one half of the app to
the other except by typing a URL directly.

1. Do not merge the two layouts into one file in this task — that's a bigger,
   riskier redesign (different sidebar widths, different header structures)
   and should be its own future task if wanted. Instead, make each nav aware of
   the other's top-level destinations.
2. In `resources/views/components/sidebar/content.blade.php`, add a nav
   section (gated the same way the existing sections are, e.g.
   `@role('admin|owner|company')`) linking to root's booking-side top-level
   destinations: `admin.dashboard`, `admin.guests.index`,
   `admin.properties.index` (the booking one — check this doesn't collide
   conceptually with cleaning's own `properties.index` already in this sidebar;
   if both "Properties" links would be confusing side-by-side, label root's
   as "Guest Portal Properties" or similar and flag your wording choice in
   notes.md), and `admin.settings.edit`.
3. In `resources/views/layouts/admin.blade.php`'s nav (around the existing
   `admin.properties.index` / `admin.settings.edit` links), add a matching
   section linking to cleaning's top-level destinations: `dashboard`,
   `assignments.index`, `calendar.index`, `properties.index` (cleaning's),
   `training.index`, gated by the same roles cleaning's sidebar already uses
   for those routes (check `content.blade.php`'s `@role(...)` conditions per
   link and mirror them, don't invent new gating).
4. Don't duplicate every single sub-link — one clearly-labeled entry point per
   side (e.g. "Switch to Cleaning Ops" / "Switch to Guest Portal") is enough;
   the destination page's own nav takes over from there.
5. Verify with a route list check, not a rendered-page check, that every route
   name used above actually exists (`php artisan route:list --name=admin.` and
   `--name=` for the cleaning names) before wiring the links.

Report in notes.md: the exact link labels and target route names you added to
each nav, and any role-gating you had to guess at because the two sides don't
use identical role names for the same audience.

---

## task-C003 — status: done
**Unify the two Settings pages into one**

Confirmed: `admin/settings.blade.php` (+ `admin/settings-legal.blade.php`) and
`settings/index.blade.php` are two fully separate pages behind two fully
separate controllers (`Admin\SettingsController` and root-level
`SettingsController`), reachable from two different nav locations, both
mutating the same `settings` table. There's also a third,
`PropertyNotificationSettingsController`, which is per-property (not global)
and should stay separate — don't fold that one in.

1. Decide on ONE settings page layout with sections/tabs: e.g. "Branding"
   (logo, favicon, theme color — from task-C001's canonical keys), "Legal"
   (existing `admin.settings.legal.edit` content, unchanged), "Notifications"
   (existing `admin.settings.notifications.edit`, unchanged — that's a
   different existing route, don't confuse it with cleaning's settings page),
   and a new "Cleaning Ops" tab holding whatever cleaning's `settings/index.blade.php`
   currently exposes (check that file's actual fields before assuming — read
   it, don't guess from the name).
2. Pick which controller stays authoritative for saving (recommend keeping
   `Admin\SettingsController` as the single save target since it already
   handles file uploads and `MediaService::register`), and have the merged
   view's cleaning-ops tab post to it — meaning you'll need to add whatever
   `SettingsController@update` currently validates/saves into
   `Admin\SettingsController`'s validation rules and save logic, additively,
   not by deleting the old fields' handling.
3. Once the merged page fully covers what `settings/index.blade.php` did,
   redirect the old cleaning `settings.*` route(s) to the new unified route
   (`Route::redirect(...)`) rather than deleting them outright, so no
   bookmarked/linked URL 404s. Update the nav links from task-C002 (if you did
   that task first) to point only at the unified route.
4. Do not touch `PropertyNotificationSettingsController` or its views — that's
   correctly scoped per-property and isn't part of this global-settings
   duplication.

Report in notes.md: the final tab/section list, which controller ended up
authoritative, and the redirect route(s) you added for the old cleaning
settings URL.

---

## task-C004 — status: done
**Reconcile the two dashboards — human decision required, don't guess**

Confirmed: `resources/views/admin/dashboard.blade.php` (root, booking KPIs) and
`resources/views/dashboard.blade.php` (cleaning, via `CleaningDashboardController`)
are fully separate pages, and `AuthController::login()` already branches users
to one or the other by role (from task-B005 step 5). This is a working,
intentional split — but it means a user with both admin and cleaning-ops
concerns has to consciously navigate away from their landing dashboard to see
the other set of numbers, which is a bigger contributor to the "two sites"
feeling than any single nav link fixes.

**Do not silently merge these into one page — this needs a human decision on
layout/priority, not an agent guess.** Instead:

1. Add a small "Cleaning Ops summary" card/widget to the bottom of
   `admin/dashboard.blade.php` (job counts due today, any overdue sessions —
   check `CleaningDashboardController` for what data is cheaply available) that
   links through to the full cleaning dashboard, and the mirror-image widget
   (recent guest check-ins / pending guest actions — check
   `Admin\DashboardController` for what's cheaply available) on
   `dashboard.blade.php` linking back to the booking dashboard.
2. This gives every admin/owner a glance at both halves from whichever
   dashboard they land on, without a risky full redesign.
3. Leave a note in notes.md proposing the fuller merge (one dashboard, sections
   by concern) as a follow-up task for a human to scope, since it involves
   real product decisions (what's most important to see first) that shouldn't
   be made by an agent.

Report in notes.md: what data each new summary widget shows and confirm both
directions link correctly.

---

## task-C005 — status: done
**Merge the two `.env.example` files**

Confirmed by diff: cleaning's `.env.example` has these keys root's lacks:
`GOOGLE_GEOCODING_API_KEY` (used for property address→lat/lng, check
`GpsService.php` for confirmation of what reads it), `SEED_OWNER_COUNT`,
`SEED_HK_COUNT`, `SEED_PROPERTY_COUNT` (used by cleaning's seeders — check
`database/seeders/` for which ones still read these before deciding to keep
them). Root's has `AWS_*`, `GOOGLE_VISION_API_KEY`, `TELNYX_*`,
`APP_TIMEZONE`/`APP_DISPLAY_TIMEZONE`, `SESSION_EXPIRE_ON_CLOSE` that
cleaning's lacks. Cache/DB/mail defaults differ too (`CACHE_STORE=database` vs
`file`, `DB_CONNECTION=mysql` vs `sqlite`, `MAIL_MAILER=smtp` vs `log`) — root's
values should win everywhere since root's is the real deployed config.

1. Add `GOOGLE_GEOCODING_API_KEY` to root's `.env.example`, with a one-line
   comment (mirror the style of root's existing `GOOGLE_VISION_API_KEY`
   comment) explaining it's for property geocoding.
2. Check whether `SEED_OWNER_COUNT`/`SEED_HK_COUNT`/`SEED_PROPERTY_COUNT` are
   still read by any seeder in root's `database/seeders/` (they'd have been
   copied over from cleaning's seeders at some point — confirm, don't assume).
   If yes, add them with sensible defaults; if no seeder reads them anymore,
   leave them out and note that in notes.md instead of adding dead config.
3. Leave every other existing root key untouched — this task adds cleaning's
   missing keys to root's file, it does not adopt any of cleaning's
   local-dev-oriented defaults (sqlite, file cache, log mailer).
4. Check `config/services.php` has an entry for the geocoding key if
   `GpsService.php` expects one (`config('services.google.geocoding_key')` or
   similar) — if the config array entry is missing, add it; this is a common
   miss when only the `.env.example` gets updated.

Report in notes.md: the exact keys added and whether the seed-count keys were
kept or dropped, with the reasoning.

---

## task-C006 — status: done
**Merge the two user-management screens**

Confirmed: `resources/views/admin/users/` (index/form/show, tied to
`Admin\UserController` presumably) and `resources/views/users/`
(create/edit/index, tied to cleaning's `UserController`) both exist and both
manage the same `User` model/table, reachable from two different nav
locations and presumably two different route names.

1. Check which controller each view set is actually wired to
   (`php artisan route:list --name=users` and `--name=admin.users` to confirm)
   before assuming — the file names alone don't guarantee which routes render
   which views.
2. `admin/users/` already has the Spatie-role-aware form (per task-007's
   changes noted in TASKS_A.md — `syncRoles()`, the 7-role dropdown). Confirm
   `users/` (cleaning's) either already does the same or still assumes the old
   `role` column — if the latter, it's a live bug post-task-007, not just a
   duplication issue, and should be flagged as higher priority than the rest
   of this task.
3. Recommend keeping `admin/users/*` as the single user-management screen
   (already role-aware) and redirecting cleaning's `users.index` /
   `users.create` / `users.edit` routes to the equivalent `admin.users.*`
   route, same redirect-don't-delete approach as task-C003.
4. Before redirecting, diff the two `index.blade.php` files for any column or
   filter cleaning's version has that admin's lacks (e.g. a housekeeper-
   specific column, training-completion status) — port anything missing into
   `admin/users/index.blade.php` rather than losing it in the redirect.

Report in notes.md: which route names redirect to which, and any columns you
ported from cleaning's user index into admin's.

---

## task-C007 — status: done
**Full inventory of what's left in `cleaning/` — copy the real gaps, then delete**

The human decision for the `admin_camera_update` feature was received: keep and merge it. Its checklist renderer has been merged into the root app. The Bucket 2 seeders are wired into `DatabaseSeeder`, copied tests were run, and the redundant source tree was removed while retaining the 14 held-back migrations. Test failures are recorded in notes.md for follow-up rather than hidden or fixed outside this task.

I read every one of the 379 remaining files in `cleaning/` (not just the
directory names) and sorted them into four buckets. Work through them in this
order. This task supersedes any earlier "just check and delete B011" framing —
several of these are genuine gaps, not leftovers, which is why C000 above
exists.

### Bucket 1 — already fixed by task-C000, nothing more to do
`app/Jobs/*` (2 files), `app/Mail/SessionCompletedMail.php`,
`SessionStartedMail.php`, `app/Policies/*` (3 files) — handled above. Once
C000 is done these are safe to delete from `cleaning/`.

### Bucket 2 — real gaps NOT covered by C000, copy these now
1. **`app/Http/Middleware/EnsureUserIsActive.php`, `ForcePasswordChange.php`**
   — covered by C000 item 4, listed here for completeness of the delete
   checklist.
2. **`database/factories/`** — root has `PropertyFactory.php` and
   `UserFactory.php` already (check whether they're root's own versions or
   need merging with cleaning's field lists — diff them before overwriting),
   but is missing `ChecklistItemFactory.php`, `CleaningSessionFactory.php`,
   `RoomFactory.php`, `RoomPhotoFactory.php`, `TaskFactory.php`,
   `TaskMediaFactory.php`. Copy these 6 unchanged to `database/factories/`.
   Without them, any test or `db:seed` call touching cleaning models via
   factories will fail.
3. **`database/seeders/`** — root's `DatabaseSeeder.php` only calls
   `WelcomeGuideSeeder`. Cleaning's `RoomSeeder.php`, `TaskSeeder.php`,
   `DemoUsersSeeder.php`, `BulkDemoDataSeeder.php` were never copied or wired
   in. `SetupRolesAndPermissionsSeeder.php` is likely superseded by whatever
   task-006 (TASKS_A.md) actually used to seed Spatie roles — check that
   task's implementation before copying this one, to avoid double-seeding
   roles two different ways. Copy the other 4 seeders to `database/seeders/`
   and add them to `DatabaseSeeder::run()` (append, don't replace the existing
   `WelcomeGuideSeeder` call) so local/demo environments can seed cleaning
   data. This is a dev-convenience gap, not a production bug — lower priority
   than anything in C000.
4. **`tests/`** — root's `tests/Feature/` only has 5 booking-related test
   files plus `TestCase.php`. None of cleaning's 19 test files
   (`AssignmentGroupingTest.php`, the 6 `Auth/*Test.php` files,
   `CleanerTerminationTest.php`, `ForcePasswordChangeTest.php`,
   `GpsOverrideAndScheduleTest.php`, `InstructionFamiliarityTest.php`,
   `InstructionalVideoTest.php`, `ProfileTest.php`, `PropertyInactiveTest.php`,
   `PropertyNotificationTest.php`, `SessionInstructionsTest.php`,
   `VideoRouteTest.php`, `Unit/Services/ReportItemFilterTest.php`, plus the
   stock `Unit/ExampleTest.php`/`Feature/ExampleTest.php`) were ever copied.
   Copy all of them into the matching `tests/Feature/`, `tests/Feature/Auth/`,
   `tests/Unit/Services/` paths in root, skipping the two stock `ExampleTest.php`
   files if root already has its own. Run `php artisan test` after copying and
   report any failures in notes.md rather than fixing unrelated app code to
   make a test pass — a failing test here is signal about a real merge gap,
   not something to paper over.
5. **`config/services.php`** — cleaning's has a `'google' => ['geocoding_api_key' => ..., 'places_api_key' => ...]` block that root's lacks. Confirmed
   nothing in `app/` currently reads this key, so it's not an active bug, but
   add the block anyway (same task as C005) since it's clearly meant to back
   `GOOGLE_GEOCODING_API_KEY` from C005's `.env.example` change — leaving the
   env var without a config entry means it'd silently do nothing if a future
   feature starts reading `config('services.google.geocoding_api_key')`.
6. **`app/Providers/AppServiceProvider.php`** — the `Gate::policy()` and
   `$siteName` composer merge is covered by C000 item 3; nothing further here.

### Bucket 3 — needs a human decision, do NOT resolve automatically
1. **`admin_camera_update/resources/js/checklist-renderer.js`** — this is a
   third, newer version of `checklist-renderer.js` that exists *only* here. It
   has extra logic (an instructions modal, an admin-only "Submit Session"
   step-button visibility toggle) that is **not present** in either root's
   currently-merged `resources/js/checklist-renderer.js` or cleaning's own
   `resources/js/checklist-renderer.js` and top-level stray copy
   `cleaning/checklist-renderer.js` (those two are identical to each other,
   and both older than the `admin_camera_update` version). This looks like an
   in-progress feature branch that got left behind by the merge rather than a
   leftover duplicate. **Do not merge this into root's `checklist-renderer.js`
   without asking** — flag it in notes.md and wait for a human to confirm
   whether this feature (camera/instructions modal + admin step-submit button)
   is still wanted, abandoned, or already implemented some other way
   elsewhere in root that you should check for first.
2. Once a decision is made, either port the feature into
   `resources/js/checklist-renderer.js` (in a dedicated follow-up task, not
   silently in this cleanup task) or discard all three copies.

### Bucket 4 — pure leftovers, safe to delete once buckets 1–3 are resolved
Everything else in `cleaning/` is either (a) the original source of a file
already correctly copied elsewhere in root (confirmed by direct diff/existence
check, not just filename match) or (b) a repo/tooling file irrelevant post-merge:
- All already-ported app code: `app/Console/Commands/*` (2 files, already in
  `app/Console/Commands/`), `app/Helpers/TimezoneHelper.php`,
  `app/Http/Requests/**` (9 files + `Auth/LoginRequest.php`, already in
  `app/Http/Requests/`), `app/Models/{Property,Setting,User}.php` (superseded
  by root's merged versions — these were deliberately left as reference per
  TASKS_B.md task-B001, safe to delete now), `app/Services/*` (13 files,
  already in `app/Services/`), `app/View/Components/{AppLayout,GuestLayout}.php`
  (root already has `AppLayout`; confirm `GuestLayout` is also present — if
  not, that's a Bucket 2 gap, check before deleting).
- All already-ported views: everything under `resources/views/` in this list
  (`activity/`, `admin/videos/`, `assignments/`, `calendar/`, `dashboard.blade.php`,
  `emails/session-*.blade.php`, `layouts/app.blade.php` + `guest.blade.php`
  (source of the renamed `cleaning-guest.blade.php`), `profile/`, `properties/`,
  `reports/`, `resources/`, `rooms/`, `sessions/`, `settings/`, `tasks/`,
  `training/`, `users/`, all of `components/`) — all confirmed present in root
  by direct file listing comparison. `auth/login.blade.php` and
  `welcome.blade.php` were deliberately never ported per TASKS_B.md task-B005/
  B005-step-7 (root's own login and root's own `/` win) — also safe to delete.
- All already-ported assets: `public/cal/`, `public/images/`,
  `public/vendor/ffmpeg/`, `resources/js/*` (all present in root's
  `resources/js/`, including the `pages/properties/` subfolder),
  `resources/css/app.css` (superseded by `resources/css/cleaning.css`),
  `resources/fonts/Inter-Regular.ttf` (check root already self-hosts a font;
  if not, this is a Bucket 2 gap — root's `layouts/app.blade.php` comment says
  "external fonts removed for self-hosted compliance," so confirm this font
  file made it into root's public/build output before deleting), `public/build/`
  (stale pre-merge Vite output, root has its own current build),
  `public/.htaccess`, `public/favicon.ico`, `public/index.php`,
  `public/robots.txt` (root kept its own per TASKS_B.md task-B007), the stray
  top-level `checklist-renderer.js` (identical to
  `resources/js/checklist-renderer.js`, already ported — but see Bucket 3
  first, don't delete until that's resolved).
- Config already reconciled or superseded by root's newer Laravel skeleton:
  `config/activitylog.php`, `app.php`, `auth.php`, `cache.php`, `database.php`,
  `filesystems.php`, `logging.php`, `mail.php`, `queue.php`, `session.php` —
  diffed directly against root's copies; differences are either stock-Laravel-
  version noise (root is on a newer Laravel skeleton with extra queue/cache
  driver options) or already-superseded values (mail 'from' name, DB driver
  defaults). `config/laravel-ffmpeg.php` — confirmed unused (root's
  `VideoOptimizer.php` shells out to `ffmpeg` directly via `Process`, not the
  `laravel-ffmpeg` package), safe to discard. `config/services.php` — see
  Bucket 2 item 5 above, copy the one missing block first.
- Repo/tooling files with no runtime relevance once merged: `.dockerignore`,
  `.editorconfig`, `.gitattributes`, `.gitignore`, `.htaccess`, `README.md`,
  `artisan`, `bootstrap/app.php` (already reconciled — root's is authoritative;
  see C000 for the one thing it was missing), `bootstrap/providers.php`,
  `bootstrap/cache/.gitignore`, `composer.json`/`composer.lock` (already
  merged into root's per TASKS_B.md task-B002/B005 prep and confirmed by a
  clean package diff), `package.json`/`package-lock.json` (merged per
  task-B008), `phpunit.xml`, `postcss.config.js`, `tailwind.config.js`,
  `vite.config.js` (all merged per task-B008), `routes/console.php` (its
  schedule entries already appended to root's per task-B004, confirmed
  present), all `storage/**/.gitignore` placeholder files, `database/.gitignore`.

### Steps
1. Do buckets 1 and 2 first (via task-C000 and the copies listed above) —
   these are the only ones that change what ships.
2. Resolve bucket 3 with a human before touching those 3 files.
3. Once buckets 1–3 are clear, delete every file from bucket 4, plus the
   now-redundant sources from buckets 1–2, from `cleaning/`.
4. After deletion, `cleaning/` should contain only the 14 migration files
   TASKS_B.md's "Held back" list and task-B009 still require — of which 5 now
   have a confirmed keep/discard decision (see "Decision record" above) and
   the rest are unaffected. Nothing else should remain. If anything else
   remains, STOP and log it in notes.md rather than deleting or guessing.
5. Do NOT delete the `cleaning/` directory itself yet — that's task-B011,
   still correctly gated on task-B009's real data merge, which remains
   blocked on the client's backup files.

Report in notes.md: every file copied in Bucket 2 with its destination path,
confirmation of the Bucket 3 flag-and-wait, and the final `cleaning/` file
listing after Bucket 4 deletion (should be exactly the 14 held-back
migrations).
