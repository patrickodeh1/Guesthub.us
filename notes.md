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
