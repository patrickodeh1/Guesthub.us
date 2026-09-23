# Task Queue

Read `AGENTS.md` before doing anything. Read `MERGE_PLAN.md` for full context on
any task. Work top to bottom, one task at a time, status `open` → `in-progress` →
`done`/`blocked`. Log details in `notes.md`, not here — this file stays short.

Tasks 005 and 006 are written but marked `waiting-on-decision` — do not start them
until a human changes that status, because they depend on an unresolved
architecture choice (see MERGE_PLAN.md "Open items" #1).

---

## task-001 — status: done
**Copy 47 non-colliding migrations from `cleaning` into root, unchanged**

Completed: all 47 files were copied and byte-verified, the Docker dry-run succeeded, and the source files were removed from `cleaning/database/migrations/`.

These touch only new tables/columns (sessions, tasks, rooms, checklists, training,
notifications) with zero overlap with root's existing schema. No content changes,
no renaming, no reordering — pure file copy.

Steps:
1. For each file below, `cp` it from `cleaning/database/migrations/<name>` to
   `database/migrations/<name>` (same filename, same timestamp prefix — do not
   rename).
2. After all 47 are copied, run `php artisan migrate --pretend` (dry run, not a
   real migrate) against a local/test DB **inside the Docker stack** — check
   `docker-compose.yml` at repo root for the actual service name, then e.g.
   `docker compose exec app php artisan migrate --pretend`. Do not attempt this
   on the bare host — local dev has no PHP installed outside Docker.
3. Once copied and verified present at the destination, delete the same 47 files
   from `cleaning/database/migrations/`.
4. Do NOT touch any other file in `cleaning/database/migrations/` — the remaining
   ~27 files are intentionally held back (see MERGE_PLAN.md) and are out of scope
   for this task.

Files to copy:
- cleaning/database/migrations/2025_10_16_165158_create_rooms_table.php
- cleaning/database/migrations/2025_10_16_165307_create_tasks_table.php
- cleaning/database/migrations/2025_10_16_165414_create_cleaning_sessions_table.php
- cleaning/database/migrations/2025_10_16_165520_create_checklist_items_table.php
- cleaning/database/migrations/2025_10_16_165701_create_room_photos_table.php
- cleaning/database/migrations/2025_11_01_113006_create_property_room_table.php
- cleaning/database/migrations/2025_11_01_113224_create_room_task_table.php
- cleaning/database/migrations/2025_11_01_113501_task_media_table.php
- cleaning/database/migrations/2026_01_09_115301_add_phase_to_tasks_table.php
- cleaning/database/migrations/2026_01_09_115302_create_property_tasks_table.php
- cleaning/database/migrations/2026_01_09_115338_make_room_id_nullable_in_checklist_items_table.php
- cleaning/database/migrations/2026_01_11_210154_add_scheduled_time_to_cleaning_sessions_table.php
- cleaning/database/migrations/2026_01_15_000000_seed_report_section_colors.php
- cleaning/database/migrations/2026_02_02_000000_create_checklist_item_photos_table.php
- cleaning/database/migrations/2026_02_09_000000_add_skipped_rooms_to_sessions_table.php
- cleaning/database/migrations/2026_02_10_081559_create_housekeeper_owner_table.php
- cleaning/database/migrations/2026_02_13_175215_create_property_checkouts_table.php
- cleaning/database/migrations/2026_02_26_190100_add_report_token_to_cleaning_sessions_table.php
- cleaning/database/migrations/2026_02_27_173500_create_photo_blobs_table.php
- cleaning/database/migrations/2026_03_02_045323_add_stage_to_cleaning_sessions_table.php
- cleaning/database/migrations/2026_03_02_074504_create_checklist_reports_table.php
- cleaning/database/migrations/2026_03_07_192429_add_quantity_to_checklist_items_table.php
- cleaning/database/migrations/2026_03_08_182957_modify_type_enum_in_tasks_table.php
- cleaning/database/migrations/2026_03_08_214530_add_checkout_id_to_cleaning_sessions_table.php
- cleaning/database/migrations/2026_03_17_214732_update_tasks_type_enum.php
- cleaning/database/migrations/2026_03_19_131203_add_is_sporadic_to_tasks_table.php
- cleaning/database/migrations/2026_03_19_131204_add_sporadic_tasks_to_cleaning_sessions_table.php
- cleaning/database/migrations/2026_03_24_143955_add_min_photos_to_rooms_table.php
- cleaning/database/migrations/2026_03_28_162104_alter_room_task_defaults.php
- cleaning/database/migrations/2026_03_30_200000_create_property_user_table.php
- cleaning/database/migrations/2026_06_14_101200_create_instructional_videos_table.php
- cleaning/database/migrations/2026_06_17_165420_add_gps_override_to_cleaning_sessions_table.php
- cleaning/database/migrations/2026_06_17_171919_create_property_notification_recipients_table.php
- cleaning/database/migrations/2026_06_17_171921_create_notification_logs_table.php
- cleaning/database/migrations/2026_07_03_102536_add_room_and_property_to_checklist_item_photos.php
- cleaning/database/migrations/2026_07_16_060903_create_resource_completions_table.php
- cleaning/database/migrations/2026_08_07_094943_add_training_metadata_to_tasks_and_videos.php
- cleaning/database/migrations/2026_08_07_094944_create_training_completions_table.php
- cleaning/database/migrations/2026_08_07_094945_create_assignment_training_snapshots_table.php
- cleaning/database/migrations/2026_08_07_102135_add_time_spent_to_training_completions.php
- cleaning/database/migrations/2026_08_07_103424_add_user_id_to_notification_logs.php
- cleaning/database/migrations/2026_08_07_104606_add_instruction_viewed_to_checklist_items.php
- cleaning/database/migrations/2026_08_07_110725_create_cleaner_instruction_familiarities_table.php
- cleaning/database/migrations/2026_09_03_135323_add_processing_status_to_instructional_videos_table.php
- cleaning/database/migrations/2026_09_04_111306_add_task_level_training_fields.php
- cleaning/database/migrations/2026_09_04_120000_add_recipient_email_to_notification_logs_table.php
- cleaning/database/migrations/2026_09_05_052327_add_views_completed_to_training_completions_table.php

---

## task-002 — status: done
**Properties: add cleaning's non-colliding columns via new migration**

Create one new migration in `database/migrations/` (root), named
`YYYY_MM_DD_HHMMSS_add_cleaning_fields_to_properties_table.php` (use today's date,
a timestamp after the last existing migration in the folder), that adds exactly
these columns to `properties`, matching cleaning's original types:

- `owner_id` — nullable foreign id, references `users.id`, nullOnDelete
- `photo_path` — nullable string
- `beds` — nullable integer
- `baths` — nullable integer (check cleaning's original migration for exact
  type/decimal precision on `baths`, since half-baths are common — copy its type
  exactly, don't guess)
- `geo_radius_m` — check cleaning's original column type and copy exactly
- `ical_url` — nullable string
- `vrbo_ical_url` — nullable string
- `deactivated_at` — nullable timestamp
- `deactivated_by` — nullable foreign id, references `users.id`, nullOnDelete
- `notify_cleaning_started` — boolean, default false
- `notify_cleaning_finished` — boolean, default false
- `notify_photo_started` — boolean, default false
- `notify_task_notes` — boolean, default false

Do NOT add `is_active` — root already has `active` for this concept (see task-003
for how the alias is handled). Do NOT add `airbnb_ical_url` — root already has it.

Source reference for exact types: `cleaning/database/migrations/2025_10_16_165046_create_properties_table.php`,
`cleaning/database/migrations/2026_02_10_055127_add_ical_url_to_properties_table.php`,
`cleaning/database/migrations/2026_02_13_174652_add_airbnb_vrbo_ical_to_properties_table.php`,
`cleaning/database/migrations/2026_06_17_163300_add_is_active_to_properties_table.php`
(for the `owner_id`/`beds`/`baths`/`geo_radius_m`/`deactivated_*` column definitions —
read the original file, don't retype from memory).

After writing the migration, update `app/Models/Property.php`:
- Add all 13 new columns to `$fillable`.
- Add an accessor/mutator so `$property->is_active` reads/writes the same value as
  `$property->active` (Laravel `Attribute::make()` accessor, get returns
  `$this->active`, set writes to `active`). This exists ONLY so cleaning's ported
  code (which will be copied over in a later task and calls `is_active`
  everywhere) keeps working without being rewritten. Add a one-line comment above
  it explaining why.
- Add appropriate `$casts` entries for the new boolean/timestamp columns.

Report the exact column types you used for `beds`/`baths`/`geo_radius_m` in
notes.md since you're reading them from the source file, not being told them here.

---

## task-003 — status: done
**Properties: change checkout_time default to 10am**

In `database/migrations/2026_08_08_152507_add_checkout_time_to_properties.php`,
change `->default('11:00')` to `->default('10:00')`. This is the only change —
one line. This migration has presumably already run in production, so this only
affects properties created after this change is deployed. Do not write a data
migration or touch any existing property row — existing rows are untouched by
design (they already have an explicit stored value).

---

## task-004 — status: done
**Users + Settings: copy the already-reviewed clean migrations, and rewrite the settings-table migration**

Part A — straight copy, no changes, same process as task-001:
- `cleaning/database/migrations/2026_01_07_011950_add_profile_fields_to_users_table.php`
- `cleaning/database/migrations/2026_02_10_055131_add_owner_id_to_users_table.php`
- `cleaning/database/migrations/2026_03_23_214934_add_preferences_to_users_table.php`
- `cleaning/database/migrations/2026_02_13_175207_add_last_login_at_to_users_table.php`
- `cleaning/database/migrations/2026_08_19_045303_add_must_change_password_to_users_table.php`
- `cleaning/database/migrations/2026_08_04_000000_add_termination_fields_to_users_table.php`
- `cleaning/database/migrations/2026_06_17_171917_add_notification_settings_to_properties_table.php`
- `cleaning/database/migrations/2026_06_29_151131_insert_global_notification_settings_into_settings_table.php`
  (already written safely with `insertOrIgnore` — copy verbatim, no edits needed)

Part B — this one needs a rewrite, do NOT copy verbatim:
`cleaning/database/migrations/2026_01_07_020552_create_settings_table.php` currently
does `Schema::create('settings', ...)` — this will fail because the `settings`
table already exists in root's production database (there's no migration for it
in this repo, but the table and `app/Models/Setting.php` are already live and in
use). Create a new migration instead, named
`YYYY_MM_DD_HHMMSS_insert_default_branding_settings.php`, that contains ONLY the
`DB::table('settings')->insertOrIgnore([...])` block from the original file (the 9
rows: `application_logo_path`, `favicon_path`, `site_name`, `theme_color`, and the
5 button colors) — drop the `Schema::create` call and the `down()` method's
`dropIfExists` (replace `down()` with a `whereIn(...)->delete()` for those same 9
keys, matching the pattern already used in
`insert_global_notification_settings_into_settings_table.php`).

Part C — update the model. Edit `app/Models/Setting.php`:
- Keep `getValue()` and `putValue()` as the primary implementation (do not rename
  or remove them — `Booking.php` calls them directly).
- Add 1-hour caching to both, matching the pattern in
  `cleaning/app/Models/Setting.php` (`Cache::remember("setting.{$key}", 3600, ...)`
  on read, `Cache::forget("setting.{$key}")` on write). Read that file for the
  exact pattern.
- Add `get(string $key, $default = null)` and `set(string $key, $value)` as public
  static methods that simply call `getValue()`/`putValue()` — these exist only so
  cleaning's ported controllers (copied in a later task, which call `Setting::get()`
  throughout) keep working unmodified. Add a one-line comment explaining why.

Once all of Part A and B are copied/created and Part C is done, delete the 8
source files listed in Part A plus the original
`2026_01_07_020552_create_settings_table.php` from `cleaning/database/migrations/`.

---

## DECISION MADE (2026-09-22): Full unification onto spatie/laravel-permission.
Root's plain `role` column is retired. Tasks 005/006/007 below are now real,
sequential, open tasks — do them in order, each depends on the previous one
landing cleanly. Do not skip ahead or parallelize them.

## task-005 — status: done
**Install Spatie packages at root and run their migrations**

1. Add to root `composer.json` (`require` section), matching the exact versions
   already used in `cleaning/composer.json`:
   - `"spatie/laravel-permission": "^6.21"`
   - `"spatie/laravel-activitylog": "^4.10"`
2. Run `composer update spatie/laravel-permission spatie/laravel-activitylog`
   (do NOT run a full `composer update` — scope it to just these two packages so
   nothing else drifts). Run this **inside the Docker container** (e.g.
   `docker compose exec app composer update spatie/laravel-permission spatie/laravel-activitylog`
   — check `docker-compose.yml` for the real service name), not on the bare host.
3. Publish both packages' config/migration files, also inside Docker:
   `docker compose exec app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
   `docker compose exec app php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"`
4. Copy these 4 migrations from `cleaning/database/migrations/` into root's
   `database/migrations/`, unchanged (do not edit — these create Spatie's own
   tables, `permissions`/`roles`/`model_has_roles`/etc. and `activity_log`):
   - `2025_10_17_010810_create_permission_tables.php`
   - `2025_10_18_091955_create_activity_log_table.php`
   - `2025_10_18_091956_add_event_column_to_activity_log_table.php`
   - `2025_10_18_091957_add_batch_uuid_column_to_activity_log_table.php`
   Note: root already has its own separate `activity_logs` (plural) table with a
   custom schema (`app/Models/ActivityLog.php`) — this is NOT the same table as
   Spatie's `activity_log` (singular). Both coexist; do not merge or touch root's
   existing `activity_logs` table or model in this task.
5. Delete the 4 files above from `cleaning/database/migrations/` once copied and
   confirmed present at the destination (do not run `migrate` against production —
   dry-run/local only, same rule as task-001).
6. In `app/Models/User.php`, add `use Spatie\Permission\Traits\HasRoles;` and
   `use HasRoles;` inside the class (alongside the existing `HasFactory, Notifiable`).

Report in notes.md: exact package versions actually installed (composer may
resolve a slightly different patch version — record what you got), and confirm
whether `config/permission.php` and `config/activitylog.php` were created.

---

## task-006 — status: done (awaiting human verification)
**Migrate root's 4 existing roles into Spatie roles**

Root currently identifies a user's role via a plain `role` string column
(values: `owner`, `manager`, `staff`, `viewer`) — this task replaces that with
real Spatie roles, and renames `owner` to `admin` in the process (per
MERGE_PLAN.md's role decision — "owner" now means property owner everywhere,
matching cleaning's meaning).

1. Write a new migration `YYYY_MM_DD_HHMMSS_create_spatie_roles_seed.php` (or a
   dedicated seeder if this repo has a seeders convention — check
   `database/seeders/` first) that creates these Spatie roles if they don't
   already exist: `admin`, `company`, `owner`, `manager`, `staff`, `viewer`,
   `housekeeper`. Use `Spatie\Permission\Models\Role::firstOrCreate(['name' => '...'])`
   for each — idempotent, safe to run more than once.
2. Write a **data migration** (separate migration file,
   `YYYY_MM_DD_HHMMSS_migrate_users_role_column_to_spatie.php`) that, in its `up()`:
   - For every user where `role = 'owner'`, assign the Spatie role `admin`
     (`$user->assignRole('admin')`) — this is the super-admin rename.
   - For every user where `role = 'manager'`, assign Spatie role `manager`.
   - For every user where `role = 'staff'`, assign Spatie role `staff`.
   - For every user where `role = 'viewer'`, assign Spatie role `viewer`.
   This must run against the OLD `role` column values before that column is
   dropped (task-007 drops it) — sequencing matters, do not reorder.
   In `down()`: reverse by reading the Spatie role back and writing it into the
   `role` column (best-effort restore, not perfect, but this is standard practice
   for a one-way data migration — note this limitation in a comment in the file).
3. Do NOT drop the old `role` column in this task — that's task-007, after
   verification.

Report in notes.md: how many users were migrated per role (a simple count query
before/after), so a human can sanity-check nobody got silently skipped.

---

## task-007 — status: blocked (role-column drop awaits human access sign-off)
**Replace all `role`-column reads with Spatie `hasRole()` calls, then drop the column**

This task is NOT to be started automatically after task-006 — a human needs to
verify task-006's data migration produced correct results first (query the
`model_has_roles` table and spot-check a few users). Wait for the task-006
status to be explicitly marked `verified` in this file before starting task-007.

Once verified, update these exact files (all confirmed by direct code read —
this is the complete list, nothing else in the codebase reads `->role` or these
helper methods):

1. **`app/Models/User.php`**:
   - Remove `const ROLES`, `const ROLE_LABELS`, `const ROLE_DESCRIPTIONS` (or
     keep `ROLE_LABELS`/`ROLE_DESCRIPTIONS` if still used for display purposes,
     but update their keys from `owner` to `admin` and add entries for `company`
     and `housekeeper` — check `resources/views/admin/users/form.blade.php` and
     `show.blade.php` first to see if these consts are still read there before
     deciding to keep or remove them).
   - Rewrite each helper method to use `hasRole()`/`hasAnyRole()` instead of
     `$this->role === '...'`:
     - `isOwner()` → keep the method name (other code may still call it), but
       change its meaning to check the property-owner role: `return $this->hasRole('owner');`
       — IMPORTANT: this changes what `isOwner()` means (was super-admin, now
       property-owner). Add a new `isAdmin()` method: `return $this->hasRole('admin');`
       for the old super-admin check, since call sites need to be updated to use
       the right one (see step 2 below — most existing `isOwner()` calls actually
       meant "is super admin" under the old system and need to become `isAdmin()`).
     - `isManager()` → `return $this->hasAnyRole(['admin', 'manager']);`
     - `isStaff()` → `return $this->hasAnyRole(['admin', 'manager', 'staff']);`
     - `canManageUsers()` → `return $this->hasRole('admin');`
     - `canManageSettings()` → `return $this->hasRole('admin');`
     - `canViewLogs()` → `return $this->hasAnyRole(['admin', 'manager']);`
     - `canManageProperties()` → `return $this->hasAnyRole(['admin', 'manager']);`
     - `canManageGuests()` → `return $this->hasAnyRole(['admin', 'manager', 'staff']);`
     - `canDeleteData()` → `return $this->hasRole('admin');`
   - `roleLabel()` — update to read `$this->getRoleNames()->first()` (Spatie
     method) instead of `$this->role`.
   - `agreementHostName()` — the `orderByRaw("case when role = 'owner' then 0 else 1 end")`
     query needs rewriting since `role` column is being dropped. Replace with a
     query using Spatie's role tables, e.g. order by whether the user has the
     `admin` role first. Flag this one in notes.md since it's the trickiest
     rewrite — a raw SQL query against Spatie's pivot tables, not a simple method
     swap. If unsure how to do this cleanly, leave the old `role` column read
     working for just this one query and flag it as a follow-up rather than
     guessing at Spatie SQL — do not silently ship something untested here.

2. **`app/Http/Controllers/Admin/UserController.php`** — 3 call sites currently
   read `$user->isOwner()` / `$authUser->isOwner()`. Under the OLD system these
   meant "is this the super-admin". Change all 3 to `isAdmin()` (the new method
   from step 1) since that's what they actually meant.

3. **`resources/views/admin/users/show.blade.php`**:
   - Line with `match($user->role) { 'owner' => ..., 'manager' => ..., 'staff' => ... }`
     → rewrite to use `$user->getRoleNames()->first()` instead of `$user->role`,
     and add a case for `admin` (was `owner`), plus `company` and `housekeeper`.
   - `@if(! $user->isOwner())` → change to `@if(! $user->isAdmin())` (same
     old-meaning correction as UserController).
   - The `canManageUsers()` etc. calls in the permissions table (lines ~115-120)
     work unchanged since those method names didn't change, only their bodies.

4. **`resources/views/admin/users/form.blade.php`**:
   - `old('role', $user->role)` — this reads/writes the role for the edit form.
     Needs rewriting to work with Spatie (e.g. `old('role', $user->getRoleNames()->first())`
     for display, and the form submission / controller-side save needs to call
     `$user->syncRoles([$request->role])` instead of setting a `role` column —
     check `UserController@update`/`@store` for where the role is currently
     saved and update that too, even though it wasn't in the original file list,
     since this form implies a corresponding save path exists).
   - `! $user->isOwner()` → `! $user->isAdmin()`.

5. **`resources/views/layouts/admin.blade.php`**:
   - `auth()->user()->roleLabel()` — works unchanged once `roleLabel()` is fixed
     in step 1.
   - `auth()->user()->canManageUsers()` — works unchanged.

6. **`routes/web.php`**:
   - `Route::middleware('role:owner')` → `Route::middleware('role:admin')`
   - `Route::middleware('role:owner,manager')` → `Route::middleware('role:admin,manager')`
   - Check whether the `role:` middleware itself is a custom one built for the
     old plain-column system (search `app/Http/Middleware/` for a file
     registering the `role` middleware alias) — if it checks `$request->user()->role === $param`,
     it needs rewriting to `$request->user()->hasRole($param)` (or
     `hasAnyRole(explode(',', $param))` for the multi-role group) since it's
     custom middleware, not Spatie's — Spatie ships its own `role` middleware
     but this app may have its own. Check first, don't assume.

7. Once all of the above is done AND a human has spot-checked login/admin-panel
   access still works correctly for at least one user of each role, write a
   final migration to drop the old `role` column from `users`. Do not do this
   until told to — mark this specific step `blocked` on human sign-off even if
   everything else in this task is done.

This is the most invasive task in the whole merge — go slowly, and if anything
about the current `role:` middleware implementation doesn't match what's
described above, stop and log it in notes.md rather than guessing.

---

## Held back — needs a human read before becoming a task (not yet written)

Do not touch these files or attempt to write tasks for them without a human
reviewing their actual content first — they mutate/reshape existing data in ways
that depend on flipstatus's specific historical data quirks and may not be safe
to run against a freshly merged database:

- `cleaning/database/migrations/2026_03_19_220000_clone_shared_tasks_per_room.php`
- `cleaning/database/migrations/2026_03_19_221800_isolate_shared_rooms.php`
- `cleaning/database/migrations/2026_03_31_162317_convert_sporadic_tasks_to_compound_keys.php`
- `cleaning/database/migrations/2026_04_11_111709_deduplicate_default_rooms_and_tasks.php`
- `cleaning/database/migrations/2026_07_11_000000_convert_property_timezone_to_iana.php`
- `cleaning/database/migrations/2026_08_15_145800_backfill_null_is_active_users.php`
  (this one specifically needs task-004 done first, since it backfills the
  `is_active` column that migration adds)

## Skip permanently — already exist at root, confirmed by direct comparison

- `cleaning/database/migrations/0001_01_01_000001_create_cache_table.php`
- `cleaning/database/migrations/0001_01_01_000002_create_jobs_table.php`
