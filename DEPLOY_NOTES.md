# Deploy notes: notifications, SMS and cleaning flow

Commands run as `docker compose exec app php artisan ...` on the prod host.
Message wording and toggles live in the DATABASE, so they do not arrive with the code.

## A. Before deploying (manual, one-time)
1. Prod .env: confirm SMS_ENABLED=true, TELNYX_API_KEY, TELNYX_FROM_NUMBER.
2. Prod .env: set TELNYX_PUBLIC_KEY (the inbound webhook accepts unsigned requests without it, and cleaner YES/NO replies depend on this webhook).
3. Telnyx portal: the messaging profile's inbound webhook URL must point at the prod TelnyxWebhookController route.
4. Confirm the scheduler runs in prod (schedule:run every minute, or schedule:work). The registration-reminder, arrival-soon and cleaning-sync commands depend on it.

## B. Deploy steps, in this order
1. Pull the code, then: `php artisan migrate`
   Adds: properties.guest_name / internal_name; bookings.last_registration_reminder_at / arrival_alert_sent_at / unit_ready_sent_at;
   cleaning_sessions.booking_id / no_guest / assignment_status / cleaner_notified_at; housekeeper_id becomes nullable.
2. `php artisan notifications:apply-client-defaults --dry-run`  (preview)
3. `php artisan notifications:apply-client-defaults`
   Backs up the old config to storage/app/guest_alerts_config_backup_*.json, then applies the client's matrix and wording.
4. `php artisan cleaning:sync-checkout-sessions`
   Creates one unassigned checkout clean per upcoming booking. Run once; the scheduler then repeats it hourly.
   Sends NO texts (no cleaner is assigned yet).
5. `php artisan optimize:clear`
   (If prod caches config/routes, run config:cache and route:cache afterwards.)

## C. After deploy: manual actions in the admin UI
1. Properties: set Guest name (shown to guests) and Internal name (shown to admin/cleaners) for every property. Both start equal to Name.
2. Settings > Notifications: read through the new wording and adjust it. Confirm that only the intended events are checked.
   Confirm "Admin" recipients (contact desk, admin, owner) are the people he wants.
3. Every cleaner user needs a phone number, or assignment texts and YES/NO replies cannot work.
4. Each property's Notifications page: add the owner/manager phone numbers as recipients. Cleaner arrived / almost finished / complete texts go to these numbers.
5. Dashboard: confirm the new unassigned checkout cleans appear as needing a cleaner.

## D. Smoke test on prod (use a test property or booking)
1. Assign a cleaner, then check they get the YES/NO text.
2. Reply YES and check that the session shows as confirmed. Reply NO on another and check that it returns to unassigned and the admin gets a text.
3. Start, advance to the photos stage, and finish a session, and check the three owner texts arrive.
4. Same-day check-in: finish the cleaning and check that the guest gets "unit ready" once only.
5. Upload an ID, decline it, re-upload it, and check that the admin gets the re-upload alert.

## Rollback
- Message config: GuestAlertService::putConfig(json_decode(file_get_contents(<backup file>), true)) via tinker.
- Migrations: php artisan migrate:rollback --step=3 (note that dropping booking_id etc. loses guest tags).

## Known limits
- Reservation-updated events share identical wording, so two firing together send two texts.
- SMS links are raw URLs. Clickable link text works only in email, and that is not built yet.
- No guest-tag dropdown in the UI yet (route exists: cleaning-sessions.guest.update).

## Update: reservation_updated and background-check wording
- Early check-in, late check-out and requested-time approve/deny now send ONE `reservation_updated` text.
  The six old events are turned off by `notifications:apply-client-defaults` (deploy step B3).
- Background-check message now uses {result}; it is "approved" on the existing mark-complete button.
- Admin session page has a "Guest for this cleaning" dropdown (reassign guest / No guest).
