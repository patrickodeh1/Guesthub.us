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
