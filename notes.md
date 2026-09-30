## [task-C007] 2026-09-27 21:55
- Confirmed the repo already contains the required Bucket 2 copies for the remaining real gaps: middleware, factories, seeders, and tests were copied earlier and are present at root paths under `app/Http/Middleware/`, `database/factories/`, `database/seeders/`, and `tests/`.
- Confirmed the previously identified root `config/services.php` block is already present with `google.geocoding_api_key` and `google.places_api_key`; no additional copy is required for that item.
- User decision received: the `admin_camera_update` branch is wanted and should be merged rather than discarded.
- Merged the admin-camera checklist feature branch into `resources/js/checklist-renderer.js` and verified the file parses cleanly with `node --check`.
- This resolves the human-decision bucket and makes it safe to continue with final C007 cleanup, subject to a last pass through the exact `cleaning/` leftovers inventory.

## [task-C006] 2026-09-27 21:40
- Confirmed the authoritative user-management screen is the root admin flow: `admin.users.*` routes resolve to `App\Http\Controllers\Admin\UserController`, while the cleaning `users.*` routes were duplicates of the same underlying user table but not the canonical admin experience.
- Replaced the cleaning user index/create/edit routes with redirects to the admin user routes to keep the single screen and avoid a second user-management UX.
- Kept the existing admin layout as the single source of truth for user management because it already matches the Spatie role-aware form and filters.
- Route verification: `php artisan route:list --name=users.index --name=users.create --name=users.edit --name=admin.users.index --name=admin.users.create --name=admin.users.edit` resolves the admin routes and the cleaning ones as redirects.
- Files touched: `/home/soarersavannah/Guesthub.us/routes/web.php`.

## [task-C005] 2026-09-27 21:15
- Added the missing root `.env.example` key: `GOOGLE_GEOCODING_API_KEY` with the same style of inline comment used for the Google Vision key.
- Added the matching config entry in `config/services.php` under `google` for `geocoding_api_key` and `places_api_key` so the merged app can read the same Google keys the cleaning app expects.
- Confirmed root's seed-count vars are not used anywhere in the root `database/seeders/` tree; no `SEED_OWNER_COUNT`/`SEED_HK_COUNT`/`SEED_PROPERTY_COUNT` references remain. They were intentionally not added to `.env.example` because they are dead config in this merged app.
- Files touched: `/home/soarersavannah/Guesthub.us/.env.example`, `/home/soarersavannah/Guesthub.us/config/services.php`, `/home/soarersavannah/Guesthub.us/notes.md`.

## [task-C004] 2026-09-27 21:00
- Added a lightweight cross-dashboard summary card to the cleaning dashboard linking to the root admin dashboard and a matching cross-link on the admin dashboard linking back to the cleaning command center.
- Kept both links to valid named routes only: `admin.dashboard` and `dashboard`.
- This stays within the conservative C004 scope: a small summary widget, no broader dashboard redesign or product guessing.
- Files touched: `/home/soarersavannah/Guesthub.us/resources/views/dashboard.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/admin/dashboard.blade.php`.

## [task-001] 2026-09-22 18:31
- Copied all 47 listed non-colliding migrations from `/home/soarersavannah/Guesthub.us/cleaning/database/migrations/` to `/home/soarersavannah/Guesthub.us/database/migrations/`.
- Verified every copied file is byte-for-byte identical to its source.
- The root Docker Compose service is `app`; `docker compose exec app php artisan migrate --pretend` was attempted but failed because the service is not running.
- Source migration files were not deleted because the required Docker dry-run could not be completed.

## [task-001] 2026-09-22 19:01
- Completed the task after Docker became available.
- Verified all 47 root/source migration pairs byte-for-byte.
- Ran `docker compose exec -T app php artisan migrate --pretend` successfully.
- Deleted the 47 verified source files from `/home/soarersavannah/Guesthub.us/cleaning/database/migrations/`.
- Task 2 was not started; the `properties` migration-order issue remains separate.

## [task-002] 2026-09-22 19:03
- Added `database/migrations/2026_09_22_190500_add_cleaning_fields_to_properties_table.php`.
- Added the 13 requested cleaning properties fields with source-compatible types:
  `beds` and `baths` are `unsignedTinyInteger` with default `0`; `geo_radius_m` is
  `unsignedInteger` with default `150`; `photo_path` is `string(2048)`; `ical_url`
  is a nullable string; `vrbo_ical_url` is `string(1000)`; the notification fields
  are booleans defaulting to `false`; and deactivation fields are nullable timestamp
  and nullable foreign id with `nullOnDelete`.
- Updated `app/Models/Property.php` with the new fillable fields, boolean/datetime
  casts, and an `is_active` alias backed by the root `active` column.
- Verified the migration with `docker compose exec -T app php artisan migrate --pretend`
  and verified the model alias with Laravel Tinker (`alias-ok`).

## [task-003] 2026-09-22 19:08
- Changed `database/migrations/2026_08_08_152507_add_checkout_time_to_properties.php`
  from a default checkout time of `11:00` to `10:00`.
- No data migration or existing property rows were changed.

## [task-004] 2026-09-22 19:11
- Copied and byte-verified the 8 reviewed user/property/settings migrations into
  `/home/soarersavannah/Guesthub.us/database/migrations/`.
- Added `2026_09_22_191000_insert_default_branding_settings.php` as an insert-only,
  `insertOrIgnore` migration for the 9 branding settings, with a targeted rollback.
- Updated `app/Models/Setting.php` with one-hour caching for `getValue()`, cache
  invalidation in `putValue()`, and `get()`/`set()` compatibility aliases.
- Ran `docker compose exec -T app php artisan migrate --pretend` successfully.
- The live local database has no `settings` table, so the Setting Tinker check could
  not run; the migration is intentionally insert-only for the existing production
  table as specified.
- Deleted the 8 copied source migrations and the original settings-table migration
  from `cleaning/database/migrations/`.

## [task-005] 2026-09-23 07:30
- Added `spatie/laravel-permission` (`^6.21`) and
  `spatie/laravel-activitylog` (`^4.10`) to root `composer.json`.
- Ran the scoped Composer update inside Docker. Resolved versions:
  `spatie/laravel-permission` 6.25.0 and `spatie/laravel-activitylog` 4.12.3
  (plus `spatie/laravel-package-tools` 1.93.2).
- Published both package configs: `config/permission.php` and
  `config/activitylog.php` were created.
- Published package migration stubs, then replaced the generated duplicate
  migrations with the four reviewed migrations copied unchanged from
  `cleaning/database/migrations/`, so each Spatie schema migration exists once.
- Added `HasRoles` to `app/Models/User.php`.
- Ran the Docker migration dry run successfully; it includes the permission
  tables and singular `activity_log` while preserving root's plural
  `activity_logs` migration.
- Verified the Spatie trait autoloads, Composer metadata is valid, and
  `git diff --check` passes.
- Deleted the four copied source migrations from `cleaning/database/migrations/`.

## [task-006] 2026-09-23 07:33
- Added `2026_09_23_073500_create_spatie_roles_seed.php` with idempotent creation
  of the seven agreed roles using the configured `web` guard.
- Added `2026_09_23_073501_migrate_users_role_column_to_spatie.php`, mapping
  legacy `owner` to `admin`, and `manager`, `staff`, and `viewer` to matching
  Spatie roles. The legacy `role` column was not changed or dropped.
- The Docker migration dry run succeeded and showed the expected role inserts and
  four legacy-role user scans.
- The local database has no `users` table, so before/after role counts could not
  be queried and the data migration was not executed. Production counts remain
  unreported until the migration is run against the intended database.
- PHP syntax checks and `git diff --check` passed.
- Task 7 must remain blocked until a human verifies `model_has_roles` and spot-checks
  migrated users.

## [task-C003] 2026-09-27 17:09
- Finalized the duplicate settings consolidation by treating the root admin settings screen as the canonical save target.
- Kept the admin controller authoritative for all global branding/legal/notification settings while preserving the legacy cleaning settings URL paths as redirects rather than live edit pages.
- Updated the legacy cleaning settings entry points to redirect to `/admin/settings` and `/admin/settings/legal` / `/admin/settings/notifications` so bookmarks and old links keep working without breaking the app.
- Files touched: `/home/soarersavannah/Guesthub.us/routes/web.php`, `/home/soarersavannah/Guesthub.us/TASKS_C.md`.
- Validation: `docker compose exec -T app php artisan route:list --name=settings.index --name=settings.update --name=admin.settings.edit --name=admin.settings.update` completed cleanly, and the redirect routes resolve to the actual admin settings routes instead of a missing named route.

## [task-007] 2026-09-23 07:40
- Human verification of task 6 was confirmed before starting task 7.
- Replaced application role checks with Spatie role APIs in `User`, the custom
  `RequireRole` middleware, user administration, routes, and guest alert
  recipient lookup.
- Updated the user administration flow to filter with `role()`, assign roles on
  creation, sync roles on update, and use `isAdmin()` for former super-admin
  protections.
- Updated role labels/descriptions and admin views for the seven unified roles.
- Rewrote `agreementHostName()` to prioritize users with the Spatie `admin` role
  through the `model_has_roles` and `roles` tables.
- The legacy `users.role` column was intentionally not dropped. Task 7 is blocked
  pending human verification that login/admin access works for at least one user
  of each role; no drop migration was created.

## [task-006] 2026-09-23 07:32
- Added `2026_09_23_073500_create_spatie_roles_seed.php` to idempotently create
  `admin`, `company`, `owner`, `manager`, `staff`, `viewer`, and `housekeeper`.
- Added `2026_09_23_073501_migrate_users_role_column_to_spatie.php` to map legacy
  `owner` to `admin`, and `manager`, `staff`, and `viewer` to their matching
  Spatie roles. The legacy `role` column remains intact for task 7.
- The Docker database has not had migrations applied, so no live `users` rows were
  available for before/after counts. The migration is validated with a dry run;
  exact production counts must be recorded when this migration is run against the
  intended database.

## [task-B001] 2026-09-23 08:28
- Copied the 17 non-colliding model files listed in `TASKS_B.md` from `/home/soarersavannah/Guesthub.us/cleaning/app/Models/` to `/home/soarersavannah/Guesthub.us/app/Models/`.
- Verified every copied file byte-for-byte with `cmp` and passed PHP syntax checks inside the Docker `app` service.
- Cross-checked cleaning model references against the merged `Property`, `User`, and `Setting` compatibility surfaces; no missing referenced fields were found. `Property` provides the required cleaning fields and `is_active` alias, and `Setting` provides the required `get()`/`set()` aliases.
- Removed only the 17 verified source model files from `cleaning/app/Models/`; `Property.php`, `User.php`, and `Setting.php` remain as reference files.
- The task title says 16 models, but its explicit file list contains 17; all 17 listed files were copied.

## [task-B002] 2026-09-23 08:35
- Renamed cleaning's SMS service to `CleaningSmsNotificationService` to avoid colliding with root's unrelated Telnyx `SmsNotificationService`.
- Copied the renamed service to `/home/soarersavannah/Guesthub.us/app/Services/CleaningSmsNotificationService.php`.
- Copied the 13 listed non-colliding services unchanged into `/home/soarersavannah/Guesthub.us/app/Services/` and verified each copy byte-for-byte.
- Updated the six specified cleaning service/controller/command files to import and call `CleaningSmsNotificationService`; also updated the existing cleaning `PropertyNotificationTest.php` because it contained remaining calls to the renamed class.
- Linted all 21 changed/copied PHP files inside Docker and confirmed no `SmsNotificationService` references remain under `cleaning/`.
- Left the cleaning service directory in place as required; the renamed source service remains until the controller porting task.

## [task-B003] 2026-09-23 08:48
- Copied the 9 listed form requests from `/home/soarersavannah/Guesthub.us/cleaning/app/Http/Requests/` into `/home/soarersavannah/Guesthub.us/app/Http/Requests/`.
- Copied the complete currently present `Auth/` subdirectory: `LoginRequest.php` into `/home/soarersavannah/Guesthub.us/app/Http/Requests/Auth/`.
- Verified all 10 copied files byte-for-byte with `cmp` and passed PHP syntax checks inside the Docker `app` service.
- Source request files remain in `cleaning/` as required until the controller-porting task.

## [task-B004] 2026-09-23 08:50
- Copied `PruneOldSessionPhotos.php` and the already-renamed `SendTrainingReminders.php` into `/home/soarersavannah/Guesthub.us/app/Console/Commands/`.
- Verified both command copies byte-for-byte and passed PHP syntax checks inside the Docker `app` service.
- Appended the required schedules to `/home/soarersavannah/Guesthub.us/routes/console.php`: `photos:prune-old --days=14` daily at 02:00 and `training:send-reminders` hourly.
- Verified both commands are registered by `php artisan list` inside Docker. Scheduler runtime behavior remains for human verification as required by the task.
- Left both source command files in `cleaning/app/Console/Commands/`; no source deletion was performed.

## [task-B005] 2026-09-23 08:52
- Blocked before copying controllers or modifying routes because the task's stated inventory does not match the repository: `cleaning/app/Http/Controllers/` contains 35 files total, including `Controller.php`, therefore 34 non-auth controllers, while B005 specifies 31 non-auth controllers.
- The cleaning route file also has URL collisions with root: both define `/` and `/img/{path}`. The task explicitly requires stopping on any route collision instead of guessing which route wins.
- Auth inspection found 10 cleaning auth controllers and 7 auth views. The supplementary auth routes can be evaluated after the controller-count and route-collision decisions are clarified.
- No B005 controllers, auth views, auth routes, or cleaning routes were copied, deleted, or changed. `TASKS_B.md` is marked blocked.

## [task-B005] 2026-09-23 08:57
- Re-read the updated B005 instructions. The route collision guidance now resolves the cleaning `/` route by skipping it, but the controller inventory still does not match the repository.
- The task specifies 33 named non-auth controllers plus the skipped `Controller.php`; the repository currently contains 34 named non-auth controllers. The complete named list is: `ActivityController.php`, `AssignmentController.php`, `CalendarController.php`, `ChecklistController.php`, `DashboardController.php`, `FamiliarityReportController.php`, `InstructionalVideoController.php`, `ManageSessionController.php`, `PhotoController.php`, `ProfileController.php`, `PropertyAssignmentsApiController.php`, `PropertyController.php`, `PropertyDuplicateController.php`, `PropertyNotificationSettingsController.php`, `PropertyRoomAttachController.php`, `PropertyRoomController.php`, `PropertyRoomOrderController.php`, `PropertyTaskOrderController.php`, `ResourcePageController.php`, `ResourcesController.php`, `RoomController.php`, `RoomSuggestionController.php`, `RoomTaskAttachController.php`, `RoomTaskOrderController.php`, `SessionController.php`, `SessionReportController.php`, `SettingsController.php`, `TaskController.php`, `TaskMediaController.php`, `TaskSuggestionController.php`, `TrainingController.php`, `TrainingReportController.php`, `UserController.php`, and `UserFamiliarityController.php`.
- No B005 files were copied or modified. B005 remains blocked until the task explicitly accounts for the 34th named controller.

## [task-B005] 2026-09-23 09:02
- Copied all 34 named non-auth controllers from `cleaning/app/Http/Controllers/` and all 10 auth controllers into the root application; skipped only the duplicate base `Controller.php`.
- Copied the six non-login auth views: `confirm-password.blade.php`, `force-password-change.blade.php`, `forgot-password.blade.php`, `register.blade.php`, `reset-password.blade.php`, and `verify-email.blade.php`. These files use `<x-guest-layout>` rather than the task's anticipated `@extends('layouts.guest')`, so no nonexistent `@extends` references were rewritten. Their shared layout/components are intentionally deferred to B006.
- Registered cleaning's supplementary password-reset, email-verification, forced-password-change, and in-session password-change routes in `routes/web.php`. Cleaning login, logout, and registration routes remain intentionally unregistered; root's `AuthController` remains the single login entry point.
- Updated `AuthController@login` to redirect `admin` users to `admin.dashboard`, cleaning roles (`owner`, `manager`, `staff`, `housekeeper`, `company`) to `dashboard`, and unmapped/no-role users to `admin.dashboard` with a warning activity log.
- Merged the non-root cleaning web routes. The cleaning `/` route was skipped as instructed. The required route collision check confirmed cleaning uses `/file/{path}`, while root uses `/img/{path}`; they are distinct. No other same-method/URI duplicates were found after the merge.
- The dashboard route name used is `dashboard`, with `auth` and `verified` middleware. This creates a known risk: admin-created users without `email_verified_at` will be blocked by `verified`; no policy change was made.
- `php artisan route:list` passed and reported 324 routes; a JSON route check found zero duplicate method/URI pairs. Key dashboard, password-reset, and forced-password-change routes were confirmed.
- Blade compilation remains deferred until B006 copies the cleaning shared Blade components (`x-guest-layout`, `auth-validation-errors`, and related components); `view:cache` currently fails because those components are not yet present.
- Removed the verified source controllers, six auth views, `cleaning/routes/auth.php`, and the fully ported `cleaning/routes/web.php`.

## [task-B006] 2026-09-23 09:08
- Copied 130 non-auth view files from the specified cleaning view directories into the root application without overwriting existing files.
- Copied cleaning's `dashboard.blade.php`, `layouts/app.blade.php`, and the colliding guest layout as `layouts/cleaning-guest.blade.php`. The app layout was included because the ported views use `<x-app-layout>` and root had no equivalent layout.
- Checked the specified copied views for `@extends('layouts.guest')` and found no references, so no layout-reference rewrite was needed. Cleaning's auth views remain separate and untouched; the root login view was not overwritten.
- Shared view directory collision checks for `admin/`, `components/`, and `emails/` found no overlapping filenames. All copied files, including both renamed/required layouts, passed byte-for-byte verification. `git diff --check` passed.
- `php artisan view:cache` now reaches the next unresolved integration dependency and fails on `<x-heroicon-o-moon>` in `layouts/cleaning-guest.blade.php`. Cleaning declares `blade-ui-kit/blade-heroicons`, while root does not; dependency reconciliation is deferred to B008.

## [task-B007] 2026-09-23 09:11
- Copied `cleaning/public/cal/` to `public/cal/` unchanged for the self-contained StaySync tool. No integration or `storage:link` command was run.
- Copied all four files from `cleaning/public/images/` to `public/images/`: `assets/clean-logo-small.png`, `assets/clean-logo.png`, `assets/room-ready-logo.png`, and `placeholders/property.png`. No exact filename collisions existed, so no `cleaning-` renames were needed. Existing cleaning property views reference `images/placeholders/property.png`, which is now present at the expected path.
- Root had no existing `public/vendor/ffmpeg/` directory, so the five cleaning FFmpeg files were copied unchanged to that path.
- Cleaning's loose root-level files (`favicon.ico`, `.htaccess`, `index.php`, and `robots.txt`) all already existed in root `public/`; they were retained and not overwritten per the naming rule.
- All copied B007 asset files passed byte-for-byte verification. `git diff --check` passed.

## [task-B008] 2026-09-23 09:16
- Merged cleaning's frontend dependencies into the root `package.json`: Alpine/collapse, Perfect Scrollbar, SortableJS, Dropzone, and the cleaning PostCSS/Tailwind companion packages. Root's existing Tailwind 4 dependency remains authoritative over cleaning's Tailwind 3 pin; the cleaning Tailwind plugins were retained as declared dependencies but the incompatible Tailwind 3 `@import` directives were not carried into the merged CSS.
- Preserved root `resources/js/app.js` and `resources/css/app.css`. Copied cleaning's feature scripts and created separate `resources/js/cleaning-app.js` and `resources/css/cleaning.css` entrypoints. The cleaning app layout and renamed cleaning guest layout now load both root and cleaning entrypoints.
- Added both cleaning entrypoints to `vite.config.js`; root's existing entrypoints remain unchanged. The cleaning CSS was adapted to Tailwind 4-compatible standalone utility rules and retains the Perfect Scrollbar stylesheet import.
- `npm install` updated `package-lock.json`. The requested `docker compose exec app npm install && npm run build` could not run because the PHP-only `app` image has no `npm` executable (`exec: "npm": executable file not found`). The equivalent root build was run with the available Node/npm installation and passed: Vite produced root and cleaning CSS/JS bundles and updated `public/build/manifest.json`.
- Script copies and the cleaning app bundle passed byte-for-byte verification. `git diff --check` passed.

## [task-B009] 2026-09-23 09:21
- B009 is blocked pending human confirmation of the approved live backups and review of the import approach. No database restore, migration, import, or destructive database command was run.
- The repository contains two candidate data sources: `database/database.sql` (a 112 KB phpMyAdmin MariaDB dump for `welcome_guide`, generated 2026-05-19, with 17 `CREATE TABLE` statements) and `cleaning/database/database.sqlite` (a 5.9 MB SQLite database with 31 tables, including 16 users, 2 properties, 19 rooms, 44 tasks, and 2 cleaning sessions).
- These files have not been confirmed as the current root/checkin and flipstatus/cleaning live backups. The SQL and SQLite sources also require a reviewed cross-database import plan before any data is moved into a disposable staging database.

## Migration verification - 2026-09-23 10:44
- Fixed the fresh-migration failure caused by `last_login_at` being added both by `2026_02_13_175207_add_last_login_at_to_users_table.php` and `2026_05_14_000003_enterprise_upgrade.php`. The enterprise migration now adds/drops only its own `last_login_ip` and related fields.
- Fixed the second fresh-migration failure caused by the four property notification columns being added both by `2026_06_17_171917_add_notification_settings_to_properties_table.php` and `2026_09_22_190500_add_cleaning_fields_to_properties_table.php`. The later migration now owns only its unique cleaning property fields.
- `docker compose exec -T app php artisan migrate:fresh --force` completed successfully, and `php artisan migrate:status` reported all migrations as ran. No production database was touched.

## [activity-rendering-fix] 2026-09-23 20:49
- Added the missing `App\View\Components\AppLayout` class so cleaning pages using `<x-app-layout>` render through `resources/views/layouts/app.blade.php`.
- Added `blade-ui-kit/blade-heroicons` and refreshed Composer dependencies for the cleaning layout's Heroicon components.
- Configured Blade Icons to register its default component as `blade-icon`, preserving the root application's local `<x-icon>` component. This fixes the runtime collision that caused `SvgNotFound` for the local `guide` icon.
- Verified with Docker: `php artisan optimize:clear`, `php artisan view:cache`, and PHP linting all passed. `/login` rendered successfully with HTTP 200; unauthenticated `/activity` returned the expected HTTP 302 redirect to login.
- Files touched: `app/View/Components/AppLayout.php`, `composer.json`, `composer.lock`, `config/blade-icons.php`.
- The pre-existing untracked `app/Console/Commands/ImportCleaningLegacyData.php` was left untouched.

## [assignments-property-relations] 2026-09-23 20:52
- Added the missing `rooms()` and `propertyTasks()` Eloquent relationships to `app/Models/Property.php`, matching the existing cleaning model contracts and root pivot tables.
- Verified the model syntax and exercised the same eager-loading/count query used by `AssignmentController`; both relationships resolved successfully across the local Docker database (`cleaning_sessions=2`).
- Unauthenticated `/assignments` returned the expected HTTP 302 redirect to login.
- No schema, role, permission, or unrelated files were changed.

## [properties-active-scopes] 2026-09-23 20:54
- Added `active()`, `inactive()`, and `withInactive()` query scopes to `app/Models/Property.php`, mapping cleaning's expected scope API to the authoritative root `active` column.
- Verified in Docker that the scopes execute successfully: `active=3`, `inactive=0`, `all=3`.
- PHP lint and `git diff --check` passed. Unauthenticated `/properties` returned the expected HTTP 302 redirect.

## [properties-owner-relation] 2026-09-23 20:57
- Added the missing `owner()` relationship to `app/Models/Property.php`, matching `PropertyController::with('owner.roles')` and the existing `owner_id` foreign-key convention.
- Verified in Docker that eager loading `owner.roles`, `rooms`, and `propertyTasks` succeeds for the local properties data.
- PHP lint and `git diff --check` passed. Unauthenticated `/properties` returned the expected HTTP 302 redirect.

## [properties-owner-fallback] 2026-09-23 21:04
- Updated both responsive sections of `resources/views/properties/index.blade.php` to display `Unassigned` when a property has no related owner.
- This handles the existing local data where all three active properties have `owner_id = NULL` without altering ownership data or schema.
- Laravel view cache compilation and `git diff --check` passed. Unauthenticated `/properties` returned the expected HTTP 302 redirect.

## [task-C000] 2026-09-27 09:58
- Copied the missing runtime classes from `cleaning/` into root: `app/Jobs/ProcessInstructionalVideo.php`, `OptimizeInstructionalVideo.php`, `app/Mail/SessionCompletedMail.php`, `SessionStartedMail.php`, `app/Policies/CleaningSessionPolicy.php`, `InstructionalVideoPolicy.php`, `PropertyPolicy.php`, and `app/Http/Middleware/EnsureUserIsActive.php`, `ForcePasswordChange.php`.
- Added the three `Gate::policy(...)` registrations and the shared `$siteName` view composer to `app/Providers/AppServiceProvider.php` without disturbing the root HTTPS logic.
- Registered the two middleware aliases and appended them to the web middleware group in `bootstrap/app.php` so deactivated users and forced-password-change users are gated as intended.
- Verified with Docker PHP linting (`php -l` via `docker compose exec -T app ...`) and the app bootstrap/provider syntax checks; all passed.
- File list touched: `/home/soarersavannah/Guesthub.us/app/Jobs/*`, `/home/soarersavannah/Guesthub.us/app/Mail/*`, `/home/soarersavannah/Guesthub.us/app/Policies/*`, `/home/soarersavannah/Guesthub.us/app/Http/Middleware/*`, `/home/soarersavannah/Guesthub.us/app/Providers/AppServiceProvider.php`, `/home/soarersavannah/Guesthub.us/bootstrap/app.php`.
- No production database or live app state was changed; this was validated inside the local Docker stack only.

## [task-C002] 2026-09-27 11:07
- Confirmed the repo already contains the C002 cross-links in both nav shells and verified the route names resolve as live routes via Docker: `admin.dashboard`, `admin.guests.index`, `admin.properties.index`, `admin.settings.edit`, `dashboard`, `assignments.index`, `calendar.index`, `properties.index`, and `training.index`.
- Root/admin sidebar includes the cleaning handoff section labeled `Switch to Cleaning Ops` with links to the cleaning dashboard, assignments, calendar, cleaning properties, and training pages.
- Cleaning sidebar includes the guest/admin handoff section labeled `Guest Portal` with links to the admin dashboard, guests, guest portal properties, and settings.
- The wording choice matched the existing labels in the repo (`Guest Portal Properties` on the cleaning side, `Cleaning Properties` on the admin side) to reduce confusion between root properties and cleaning properties.
- Role gating was kept aligned with the existing cleaning sidebar using the same `@role('admin|owner|company')` pattern; no broader role assumptions were introduced.

## [task-C002] 2026-09-27 11:07
- Verified the nav handoff is already present and valid in the repo: the root admin sidebar includes a "Switch to Cleaning Ops" section linking to `dashboard`, `assignments.index`, `calendar.index`, `properties.index`, and `training.index`; the cleaning sidebar includes a "Guest Portal" section linking to `admin.dashboard`, `admin.guests.index`, `admin.properties.index`, and `admin.settings.edit`.
- Route validation passed via Docker: `dashboard`, `assignments.index`, `calendar.index`, `properties.index`, `training.index`, `admin.dashboard`, `admin.guests.index`, `admin.properties.index`, and `admin.settings.edit` all resolve as live routes.
- Role gating was mirrored from the existing cleaning sidebar: the cross-link section is wrapped in `@role('admin|owner|company')` when that same access pattern is used on the cleaning side; no extra guessed role broadening was introduced.
- The admin page label wording choice was kept as the existing repo labels, including "Guest Portal Properties" for the root-side destination to avoid confusion with the cleaning `properties.index` link.

## [task-C007] 2026-09-27 12:08
- Wired the copied `RoomSeeder`, `TaskSeeder`, `DemoUsersSeeder`, and `BulkDemoDataSeeder` into root `DatabaseSeeder::run()` after the existing `WelcomeGuideSeeder` call. The seeder passes PHP syntax validation; it was not executed because it creates demo users and bulk demo records.
- Confirmed the user decision to keep `admin_camera_update`; its newer checklist renderer is present in `resources/js/checklist-renderer.js` with instructions modal/edit-report logic. `node --check resources/js/checklist-renderer.js` passes.
- Ran the merged test suite in Docker with the in-memory SQLite PHPUnit configuration. The suite has multiple failures. A focused `AssignmentGroupingTest` run had 4 passing and 2 failing tests: both authenticated housekeeper requests expected HTTP 200 but received 302. This aligns with the already documented `verified` middleware risk; no auth policy was changed as part of C007.
- Confirmed `cleaning/` contains exactly the 14 held-back migration files and no other files. No held-back migration was run, and no production database was touched.
- Reconciled stale queue statuses: B001–B003 are done per their prior copy/syntax verification notes; B004 is done with scheduler runtime explicitly awaiting human verification; B010 is blocked on B009 staging verification and production authorization; B011 is blocked pending deployment burn-in and migration/data resolution. A006/A007 remain gated on real-data/human role verification.
- Validation: Docker PHP lint for `database/seeders/DatabaseSeeder.php`, `node --check resources/js/checklist-renderer.js`, and `git diff --check` pass. Full app tests remain failing as noted above.
- Files touched this pass: `/home/soarersavannah/Guesthub.us/database/seeders/DatabaseSeeder.php`, `/home/soarersavannah/Guesthub.us/resources/js/checklist-renderer.js` (feature merge predates this pass), `/home/soarersavannah/Guesthub.us/TASKS_C.md`, `/home/soarersavannah/Guesthub.us/TASKS_B.md`, `/home/soarersavannah/Guesthub.us/notes.md`; redundant files under `/home/soarersavannah/Guesthub.us/cleaning/` were removed, leaving the 14 migrations.

## [force-password-change-layout] 2026-09-27 12:28
- Fixed the `/force-password-change` 500: `<x-guest-layout>` had always rendered the booking-specific `layouts.guest`, which dereferences a required property. The component now selects `layouts.cleaning-guest` when no guest property is supplied and preserves the existing booking layout when a property is supplied.
- Added a regression test verifying an authenticated user can render the forced-password-change page without a property.
- The focused regression test passes in Docker (1 test, 2 assertions). The full `ForcePasswordChangeTest` initially exposed a separate unavailable sidebar icon on the dashboard; that was fixed in the follow-up below.
- Files touched: `/home/soarersavannah/Guesthub.us/app/View/Components/GuestLayout.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/ForcePasswordChangeTest.php`, `/home/soarersavannah/Guesthub.us/notes.md`.

## [dashboard-sidebar-icon] 2026-09-27 12:40
- Replaced unavailable `<x-heroicon-o-arrows-right-left>` in the Guest Portal sidebar with the installed Heroicons v1 component `<x-heroicon-o-switch-horizontal>`.
- Verified the entire Blade view set compiles with `php artisan view:cache`, then cleared the generated view cache.
- Re-ran `ForcePasswordChangeTest` in Docker; all 5 tests and 12 assertions pass, including the normal-password dashboard request.
- Files touched: `/home/soarersavannah/Guesthub.us/resources/views/components/sidebar/content.blade.php`, `/home/soarersavannah/Guesthub.us/notes.md`.

## [admin-settings-route] 2026-09-27 12:52
- Fixed the dashboard's `Route [admin.settings.edit] not defined` error by replacing the self-redirects under the `admin` prefix with GET actions to their existing controllers: general settings (`SettingsController@edit`), legal settings (`SettingsController@legalEdit`), and notification settings (`NotificationSettingsController@edit`). This preserves existing update actions and leaves legacy `/settings` redirects intact.
- Docker route listing confirms `admin.settings.edit`, `admin.settings.legal.edit`, and `admin.settings.notifications.edit` all resolve to controller actions rather than self-redirects.
- Added `AdminSettingsNavigationTest` for a verified admin visiting the cleaning dashboard and rendering the Guest Portal settings link. The regression test passes (1 test, 2 assertions). `php artisan view:cache` and `git diff --check` pass.
- Files touched: `/home/soarersavannah/Guesthub.us/routes/web.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/AdminSettingsNavigationTest.php`, `/home/soarersavannah/Guesthub.us/notes.md`.

## [task-D001] 2026-09-28 11:38
- Implemented the shared light-by-default theme initializer; restored class-driven Tailwind dark variants, dark-eval colors and installed plugins; repaired cleaning hover/focus selectors; added root admin dark palette compatibility and theme toggle; replaced the dropdown theme mutation hacks; extended the screenshot runner for both themes without invoking host PHP or migrations; and added theme initialization/toggle tests.
- Verified `npm run build`, JS syntax checks, `git diff --check`, the shared theme/admin navigation/forced-password tests (9 tests, 28 assertions), and Chrome behavior on `/login`: a dark OS with no stored choice stays light, stored `true` and JSON-string `"true"` activate dark, and stored `false` returns to light. The local login returned HTTP 200.
- Conflicting acceptance scope: D001 lists a guest portal page in dark-mode verification, while D007 explicitly requires the guest portal to remain light-only. Kept the guest layout light-only using `data-theme="light"`.
- The full screenshot matrix was not run because the existing capture flow also restarts/completes the onboarding tour and submits an override-checkin mutation against the app database. Visual review is limited to `/login`; the remaining authenticated page matrix remains unverified.
- Unexpected finding: `git status` showed the tracked root `/home/soarersavannah/Guesthub.us/guesthub` SQLite database modified after local verification. This file is explicitly held out of scope. It was not inspected or reverted; the task is blocked pending human review of that database change.
- Files touched for D001: `/home/soarersavannah/Guesthub.us/resources/css/app.css`, `/home/soarersavannah/Guesthub.us/resources/css/cleaning.css`, `/home/soarersavannah/Guesthub.us/resources/js/app.js`, `/home/soarersavannah/Guesthub.us/resources/js/cleaning-app.js`, `/home/soarersavannah/Guesthub.us/resources/views/auth/login.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/components/action-dropdown.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/components/dropdown/item.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/admin.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/app.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/cleaning-guest.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/guest.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/theme-init.blade.php`, `/home/soarersavannah/Guesthub.us/scripts/capture-screenshots.js`, `/home/soarersavannah/Guesthub.us/tests/Feature/SharedThemeInitializationTest.php`, and generated `/home/soarersavannah/Guesthub.us/public/build/*` assets.

## [task-D001-continuation] 2026-09-28 12:03
- Followed the user's instruction to ignore the modified `guesthub` SQLite file; it was not read, changed, or reverted. Continued D001 rather than treating that file as a blocker.
- Expanded dark styling for the root admin sidebar and tour overlays. Made screenshot output/profile directories configurable, added login-failure detection, removed the script's migration and tour/check-in POST mutations, and kept a two-theme default screenshot pass plus `--theme=light|dark`.
- Rebuilt assets successfully. The focused theme, admin-navigation, and forced-password tests passed (9 tests, 28 assertions); JS syntax checks, screenshot-theme argument validation, and `git diff --check` passed. Chrome verified `/login` in light and dark, including dark-OS/light-default, stored theme persistence, and legacy JSON-string storage. The cleaning/admin authenticated views are also covered by rendered-feature tests where available.
- Attempted the screenshot matrix on the running local Docker app. The configured preview login did not authenticate, so protected routes redirected to `/login`; therefore only `/login` was visually verified in light and dark. Authenticated matrix pages were not checked and no screenshots are being retained. The screenshot script now fails explicitly for this case. The user later confirmed all of D001's required dark-theme coverage works; D001 is marked done per that manual verification.
- The guest portal remains deliberately light-only per D007's explicit requirement, despite D001's conflicting dark guest-page acceptance line.
- Temporary screenshot images, manifest, and browser profiles were removed from `/tmp`. The tracked SQLite file remains untouched.

## [task-D001-user-direction] 2026-09-28 12:10
- User directed us to ignore the SQLite file and continue. The file remains untouched. D001 is still blocked only on the authenticated visual matrix because the configured screenshot account redirected to `/login`.
- Checked D000: it is `waiting-on-decision`, not done. No D000 code or data changes were made. Per the user's explicit instruction to move on, started D002; D001's implementation is present, but its authenticated screenshots remain unverified.
- Task D002 is now in progress.

## [task-D002] 2026-09-28 13:12
- Replaced the two authenticated shells with `resources/views/layouts/unified.blade.php`; `AdminLayout` and `AppLayout` now render it, and the cleaning header slot is rendered in the shared title area. The shell keeps the root tour hooks, search, notifications, theme toggle, alerts, media picker, confirmation modal, and mobile drawer behavior.
- Added `config/navigation.php` and `App\Support\Navigation` for three sections and `Route::has()` checks. Added `App\Support\Branding` for the unified logo/icon/site-name block. Property links use the same owner/company/housekeeper visibility conditions as the existing property index; those conditions were extracted into `Property::visibleTo()` and the controller now uses that scope.
- Cleaning-only JavaScript/CSS, upload permission, toast UI, and checklist note/photo/inventory/instructions/gallery modals are conditional on `<x-app-layout>`. The existing language selector and theme variables were retained for cleaning pages. Root admin pages load only the root Vite entry; cleaning pages load both entries, and only `cleaning-app.js` calls `Alpine.start()`.
- Final navigation visibility encoded in the config:
  - Admin: all Guest Portal and Cleaning Ops links; all Shared links. Early Access Signups and Users are admin-only; Activity Log uses `admin.logs.index`.
  - Manager: Cleaning Ops dashboard, assignments, calendar, sessions/reports, training, and resources; Shared Activity Log (`admin.logs.index`), Settings, and My Account. Rooms/tasks/property links and the Guest Portal group are hidden.
  - Owner/company: Guest Portal links except Early Access Signups; Cleaning Ops links including rooms/tasks; Shared Properties (filtered by the existing owner/company property scope), Activity Log (`activity.index`), Settings, Security, Admin Guide, and My Account. Users remains admin-only.
  - Housekeeper/staff/viewer: Cleaning Ops dashboard, assignments, calendar, sessions/reports, training, and resources; Shared Activity Log (`activity.index`) and My Account. Guest Portal, property, rooms, tasks, and admin-only links are hidden.
- Deleted the unreferenced `layouts/admin.blade.php`, `layouts/app.blade.php`, `components/navbar.blade.php`, and all files under `components/sidebar/` after the reference search returned no remaining uses. No changes were made to `cleaning-guest.blade.php` or page-content form/dropdown components.
- Validation: Docker PHP syntax checks, `php artisan view:cache` followed by `view:clear`, retired-shell reference search, and `git diff --check` passed. After the user authorized tests, `UnifiedNavigationTest` passed (3 tests, 122 assertions), covering all seven role link sets, owner property scoping, and the root admin shell.
- Blockers/unexpected findings: manual checks at 375px and browser-console checks for one admin page and one checklist page remain pending because the configured preview account redirects to `/login`. A transient owner-dashboard render exposed `Call to undefined method App\Models\Property::users()` in `DashboardController`; it was not changed because this task does not authorize unrelated dashboard/controller work. An existing `PropertyInactiveTest` could not initialize because it references the missing `Database\Seeders\SetupRolesAndPermissionsSeeder`. A temporary render check also received HTTP 403 on `admin.settings.edit`; the root-admin browser check remains pending.
- The cleaning-entity search expansion was not made; it remains a follow-up pending review of the existing search endpoint. The root `guesthub` SQLite file remains ignored and untouched. D003 and later tasks were not started.
- Files touched: `/home/soarersavannah/Guesthub.us/TASKS_D.md`, `/home/soarersavannah/Guesthub.us/notes.md`, `/home/soarersavannah/Guesthub.us/config/navigation.php`, `/home/soarersavannah/Guesthub.us/app/Support/Navigation.php`, `/home/soarersavannah/Guesthub.us/app/Support/Branding.php`, `/home/soarersavannah/Guesthub.us/app/Models/Property.php`, `/home/soarersavannah/Guesthub.us/app/Http/Controllers/PropertyController.php`, `/home/soarersavannah/Guesthub.us/app/View/Components/AppLayout.php`, `/home/soarersavannah/Guesthub.us/app/View/Components/AdminLayout.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/unified.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/navigation.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/cleaning-modals.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/cleaning-toasts.blade.php`, and `/home/soarersavannah/Guesthub.us/resources/views/components/icon.blade.php`. Deleted: `/home/soarersavannah/Guesthub.us/resources/views/layouts/admin.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/app.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/components/navbar.blade.php`, and `/home/soarersavannah/Guesthub.us/resources/views/components/sidebar/{sublink,header,overlay,link,content,dropdown,sidebar,footer}.blade.php`.
- A temporary existing-suite probe was also attempted. It failed during setup because `PropertyInactiveTest` references missing `Database\Seeders\SetupRolesAndPermissionsSeeder`; no production or local database migration was run.

## [task-D000] 2026-09-28 13:39
- Implemented the approved fix without removing the `verified` middleware: newly admin-created accounts now receive `email_verified_at` immediately, and `UserFactory` already defaults new users to verified while retaining its explicit `unverified()` state. Existing demo seeders also specify verification timestamps for newly created users.
- Added `users:backfill-email-verification`, which previews the count by default, accepts `--dry-run`, and only writes when explicitly passed `--apply`. It updates `email_verified_at` only for records whose `status` is `active` and timestamp is NULL; it never changes inactive or already-verified users. Conflicting `--dry-run --apply` flags are rejected.
- Added focused feature tests for admin-created users and the backfill; updated the AssignmentGroupingTest fixture to use a non-default password so the forced-password-change middleware does not redirect the scenario under test.
- Validation: `ActiveUserEmailVerificationTest` and `AssignmentGroupingTest` passed together (9 tests, 48 assertions). `php artisan users:backfill-email-verification --dry-run` in the local Docker app reported 0 eligible active accounts and made no changes. PHP syntax checks and `git diff --check` passed.
- No `--apply` backfill was run against any database. The production backfill remains a deliberate operator action after reviewing the preview count in the target environment. No schema or role/permission changes were made.
- Files touched: `/home/soarersavannah/Guesthub.us/TASKS_D.md`, `/home/soarersavannah/Guesthub.us/notes.md`, `/home/soarersavannah/Guesthub.us/app/Http/Controllers/Admin/UserController.php`, `/home/soarersavannah/Guesthub.us/app/Console/Commands/BackfillActiveUserEmailVerification.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/ActiveUserEmailVerificationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/AssignmentGroupingTest.php`.

## [task-D001-user-confirmation] 2026-09-28 13:47
- User confirmed that all required dark-theme behavior works, including the authenticated-page coverage. D001 was marked done based on this manual verification.
- The earlier scripted screenshot attempt could not authenticate, but this no longer blocks D001 after the user's explicit confirmation.

## [task-D002-continuation] 2026-09-28 13:52
- User directed to mark D001 done and proceed with D002. Kept D002 `in-progress`; no following D task was started.
- The shared theme test still referenced the deleted `layouts/admin.blade.php` and `layouts/app.blade.php`. Updated its shell assertions to use the unified layout. The focused theme, unified navigation, and admin navigation suite now passes: 7 tests, 139 assertions. Blade cache compilation, shell-reference search, and `git diff --check` pass.
- D002 role navigation and owner-scoped property feature coverage are passing. Manual 375px drawer/checklist-camera and browser-console acceptance remains unverified because the configured preview account redirects to login. The previously observed `Property::users()` owner-dashboard error is still outside D002's scoped implementation and remains unchanged.

## [task-D002-user-navigation-direction] 2026-09-28 14:23
- Restored the root navigation order from the pre-unification admin sidebar: Guest Admin (Dashboard, Guests, Properties), Settings with its existing submenu, then Administration in its existing item order. Cleaning Ops is added between Settings and Administration as one collapsible containing the cleaning navigation; the root and cleaning dashboards remain separate.
- Kept the root Properties link and added nested per-property Check In/Out Details, Guest Guide, and Availability links. Property visibility remains owner-scoped. Updated the brand link to return root pages to `admin.dashboard` and Cleaning Ops pages to the separate `dashboard`.
- Added `manualtest.md` with the requested 375px drawer, checklist camera/photo, and browser-console checks. These are pending and were not represented as completed; the user directed D002 be marked done.
- Validation in the local `guesthub_app` container: focused navigation/theme/settings tests passed (7 tests, 152 assertions); PHP lint, Blade view cache/clear, and `git diff --check` passed. The browser/manual acceptance could not be run because the configured preview login redirects to `/login`. The pre-existing `Property::users()` dashboard error remains unchanged.
- Files touched: `/home/soarersavannah/Guesthub.us/config/navigation.php`, `/home/soarersavannah/Guesthub.us/app/Support/Navigation.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/navigation.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/navigation-item.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/unified.blade.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/UnifiedNavigationTest.php`, `/home/soarersavannah/Guesthub.us/manualtest.md`, `/home/soarersavannah/Guesthub.us/TASKS_D.md`, and `/home/soarersavannah/Guesthub.us/notes.md`.

## [task-D002-nav-permissions] 2026-09-28 14:37
- Updated the navigation after the user's clarification: housekeepers now see their own standalone Cleaning Ops links (My Jobs, Calendar, Sessions, Training, Photos, Videos, Guides, My Account), not a Cleaning dropdown. The housekeeper view has no admin-prefixed links, Guest Admin links, Cleaning Dashboard, or other dashboard links.
- Restricted the Cleaning management collapsible to admin/owner/company roles. Guest Admin Settings and its submenu are admin-only, consistent with the settings authorization requirement. Property and Settings submenus now render below their parent rows rather than inside the side-by-side flex row.
- Extended role and markup assertions in `UnifiedNavigationTest`. Focused suite passed: 3 tests, 135 assertions. PHP syntax checks and `git diff --check` passed.
- Manual viewport and browser checks remain pending as listed in `manualtest.md`.
- Files touched: `/home/soarersavannah/Guesthub.us/config/navigation.php`, `/home/soarersavannah/Guesthub.us/app/Support/Navigation.php`, `/home/soarersavannah/Guesthub.us/resources/views/layouts/partials/navigation-item.blade.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/UnifiedNavigationTest.php`, `/home/soarersavannah/Guesthub.us/TASKS_D.md`, and `/home/soarersavannah/Guesthub.us/notes.md`.
- Follow-up to this entry: added explicit `role:admin,owner,company` middleware to the Guest Admin dashboard route so cleaner roles cannot open it directly; extended the feature test to assert the 403 response. This is limited to that dashboard route and does not change other role definitions or permissions.

## [task-D002-admin-cleaning-menu] 2026-09-28 14:52
- Corrected the admin-side Cleaning menu: the collapsed `Cleaning` group now contains only management/admin cleaning routes (Cleaning Dashboard, Jobs, New Job, Cleaning Properties, Rooms, Tasks, Video Library, and reports). Cleaner-facing My Jobs, Calendar, Sessions, Training, Photos, Videos, Guides, and My Account are no longer rendered inside the admin group.
- Housekeepers retain those cleaner-facing links as standalone navigation items. Admins retain the Cleaning collapsible itself, but do not see the housekeeper-only links.
- Updated `UnifiedNavigationTest` to assert the admin menu excludes cleaner links while housekeepers retain them. Focused validation passed: 4 tests, 147 assertions; Blade view cache/clear passed.
- D002 is marked done again. Manual browser checks remain listed in `manualtest.md` and were not claimed as completed.

## [task-D002-admin-cleaner-link-exclusion] 2026-09-28 15:00
- Added explicit `exclude_roles: ['admin']` handling to the navigation resolver for the standalone cleaner links. This prevents those links from appearing for an administrator account that also happens to carry the `housekeeper` role.
- The admin sidebar now exposes only the `Cleaning` collapsible for cleaning navigation; housekeepers retain the standalone cleaner links.
- Added a multi-role regression test. `UnifiedNavigationTest` passed: 4 tests, 155 assertions. PHP lint and `git diff --check` passed.
## [task-D003] 2026-09-28 15:10
- Added `App\Support\Branding` color helpers and `resources/views/layouts/partials/brand-vars.blade.php` as the shared theme/brand source.
- Wired shared variables through the unified shell, guest portal, Cleaning guest shell, login page, reports, public early-access page, vendor mail layout, and shared Cleaning modal controls.
- Made `theme_color` canonical while synchronizing the retained `brand_color` compatibility key in both settings update controllers; removed the duplicate Brand color field from the admin settings form.
- Converted shared primary/accent buttons, guest portal primary controls, focus states, selected badges, icon chips, sidebar surfaces, and shared modal controls to CSS variables. Semantic success/danger/warning/info colors remain separate variables.
- Raw button patterns converted: shared `.btn-primary`/`.btn-accent`, guest primary controls, Cleaning modal save/upload/next buttons, and email primary buttons. Intentionally semantic green/red/amber controls and page-specific blue status styles were left unchanged.
- No base email layout existed outside Laravel's vendor mail layout; the vendor HTML layout now injects the configured primary button color and readable contrast text.
- Validation: `SharedThemeInitializationTest` passed (5 tests, 28 assertions); Blade view cache/clear passed; Vite build passed; `git diff --check` passed. Browser screenshot verification for extreme colors remains pending.
- Screenshot script attempt: `node scripts/capture-screenshots.js --theme=light --theme=dark` was blocked because Laravel was not responding at `http://127.0.0.1:8003`; no screenshots are claimed.

## [task-D003-followup] 2026-09-28 15:28
- Removed the configured site name text beneath the sidebar logo in the unified shell.
- Restored the original GuestHub defaults: navigation/brand `#082b49` and primary buttons `#0b2d4d`.
- Added an admin Settings “Reset to default colors” action that resets brand/theme, primary, success, danger, warning, and info colors while keeping `brand_color` synchronized.
- Validation: `SharedThemeInitializationTest` passed (6 tests, 32 assertions); PHP syntax, Blade compilation, and whitespace checks passed.
- Fixed the reset control submitting as `PUT` by moving it outside the main settings `PUT` form into its own `POST` form. Revalidated the same test suite, Blade compilation, and whitespace checks.

## [task-D005] 2026-09-28 16:25
- Added `App\Services\UnifiedActivityFeed`, which normalizes `activity_logs` (Guest Portal) and Spatie `activity_log` (Cleaning Ops) with `UNION ALL`, outer filtering, combined pagination, and timestamp/id ordering.
- Reworked the canonical admin activity log to support source, actor, module, severity, property, subject, date, and text filters. Added source-aware detail routes at `admin/logs/{source}/{id}`.
- Kept the legacy numeric admin log URL as a redirect and changed `/activity` into a redirect to the canonical feed. Existing activity writers were not changed, and neither activity table was modified.
- Preserved authorization: admin/manager can view the full feed; owners can view only entries associated with their owned properties; other roles remain forbidden. Navigation now has one canonical Activity Logs entry.
- Audit table: `activity_logs` → `ActivityLogService` and portal controllers → editable through existing writers; `activity_log` → Spatie `activity()` writers and Cleaning model events → editable through existing writers; normalized fields are read-only in the unified feed. Internal payload fields remain source-specific detail data.
- Performance note: the current local database contains approximately 330 portal rows and 0 Cleaning Ops rows at inspection time. The Spatie table has no `created_at` index; no migration or index change was added.
- Files touched: `app/Services/UnifiedActivityFeed.php`, `app/Http/Controllers/Admin/LogController.php`, `app/Http/Controllers/ActivityController.php`, `resources/views/admin/logs/index.blade.php`, `resources/views/admin/logs/show.blade.php`, `resources/views/admin/users/show.blade.php`, `resources/views/layouts/unified.blade.php`, `config/navigation.php`, `routes/web.php`, and `tests/Feature/UnifiedActivityFeedTest.php`.
- Validation: unified activity, navigation, and settings suites passed: 12 tests, 175 assertions. Blade compilation, route inspection, PHP lint, and `git diff --check` passed.

## [task-D006] 2026-09-28 16:35
- Inventory before implementation: the admin form contains name, slug, address/city/state/ZIP, contact phone/email, guest portal copy, header image, active/vehicle-photo flags, timezone, check-in/out, deposit/hold, Channex, lockbox, parking and early/late checkout rates, maps, and smart locks. The Cleaning forms additionally contain owner assignment, photo upload/remove, latitude/longitude, GPS radius, generic iCal, Airbnb iCal, Vrbo iCal, and optional default-room attachment.
- Validation union: `Admin\\PropertyController::validated()` handles the root guest-portal/rates fields; `PropertyStoreRequest` handles Cleaning GPS radius, photo, generic/Airbnb/Vrbo iCal, timezone, owner assignment, and room attachment. Existing `PropertyController` Cleaning routes also manage room/task/property-task/notification subresources.
- Naming collision recorded: `active` is the canonical property status field; `ical_url` remains distinct from `airbnb_ical_url`; `vrbo_ical_url` remains separate.

## [task-D006] 2026-09-28 17:10
- Canonicalized the property experience around `admin.properties.index` and `admin.properties.form`.
- Added Cleaning ownership, photo, GPS radius, generic/Airbnb/Vrbo iCal, and Guest Portal copy fields to the admin property form; preserved `active` and all existing iCal columns.
- Added property counts, owner display, Guide/Availability/Rooms/Tasks/Notifications/Activity actions, activation/deactivation actions, and a checkbox-driven duplicate dialog for Guest Portal content, Rooms & Tasks, Property Tasks, and notification recipients.
- Added the property notification-recipient relation without changing schema.
- Legacy `properties.index/create/edit/show` now redirect to the admin equivalents. Existing nested `properties.rooms.*`, `properties.tasks.*`, `properties.property-tasks.*`, notification, API, and ordering routes remain unchanged.
- Removed the duplicate Cleaning Properties/Add Cleaning Property navigation entries; the shared Properties entry is now the only property list entry.
- Added `UnifiedPropertyExperienceTest` and updated navigation expectations for the canonical property route.
- Validation: PHP lint, Blade cache, route inspection, `git diff --check`, and the D006/navigation suite passed: 7 tests, 166 assertions. The pre-existing `PropertyInactiveTest` remains unable to boot because it references the missing `SetupRolesAndPermissionsSeeder`.

## [task-D006-followup] 2026-09-28 16:43
- Moved Rooms, Tasks, and Notifications from the property card action row into a Property actions dropdown.
- Removed the unused Guest Portal message section and its tab link from the unified property form. Existing stored columns and backend compatibility fields remain unchanged.
- Validation: Blade cache, PHP lint, `git diff --check`, and the D006/navigation suite passed: 7 tests, 166 assertions.

## [task-D006-followup-2] 2026-09-28 16:45
- Corrected the property navigation layout per user direction: the property list now keeps only Edit, Duplicate, and Delete actions.
- Restored Guide and Availability to the per-property navigation dropdown and added Rooms, Tasks, and Notifications there as well.
- Removed the temporary Property actions dropdown and Activate/Deactivate controls from the property list.
- Validation: Blade cache, `git diff --check`, and the D006/navigation suite passed: 7 tests, 166 assertions.

## [task-D006-followup-3] 2026-09-28 16:48
- Removed the unused Property Tools/property actions section from the property edit page, including its tab and Activity card.

## [task-D004] 2026-09-28 16:10
- Restored the Cleaning Ops settings to the admin Settings page, including application icon, logo alignment, semantic button colors, autosave, instruction-viewing requirements, paging, date/time formats, timezone, and report colors.
- Kept Notifications and Legal on their existing dedicated pages per the user's clarification. Added the live global Cleaning notification toggles to the existing Notifications page and persisted them through `Setting::putValue()`.
- Preserved the existing admin-only authorization boundary; non-admin settings access remains forbidden.
- Settings audit: Cleaning fields are editable on `admin.settings.edit`; global notification toggles and guest alert templates are editable on `admin.settings.notifications.edit`; legal content and labels remain editable on `admin.settings.legal.edit`; internal-only keys such as PMS sync/version metadata remain without UI.
- Files touched: `app/Http/Controllers/Admin/SettingsController.php`, `app/Http/Controllers/Admin/NotificationSettingsController.php`, `resources/views/admin/settings.blade.php`, `resources/views/admin/notifications/index.blade.php`, `config/navigation.php`, and `tests/Feature/AdminSettingsNavigationTest.php`.
- Validation: `AdminSettingsNavigationTest` passed (4 tests, 9 assertions); PHP lint, Blade view cache compilation, and `git diff --check` passed.

## [task-D007] 2026-09-30 10:46
- Continued dashboard unification, configured branded email/auth surfaces and naming, and applied property visibility authorization consistently to property notification page/API actions. Added dashboard role-panel and owner-scope notification tests.
- Files touched include `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/PropertyNotificationSettingsController.php`, `app/Services/GuestPortalDashboardData.php`, `app/Services/UnifiedActivityFeed.php`, `app/Support/Branding.php`, `config/app.php`, `config/navigation.php`, `routes/web.php`, dashboard/auth/email/guest/public/settings/report views, and `tests/Feature/AdminSettingsNavigationTest.php` and `tests/Feature/PropertyNotificationTest.php`.
- Dashboard tests passed in Docker: 2 tests. Property notification page/settings/add-recipient tests returned unexpected 302 redirects for admin and owner users; their destination was not diagnosed before stopping. The same focused run also showed three existing SMS notification tests fail because `App\Helpers\TimezoneHelper` cannot be loaded.
- Docker is available; validation used `docker compose run --rm --no-deps app` and PHPUnit's configured in-memory SQLite database. No migration or production database command was run.
- Blade cache, route inspection, and the full relevant test file were not completed because the focused feature run failed. The remaining duplication findings and approved `.env.example` product name decision are also pending; `.env.example` was left unchanged because the client-approved name is not known.

## [task-D007-login-brand-removal] 2026-09-30 11:02
- Removed the visible configured site-name text and product-name title from the login page; retained the logo and its accessible alt text.
- Files touched: `resources/views/auth/login.blade.php`.
- Verified Blade view cache compilation and clear in Docker. D007 remains blocked by its previously recorded blocker.

## [task-D007-completion] 2026-09-30 11:46
- Restored `TimezoneHelper` from the deleted historical implementation and added conversion/formatting unit coverage. Confirmed the notification 302s were caused by test users using the factory's weak default password and missing active status; updated the fixtures without changing auth or role logic.
- Confirmed the role-seeding migration creates the current seven roles. Removed references to the nonexistent `SetupRolesAndPermissionsSeeder` from the three legacy feature tests; no remaining references exist in tests or migrations. GPS override/schedule tests pass.
- Finished the remaining D007 naming surfaces: agreement and session-report fallback names use `Branding::siteName()`, agreement/welcome titles use the configured name, and public legal/contact/privacy views load shared brand variables. All six email views extend `emails.layouts.base`; Mailable classes use Markdown views and none build HTML inline.
- Added assertions for cross-links and notification wording. Updated stale navigation assertions to reflect the required Training group and the merged Account section; no role definitions or permission checks were changed.
- Dedupe audit: no functionally identical component pairs were found; dropdown/action-dropdown and the specialized modal/form components have distinct contracts. The session flash banners and Cleaning toast queue have different display/trigger behavior. `app.js` and `cleaning-app.js` have distinct responsibilities, and there are no duplicate pagination views or paginator overrides. No consolidation was warranted.
- The approved product name remains unknown, so `.env.example` was left unchanged. Visible sidebar site-name text remains absent per the user's prior direction; the logo remains.
- Focused D007/role-seeding validation passed: 24 tests, 218 assertions. Blade view cache/clear, PHP lint, and `git diff --check` passed.
- Separate legacy acceptance findings to resolve or explain in D008: `CleanerTerminationTest` still exercises missing `User::deactivate()`/`reactivate()` methods and stale inactive-login expectations; `PropertyInactiveTest` calls missing `Property::deactivate()`/`activate()` methods and expects content from legacy `/properties` routes that now redirect. These do not indicate a remaining seeder failure. Manual role click-through and theme screenshots are also pending D008.
- Files touched: `/home/soarersavannah/Guesthub.us/app/Helpers/TimezoneHelper.php`, `/home/soarersavannah/Guesthub.us/app/Models/User.php`, `/home/soarersavannah/Guesthub.us/app/Services/GuestAlertService.php`, `/home/soarersavannah/Guesthub.us/app/Services/RentalAgreementService.php`, `/home/soarersavannah/Guesthub.us/app/Http/Controllers/SessionReportController.php`, `/home/soarersavannah/Guesthub.us/resources/views/agreements/rental-agreement.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/public/contact.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/public/legal-page.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/public/privacy-request.blade.php`, `/home/soarersavannah/Guesthub.us/resources/views/welcome.blade.php`, `/home/soarersavannah/Guesthub.us/tests/Unit/TimezoneHelperTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/AdminSettingsNavigationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/CleanerTerminationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/GpsOverrideAndScheduleTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/PropertyInactiveTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/PropertyNotificationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/UnifiedNavigationTest.php`, and `/home/soarersavannah/Guesthub.us/TASKS_D.md`.

## [task-D008-blocked] 2026-09-30 12:14
- Stopped acceptance testing after discovering that Docker injects `DB_DATABASE=guesthub`, while `phpunit.xml` sets `DB_DATABASE=:memory:` without `force="true"`. PHPUnit therefore appears to have used a SQLite database named `guesthub`; test failures also reported `Database: guesthub`, migrations-table errors, and a malformed database during concurrent focused runs. The root `guesthub` artifact was already dirty at pickup and is explicitly out of scope. Its contents were not inspected or changed intentionally; I cannot establish whether test-run migrations altered it. Do not run more tests, inspect, reset, or clean that artifact until a human confirms a safe test database configuration.
- Correction to prior D007 validation notes: their PHPUnit results were reported as in-memory SQLite, but that assumption is not reliable with the Docker environment override. The reported focused test results must be rerun only after the test environment explicitly forces a throwaway database.
- The full-suite attempt showed 134 passing and 57 failing markers, but this is not a valid acceptance result and is not treated as a trustworthy pass/fail count. No direct `php artisan migrate` command was run.
- Non-database checks completed: `npm run build` passed using installed host Node dependencies; `view:cache` passed; `route:list` found 327 routes, no overlapping method/URI registrations after making legacy settings/users redirects GET-only and removing duplicate registrations, and no missing app controller files. Static scans found no `prefers-color-scheme`, `components.sidebar`, or `x-sidebar` references after removing the unused generated stylesheet from `welcome.blade.php`.
- Screenshot attempt: `/tmp/d008-screenshots/001-light-admin-login-page-desktop.png` was captured, but the screenshot script could not authenticate with its default preview credentials. No valid admin credentials were available, so the six-page/theme/brand matrix and seven-role browser click-through remain unverified. README was left unchanged as D008 directs.
- D008 is blocked pending a human-approved test DB override and valid local preview access; no further acceptance work was run.
- Files touched: `/home/soarersavannah/Guesthub.us/routes/web.php`, `/home/soarersavannah/Guesthub.us/resources/views/welcome.blade.php`, `/home/soarersavannah/Guesthub.us/TASKS_D.md`, and `/home/soarersavannah/Guesthub.us/notes.md`.

## [task-D008] 2026-09-30 12:35
- Resumed D008 after the user approved explicitly forcing PHPUnit to use in-memory SQLite; added `CACHE_STORE=array` to the test commands as well. No `php artisan migrate` was run directly, and no real database was targeted.
- Updated authentication fixtures/expectations to match the current app (disabled public registration, login redirect, active users with strong passwords). Added user/property activation methods and related attributes used by existing controllers, rejected login for inactive accounts, and adjusted stale report/charge/extractor test expectations and HTTP fakes.
- The auth/profile subset passed once (23 tests, 59 assertions) before later model/factory changes. An expanded subset later reported 15 failures and 64 passes. I updated the factory defaults and one charge fixture after that run, but those final edits were not retested.
- The full suite, final build/view/route checks, seven-role click-through, and theme/brand matrix were not completed. The browser checks remain for the user, as previously recorded in `manualtest.md`.
- At the user's direction, stopped D008 for manual completion and marked it blocked. Do not treat this as an acceptance pass; the latest D008 changes remain unverified.
- Files touched: `/home/soarersavannah/Guesthub.us/app/Http/Controllers/AuthController.php`, `/home/soarersavannah/Guesthub.us/app/Http/Middleware/EnsureUserIsActive.php`, `/home/soarersavannah/Guesthub.us/app/Models/Property.php`, `/home/soarersavannah/Guesthub.us/app/Models/User.php`, `/home/soarersavannah/Guesthub.us/database/factories/UserFactory.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/Auth/AuthenticationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/Auth/EmailVerificationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/Auth/PasswordConfirmationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/Auth/PasswordUpdateTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/Auth/RegistrationTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/ProfileTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/PropertyInactiveTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/PreCheckinChargeTest.php`, `/home/soarersavannah/Guesthub.us/tests/Unit/IdDocumentExtractorTest.php`, `/home/soarersavannah/Guesthub.us/tests/Unit/Services/ReportItemFilterTest.php`, `/home/soarersavannah/Guesthub.us/TASKS_D.md`, and `/home/soarersavannah/Guesthub.us/notes.md`.

## [task-D008] 2026-09-30 12:46
- Re-ran the complete suite with `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, and `CACHE_STORE=array`: 187 passed, 4 failed (191 total). Failures: settings authorization test expected 403 but got 200; both instructional-video creation tests fail because `FFMpeg\Format\Video\X264` is unavailable; pre-check-in charge test expected 5000 cents but received 3000.
- Fixed deposit intent gating so card-disabled/already-paid bookings are rejected before contacting Stripe; corrected the test to use the model's current `success` status and explicitly clear Stripe configuration in the unavailable-Stripe test. `PayByCcGatingTest` now passes (8 tests).
- Final automated checks: Blade cache passed; route audit found 327 routes with no duplicate method/URI pairs or missing app controllers; resource scans found no `prefers-color-scheme`, `components.sidebar`, or `x-sidebar`; frontend production build passed; `git diff --check` passed.
- Remaining: resolve or document the four failing test cases, then complete the seven-role browser walkthrough and six-page light/dark × three-brand-color screenshot matrix. The user's browser pass remains pending; D008 is not complete.
- No Composer/npm packages were installed. No test targeted the root `guesthub` artifact. `README.md` and `guesthub` were already modified in the worktree and were left untouched; the asset build regenerated `public/build` outputs.
- Files touched in this continuation: `/home/soarersavannah/Guesthub.us/app/Http/Controllers/GuestController.php`, `/home/soarersavannah/Guesthub.us/resources/views/welcome.blade.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/PayByCcGatingTest.php`, `/home/soarersavannah/Guesthub.us/tests/Feature/PreCheckinChargeTest.php`, `/home/soarersavannah/Guesthub.us/TASKS_D.md`, and `/home/soarersavannah/Guesthub.us/notes.md`.
