# GuestHub: AI pickup note

## Current branch state (2026-11-13) — supersedes older notes below where they conflict

- Active implementation branch: `feature/complete-client-scope`. Do not commit this work to `main`; the user is making independent fixes on `main`. Merge latest `main` into this branch later, resolve conflicts, test, then wait for the user's approval before merging.
- Inventory has been added on this branch. New migrations:
  - `2026_11_13_100000_create_property_inventory_tables.php`
  - `2026_11_13_110000_create_inventory_session_records.php`
  - `2026_11_13_120000_add_supplier_to_inventory_movements.php`
  - `2026_11_13_130000_add_inventory_low_stock_alert_state.php`
- Inventory includes property-scoped items, locations, balances, receiving, transfers, fractional quantities, supplier/purchase history, item photos/editing, waste/theft movements, low-stock display, cleaner session stock counts and a usage report by stay/cleaner. `inventory:send-low-stock-alerts` emails property owners once per low-stock episode and re-arms after recovery; migration, scheduler and alert tests are committed.
- Inventory tests: `tests/Feature/InventoryStockServiceTest.php`, `InventorySessionWorkflowTest.php`, and `InventoryLowStockAlertTest.php`. No local migration, lint, test or UI result has yet been confirmed. Do not mark tracker 56 done. Automated purchase-order/reorder workflow and full UI verification remain open.
- Host Plan and Stripe billing code now exists: `app/Http/Controllers/Admin/PlanController.php`, `app/Services/Billing/BillingService.php`, `PlanPricing.php`, `SubscriptionSync.php`, and `resources/views/admin/plan/index.blade.php`. The platform webhook dispatches subscription events while retaining guest PaymentIntent finalization. Do not follow older statements below saying the Plan page or billing code is not written. Stripe test-mode trial, automatic charge, failed-payment and portal flows still need end-to-end verification.
- Host Plan add-on quantities are resolved server-side: `per_property` uses the host's active visible properties; `per_device` uses registered `PropertyLock` records with a connected Seam device ID (current device scope = smart locks). `billing:sync-resource-quantities` runs hourly and updates Stripe subscription line quantities with `proration_behavior=none`, applying count changes to the next billing cycle. Tests: `tests/Feature/BillingQuantityResolverTest.php` and `SyncBillingResourceQuantitiesTest.php`; verify with Stripe test mode locally.
- Direct-booking checkout now calls the existing public quote endpoint and shows a live line-item and total breakdown as dates, guest count or payment method change. The public listing page already has the photo lightbox/gallery and availability quote experience.
- Hosts can now copy an iframe snippet from the published listing editor to embed a compact booking widget on their own website. The widget shows live availability/pricing and redirects the guest to top-level GuestHub checkout before collecting personal details or payment, avoiding third-party iframe cookie/CSRF issues. The normal GuestHub public listing remains available separately. Channex provider tests cover booking revision/OTA normalization and availability push payloads (`tests/Feature/ChannexProviderTest.php`).
- Client API credential ownership is still an open design choice pending James's reply. Preserve support for both GuestHub-managed credentials and host-managed credentials; do not force one model.


Read this first. It replaces the full chat history. Keep work in small steps.

## Setup
- App: Laravel in Docker at `~/Guesthub.us`. Run commands with `docker compose exec app php artisan ...`.
- Local site: http://localhost:8001 (`.env` APP_URL says 8000, local only).
- Client tracker: Django app at `~/soarer-blog` (container `web`). Client is James. Tasks have a status: todo, doing, done ("please verify"), needs_fix, accepted.
- Mark a task from `~/soarer-blog`:
  `docker compose exec -T web python manage.py shell -c "from tracker.models import Task; Task.objects.filter(id=ID).update(status='done', how_to_test='...')"`

## How we work
1. Never patch a file you have not seen. Ask for it with a read-only command (`cat`, `sed -n`, `grep -n`).
2. Back up to `/tmp/bk` before editing.
3. Patch with a Python script that asserts each anchor matches exactly once.
4. Lint (`php -l`), then `route:clear` and `view:clear`.
5. Say what is verified and what is not. Do not claim a result you have not seen.
6. Guest-facing text never mentions Stripe or how payments are processed.
7. SEO copy must not promise rankings.

## Done and verified by the user
- Free direct booking toggle (Features page). Tracker id 81.
- Payment methods: card, processing fee, manual (Zelle/Venmo/PayPal) with instructions. id 41.
- Lease application: ID scan (barcode and passport), read-only scanned fields, employer, consent, fee, host ID review page with approve/decline per side. id 40.
- SEO and AI visibility: sitemap, robots (static `public/robots.txt` was removed so the dynamic route serves), llms.txt, JSON-LD, noindex on parameter URLs, 404 on unpublished. id 42, 74.
- Direct booking link, guest portal, host alerts. id 8.

## Decisions made
- Host/staff cancel only cancels. Refunds are separate: Refund panel on the admin booking page, any amount up to the remaining balance. Paid cancelled bookings stay unarchived with `refund_status = awaiting_decision` until refunded in full or "Close refund".
- Guests cannot cancel on the site. The page says to contact us.
- Lease form has no vehicles. The screening link was removed.
- Lease approval is blocked until every ID side is approved.
- `refund_processing_fee` setting (Features page) decides whether the card fee is refunded.
- Notification templates are editable on the admin notifications page. Added: guest_refund_issued, host_card_paid.

## Open work (in order)
1. Packages, a la carte, free trial days, automatic billing after trial. Tracker ids 80 and 58. James: tiers step up (basic, then enhanced = basic plus more), renamable, comparable, per-property and per-device pricing, hosts link their own API keys. Extend the existing Features page. Billing on the platform account is unknown: check for Stripe Billing or Cashier first.
2. Inventory basics. id 56. Categories: high value (serial and photo), cleaning supply, guest supply, other. Fields: unit of measure, location, price, vendor, barcode, quantity on hand, last ordered. Cleaner records what was taken from the closet and what is left, including partial amounts. Low-stock list and usage report per stay and per cleaner. Auto-reorder later.
3. Check older tracker rows that may be done: 7 (guest-cleaning link, nav), 21 and 71 (package room, lockbox photo steps), 76 (roles).
4. Small items: cleaning session status rejects `cancelled`; `pms:sync` fails in the Channex provider; application fee refund button; declined lease ID cannot re-upload; remove dead code and the `-copy-` test listings.

## Unverified
- PriceLabs, Wheelhouse, iCal import; whether synced rates set the nightly price (test rate precedence by writing a rate row in tinker).
- Full test suite had 48 unattributed failures before this session.
- Refund email variable format (`renderGeneric`), host-paid email, live Stripe Connect, real-ID phone test, counsel review of lease wording.
- Lease next step after approval: waiting on James (rental contract vs convert to booking).

## Deploy
See `DEPLOY_NOTES.md`: migrate, clear caches, delete `public/robots.txt`, set real `APP_URL`, set live keys, run scheduler and queue, submit sitemap.

## How the user works (read this)
- The user runs every command and pastes the output back. You have no shell on their machine, so never say a command ran unless you saw its output.
- They want small steps, fast. One piece at a time, each ending with a test they can run and a tracker mark command.
- They paste long terminal output. If a paste is cut off, ask for the missing part instead of guessing.
- They test in the UI themselves. Tell them what to click and what they should see.
- Do not claim something works unless you saw it work. Say "built" or "unverified" when that is the truth.
- Keep messages short. Plain words. No marketing language.
- Tracker statuses are set by the user running the mark command. Give them the command, do not assume it ran.

## First thing to do in a new session
Verify these docs against the repo before trusting them, and report anything that does not match:
`git log --oneline | head -10`
`docker compose exec app php artisan route:list --name=packages`
`docker compose exec app php artisan migrate:status | tail -8`

## File map (seen in earlier sessions, verify with grep)
- Routes: `routes/web.php` (admin and webhooks), `routes/public.php` (public pages). Menu: `config/navigation.php`.
- Direct booking: `app/Services/DirectBookingService.php` (start, confirmCardPayment, markManualPaymentReceived, cancel, notifyStarted, notifyHost, notifyHostPaid, notifyGuest), `app/Http/Controllers/DirectBookingController.php`, `app/Http/Controllers/Admin/DirectBookingAdminController.php` (cancel, refund, resolveRefund), `app/Http/Controllers/Admin/BookingController.php`.
- Payments: `app/Services/Payments/PaymentService.php` (Stripe client, refund). Webhooks: `StripeWebhookController` (platform, `/webhooks/stripe`), `StripeConnectController` (Connect, `/webhooks/stripe/connect`).
- Emails and texts: `app/Services/DirectBookingMessages.php` (editable templates, `render`, `renderGeneric`, `renderLease`), `EmailNotificationService::sendDirectBooking`, admin page `resources/views/admin/notifications/index.blade.php`.
- Cancellation and refunds: `app/Services/CancellationPolicy.php`, `DirectBookingCancellationPolicy.php`, view `resources/views/admin/bookings/partials/direct-refund.blade.php`.
- Lease: `app/Http/Controllers/LeaseApplicationController.php`, `Admin/LeaseApplicationAdminController.php`, views `public/lease-application-fee.blade.php` and `admin/lease-applications/show.blade.php`, script `public/js/lease-id-capture.js`.
- Public site and SEO: `app/Http/Controllers/PublicListingController.php` (browse, sitemap, robots, llms.txt), layout `resources/views/layouts/public.blade.php`.
- Guest pages: `resources/views/public/checkout-status.blade.php`, `resources/views/guest/show.blade.php` (very large, read only the range you need).
- Features and packages: `FeatureGate`, models `Feature`, `HostFeature`, `Package`, `HostPackage`; controllers `Admin/FeatureController`, `PackageController`, `HostPackageController`; views `admin/features`, `admin/packages`, `admin/host-packages`.
- Logging: `ActivityLogService::admin(action, text, area, [context])`.
- UI: layout `<x-admin-layout title="...">`; classes `page-header`, `page-title`, `eyebrow`, `page-subtitle`, `card card-pad`, `field-label`, `input`, `btn-primary`, `btn-secondary`, `section-title`; Tailwind utilities are available.

## Patch template (copy this pattern)
```bash
cd ~/Guesthub.us
mkdir -p /tmp/bk
cp path/to/file.php /tmp/bk/file.step.bak
python3 - <<'PY'
def one(s, old, new, label):
    assert s.count(old) == 1, f'{label}: expected 1 match, got {s.count(old)}'
    return s.replace(old, new, 1)
p = 'path/to/file.php'
s = open(p).read()
s = one(s, "exact old text", "new text", 'what this edit is')
open(p, 'w').write(s)
print('patched')
PY
if docker compose exec app php -l path/to/file.php 2>&1 | grep -q "No syntax errors"; then
  docker compose exec app php artisan route:clear
  docker compose exec app php artisan view:clear
else
  cp /tmp/bk/file.step.bak path/to/file.php
  echo "RESTORED backup"
fi
```
Rules: read the file first. One exact-match anchor per edit. Back up, lint, restore on failure. Rolled-back tinker tests (`DB::beginTransaction()` ... `DB::rollBack()` in a `finally`) are the safe way to prove logic.

## Reading James's exact words for a task
```bash
cd ~/soarer-blog
docker compose exec -T web python manage.py shell -c "from tracker.models import Task; t=Task.objects.get(id=ID); print(t.title); print(t.source_message); [print('COMMENT', c.body) for c in t.comments.all()]"
```
Use his wording, not a summary, when deciding what "done" means.
