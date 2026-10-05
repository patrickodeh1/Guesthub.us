# Deploy notes: notifications & SMS

Run these on PROD after deploying this branch. The message list and toggles live in the
database (Settings), so they do NOT arrive with the code.

1. `docker compose exec app php artisan migrate`
   (adds properties.guest_name / internal_name, bookings.last_registration_reminder_at / arrival_alert_sent_at)
2. `docker compose exec app php artisan notifications:apply-client-defaults --dry-run`
3. `docker compose exec app php artisan notifications:apply-client-defaults`
   (backs up the old config to storage/app/guest_alerts_config_backup_*.json first)
4. Open Settings > Notifications and confirm the wording; edit if the client wants changes.
5. Properties: fill in Guest name and Internal name for each property (backfilled from Name, so check them).
6. Confirm the scheduler is running in prod (`schedule:run` every minute / `schedule:work`), because the registration-reminder and arrival-soon commands depend on it.
7. Set TELNYX_PUBLIC_KEY in prod .env before enabling the cleaner YES/NO replies (webhook is unsigned without it).

Rollback of message config: restore the backup JSON via GuestAlertService::putConfig(json_decode(..., true)).
