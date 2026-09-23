# GuestHub.us Merge Plan (Reference — do not edit; log progress in notes.md)

## Goal
Merge `cleaning` (flipstatus) into root (`checkin`/Guest Hub) as **one** Laravel app:
one login, one database, one admin platform, one deployment. Client: James.
Root of this repo is the live target app. `cleaning/` is a temporary source folder —
files get ported out of it and deleted from it as we go. When done, `cleaning/` is empty
and removed.

## Client requirements (verbatim intent, from chat)
- One domain, one login page, redirect by role (guest / cleaner / admin).
- One database, one properties list.
- Guest Hub's existing property/booking data model wins on any naming collision
  ("guest hub stays, flipstatus merges in").
- `.env`: merge flipstatus vars in; on any duplicate key, Guest Hub's value wins
  (API keys, email settings, etc.).
- Fix the recurring bug: logos and task images disappear from the server periodically.
  UNRESOLVED as of this writing — ruled out `photos:prune-old` (correctly scoped to
  RoomPhoto/ChecklistItemPhoto only, not logos/task-media). Root cause still unknown.
- Cleaner-submitted "finished" photos: auto-delete after 14–15 days (client gave both
  numbers in different messages — confirm exact number with James; not urgent, this
  is already implemented via `photos:prune-old --days=14`, just confirm the value).
- Reduce total site size — was ~4GB backup / ~10GB storage. Found and fixed one major
  cause already: `cleaning/public/files` and `cleaning/public/storage` were two full
  421MB physical duplicates of the same data (should have been one real dir + one
  symlink). Duplicates deleted; symlink deferred until after merge (target path changes).
- Cleaner-facing task images/videos: compress before storing (client noted a 5MB photo
  is unnecessarily large for in-app viewing). Not yet implemented — needs a task.
- Admin settings: add one-click buttons for storage backup, script backup, cache
  clear, route clear. Not yet implemented — needs a task once `cleaning`'s
  `clear_cache.php` (moved to `_removed_scripts/`, logic worth reusing) is ported
  properly as an authenticated Artisan command + admin UI button.
- New property checkout-time default: 10am (was 11am). Rule: only applies to new
  properties / properties with no explicit value already set — never overwrite an
  existing explicit value during data population.

## Security issues found and fixed during cleanup (already done, for the record)
- `assign_role.php` — unauthenticated script granting admin/owner/housekeeper roles
  to user ID 1 to anyone hitting the URL. Removed.
- `phpinfo.php`, `debug_live_server.php`, `scanner.php` (hardcoded server paths),
  `read_log.php`, `check_all_images.php`, `find_missing_image.php`,
  `list_users.php`, `reoptimize_videos.php`, `update_property_queries.php`,
  `clear_cache.php` — all removed from web-accessible locations.
- `public/storage_backup22` (421MB), `public/storage_backup`, and
  `public/cal/complete_project_backup.zip` (803KB) — live, publicly-downloadable
  backup dumps sitting in the web root. Removed.
- `checkin/public/storage_old_broken_backup_20260802` — removed (local-only, never
  shipped, per client).
- All `.bak26`/`.backup`/`.bak` files across both apps — confirmed via diff to be
  older/stripped versions of live files (missing GPS override, training validation,
  onboarding, SMS/email notifications). Deleted.
- Recommend flagging to James: rotate any credentials that may have been exposed
  during the period `assign_role.php`/`phpinfo.php` were live, since we can't confirm
  how long they were reachable or whether cPanel served from `public/` or app root.

## Final schema/architecture decisions

### Roles
Resolved naming collision: "owner" means different things in each app (root:
super-admin; cleaning: property owner). Decision: **keep cleaning's meaning** —
`owner` = property owner going forward. Root's current super-admin role is
renamed to `admin` (reusing cleaning's existing top-level role, not inventing
a new one — cleaning's controllers already have full `hasRole('admin')` /
`hasAnyRole([...])` logic wired everywhere).

Final role set (7, no collisions): `admin`, `company`, `owner` (property owner),
`manager`, `staff`, `viewer`, `housekeeper`.

**DECIDED (2026-09-22): Full unification onto `spatie/laravel-permission`**
(cleaning's existing package) for both apps. Root's plain `role` column is
retired entirely, not kept in parallel. See TASKS.md task-005/006/007 for the
exact sequenced steps — this is the single riskiest part of the whole merge
(touches auth/access-control on a live app), so it's broken into three
dependent tasks with a mandatory human verification checkpoint between the
data migration (task-006) and the code-rewrite/column-drop (task-007). Do not
let an agent run all three back to back unattended.

Root's actual current role implementation (confirmed by reading the code):
- `app/Models/User.php`: `const ROLES = ['owner','manager','staff','viewer']`,
  plus `ROLE_LABELS`, `ROLE_DESCRIPTIONS`, and helper methods `isOwner()`,
  `isManager()`, `isStaff()`, `canManageUsers()`, `canManageSettings()`,
  `canViewLogs()`, `canManageProperties()`, `canManageGuests()`, `canDeleteData()`.
- Touch points needing update when "owner"→"admin" (5 files + 1 migration):
  - `app/Models/User.php` (consts + helper method bodies)
  - `app/Http/Controllers/Admin/UserController.php` (lines using `isOwner()`, 3 call sites)
  - `resources/views/admin/users/show.blade.php`
  - `resources/views/admin/users/form.blade.php`
  - `resources/views/layouts/admin.blade.php`
  - `routes/web.php` — two `Route::middleware('role:owner')` /
    `Route::middleware('role:owner,manager')` groups → become `role:admin` /
    `role:admin,manager`
  - Data migration: existing users with `role = 'owner'` → update to `role = 'admin'`
    (production data, not just schema — must run once, carefully, against the
    real merged DB, not blind on any test data).

### Properties (`properties` table)
Root/checkin's schema is the base (wins on any name collision, per client rule).
Add these columns from cleaning, unchanged:

| New column | Type/notes |
|---|---|
| `owner_id` | FK to users (property owner) |
| `photo_path` | string |
| `beds`, `baths` | property specs |
| `geo_radius_m` | GPS-fence radius for on-site cleaner check-in |
| `ical_url` | secondary/generic calendar sync (separate from existing `airbnb_ical_url`) |
| `vrbo_ical_url` | VRBO calendar sync |
| `deactivated_at`, `deactivated_by` | soft-deactivation audit trail |
| `notify_cleaning_started`, `notify_cleaning_finished`, `notify_photo_started`, `notify_task_notes` | booleans, per-property notification toggles |

One naming collision resolved: root has `active` (boolean), cleaning has
`is_active` (boolean) — same concept. Root's `active` wins per the naming rule.
Cleaning's ported code (uses `is_active` throughout) needs an accessor alias on
the merged `Property` model — do **not** rename every call site in ported
cleaning code; add `is_active` as an aliased attribute pointing at `active`.

`checkout_time` default: change `database/migrations/2026_08_08_152507_add_checkout_time_to_properties.php`
from `default('11:00')` to `default('10:00')`. Confirmed this is column-default-only;
no data backfill needed — existing rows keep their explicit stored value regardless.

### Users (`users` table)
No naming collisions. Straightforward additive merge — cleaning's user-related
migrations (already read, all clean):
- `add_profile_fields_to_users_table` → adds `phone_number`, `profile_photo_path`
- `add_termination_fields_to_users_table` → adds `is_active` (boolean, defaults
  true), `terminated_at`, `terminated_by` (FK, nullOnDelete), `termination_reason`
- `add_owner_id_to_users_table`, `add_preferences_to_users_table` (JSON),
  `add_last_login_at_to_users_table`, `add_must_change_password_to_users_table`,
  `backfill_null_is_active_users` (data backfill, run after the column exists)

Note: cleaning's `is_active` on `users` does NOT collide with anything on root's
`users` table (root has no such column) — this one is a clean add, unlike the
`Property.active`/`is_active` collision above. Don't confuse the two.

### Settings (`settings` table)
Root has an `app/Models/Setting.php` (`getValue()`/`putValue()`, key/value,
**no caching**) actively used in `Booking.php` for `default_deposit_cap_cents` and
`processing_fee_percent` — but there is **no `create_settings_table` migration in
the repo**, meaning the table already exists in production outside of git history.

Cleaning's `Setting.php` (`get()`/`set()`, same key/value shape, **with 1-hour
cache + cache-clear on write**) and its `create_settings_table.php` migration
(9 default branding/theme rows) cannot run as-is — `Schema::create('settings', ...)`
will fail against production since the table already exists.

**Decision:**
- Rewrite cleaning's `create_settings_table.php` as an **insert-only** migration
  (drop the `Schema::create` block entirely) using `insertOrIgnore` for the 9
  branding/theme default rows, so it's safe whether or not those keys already exist.
- Unified `Setting` model: keep root's `getValue()`/`putValue()` as the real
  implementation (root wins per the naming rule), add cleaning's caching behavior
  into them, then add `get()`/`set()` as thin aliases calling the same underlying
  logic — so cleaning's ported code (calls `Setting::get(...)` throughout
  `ChecklistController` etc.) keeps working with zero call-site rewrites.
- `insert_global_notification_settings_into_settings_table.php` — already written
  correctly as `insertOrIgnore`, ships as-is, no changes needed.

## Migration inventory (74 total in cleaning)

**Skip entirely (2)** — root already has these (Laravel boilerplate, confirmed
present at root):
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`

**Held back — needs the role decision (A/B above) resolved first (1)**:
- `2025_10_17_010810_create_permission_tables.php` — Spatie's own tables;
  only needed if role decision is (A) or if cleaning's roles get Spatie under
  plan (B). Also requires `spatie/laravel-permission` + `spatie/laravel-activitylog`
  actually installed via composer first, or `artisan migrate` fails outright.

**Held back — one-time data-shape migrations, need a human read before running (4)**:
these were written to fix up flipstatus's own historical data mess and may not
make sense (or may silently no-op, or worse, misfire) against a freshly merged DB:
- `2026_03_19_220000_clone_shared_tasks_per_room.php`
- `2026_03_19_221800_isolate_shared_rooms.php`
- `2026_03_31_162317_convert_sporadic_tasks_to_compound_keys.php`
- `2026_04_11_111709_deduplicate_default_rooms_and_tasks.php`

**Held back — touches properties/users/settings, now fully decided above, ready
to become real tasks (14)**: see TASKS.md task-002/003/004.

**Held back — touches properties data specifically (1)**:
- `2026_07_11_000000_convert_property_timezone_to_iana.php` — data migration,
  needs a read before running (not yet reviewed in detail).

**Held back — touches users data specifically (1)**:
- `2026_08_15_145800_backfill_null_is_active_users.php` — depends on the
  termination-fields migration landing first (adds the `is_active` column it backfills).

**Safe, no collisions, ready for straight copy (remaining ~51)**: see TASKS.md task-001.

## Local dev vs. production environment (important — read before running anything)
- **Local development uses Docker for everything** (PHP, composer, npm, the
  database — the whole stack runs inside containers). Any task instruction that
  says "run `php artisan ...`" or "run `composer ...`" locally means: run it
  through Docker (e.g. `docker compose exec app php artisan migrate --pretend`,
  or whatever this repo's actual compose service/container name is — check
  `docker-compose.yml`/`Dockerfile` at repo root first, since these were
  present in the original checkin app before the merge and may need restoring
  or are still there). Do not assume a bare `php`/`composer` binary is on the
  host machine — it generally is not (confirmed: local sandbox has no `php`
  installed at all).
- **Production (cPanel) does NOT use Docker.** It runs PHP directly via
  cPanel's own PHP handler, with `composer install` (not `update`) run directly
  on the server via SSH, no containers involved.
- Practical effect on tasks: any task that includes a "dry-run migrate to
  verify" step should be run inside the local Docker stack, not attempted on
  bare metal. If Docker isn't available/running when an agent picks up a task,
  skip the verification step and say so in notes.md rather than trying to
  install PHP on the host or skipping straight to running against production.
- A `Dockerfile`/`docker-compose.yml` currently exists in `cleaning/` — when
  `cleaning`'s remaining app code gets ported over in later tasks, decide then
  whether root's existing Docker setup already covers cleaning's needs (likely
  yes, since both are just PHP/Laravel/MySQL) or whether anything
  cleaning-specific (e.g. `ffmpeg` for video processing, referenced in
  `public/vendor/ffmpeg/`) needs adding to the Docker image. Not a task yet —
  flag it as a follow-up once the app-layer merge starts.

## Open items (unresolved, need decisions/input)
1. ~~Role system depth~~ — RESOLVED 2026-09-22: full Spatie unification (option A).
2. `cal/` (StaySync) tool in `cleaning/public/cal/` — DEFERRED, not a blocker.
   James was never asked and hasn't mentioned it since. Decision: leave it
   untouched in `cleaning/public/cal/` for now (don't port, don't delete). Once
   the merged app is deployed, test the live site end-to-end — if nothing
   breaks without it and nobody asks about it, treat that as the answer and
   remove it during that post-deploy cleanup pass. Do not spend more merge time
   on this until then.
3. Disappearing logos/task-images bug — root cause not yet found. Ruled out
   `photos:prune-old`. Next suspects, not yet checked: `TaskMediaController.php`'s
   media-deduplication/usage-count delete logic, `Task.php`'s `deleted` model hook,
   and whether `config/filesystems.php` / the `public` disk symlink setup was ever
   broken/pointed somewhere ephemeral on the live server.
4. Exact checkout-time value discrepancy in client's own messages: "14 days" (5:59pm
   message) vs "15 days" (merge notes) for photo retention — confirm with James
   which is correct; currently implemented as 14.
