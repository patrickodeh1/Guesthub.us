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
