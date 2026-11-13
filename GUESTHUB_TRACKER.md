# GuestHub tracker

Living list. Update the status column and the log when you finish a step. Read `GUESTHUB_AI_HANDOFF.md` first for setup and working rules.

Client tracker (James sees this): `~/soarer-blog`, Django app, container `web`.
Mark a task: `docker compose exec -T web python manage.py shell -c "from tracker.models import Task; Task.objects.filter(id=ID).update(status='done', how_to_test='...')"`
Statuses: todo, doing, done (please verify), needs_fix, accepted. Only James sets accepted.

## The 7 original tasks

| Tracker id | Task | Status | Left |
|---|---|---|---|
| 81 | Free direct booking toggle | done | Confirm the tracker row says done |
| 41 | Multiple payment methods with fees and instructions | done | Confirm the tracker row says done |
| 40 | Short lease application (ID scan, employer, fee) | done | Real-ID phone test, counsel review of wording |
| 42 | SEO and AI visibility | done | Deploy: delete `public/robots.txt`, real `APP_URL`, submit sitemap, Rich Results Test on a live URL |
| 74 | AI search (discoverability) | done | Same as 42. Search is a destination search, not natural language |
| 8 | SEO link with contract features | done | Same as 42 |
| 80 | Booking site integration, sync, packages, trial billing | doing | See "Packages build" below |

## Related open tasks

| Tracker id | Task | Status | Notes |
|---|---|---|---|
| 58 | Tiered packages, API key linking, inventory, reorder alerts | doing | Package/Stripe foundation and host Plan page exist; property/device quantities are server-derived on subscription changes and automatically reconciled hourly. Inventory catalogue, cleaner session workflow and scheduled low-stock alerts are built on the feature branch. Published listings also expose an embeddable booking widget for a host's own website. PriceLabs/Wheelhouse already support host API keys; Channex currently uses the platform key; its booking importer preserves OTA names such as Booking.com and VRBO. Remaining: Stripe E2E, credential ownership decision, channel-account certification in Channex, and local inventory/billing verification |
| 56 | Inventory categories and fields | doing | Built on `feature/complete-client-scope`: property-scoped catalogue, item editing/photos, dedicated Tool category, locations, supplier and purchase history, stock balances, receiving/transfers, fractional cleaner counts in active sessions, guest-use estimates, waste/theft, low-stock view, per-stay/per-cleaner report, scheduled owner email alerts with recovery/re-alert logic, purchase orders and partial receiving. Admin UI redesigned into separate Overview, Items, Add item, Stock, Purchase orders and Reports sections; cleaner inventory is a mobile-first per-item restock checklist with plain-language guidance. Item forms support camera scanning of existing product barcodes on browsers implementing BarcodeDetector; barcode values remain text. No generated/printable internal asset barcode labels yet. Automated tests are added; local migration/test run, PHP/view compilation and UI verification remain |
| 7 | Guest-cleaning link on date change, nav redesign | todo | May already be done. Verify in the app |
| 21 | Package room pickup shows for Chicago when unchecked | todo | Verify. Same bug in 71 |
| 71 | Lockbox photo steps, auto-advance, step order bugs | todo | Verify |
| 76 | Roles and white-label account type | todo | 55, 57, 59 are done, so likely covered |

## Packages build (tasks 80 and 58)

Done, in order:
1. Tables: `packages`, `package_features`, `host_packages`; `features.billing_unit`; `host_features.quantity`. Migration `2026_11_11_160000_create_packages_tables`.
2. `FeatureGate`: explicit host grant wins, then free features, then the host's package (inherits from parent tier). Tested: step-up, trial running and ended, no package.
3. Admin Packages page `/admin/packages`: one Save all changes, compare table, per-feature free/price/billing unit/active. Starter tiers Basic, Pro, Enhanced (migration `..._170000_seed_starting_packages`).
4. Admin Host billing `/admin/host-packages`: pick package, status, trial end, add-on features with quantity, monthly total preview. Admin only.
5. Billing columns on `host_packages` (migration `..._180000_add_billing_to_host_packages`): billing_source (admin or stripe), stripe ids, period end, cancel flag, past_due_since. Grace period for past due: 7 days (`HostPackage::PAST_DUE_GRACE_DAYS`).

Implemented since the original package foundation:
- Host Plan page for owner/company roles: package comparison, trial selection, add-on quantities, monthly quote, subscription management and billing portal.
- `BillingService`: Stripe Checkout subscription flow, SetupIntent/subscription flow, add-on/plan updates and billing portal.
- `SubscriptionSync`: subscription-created/updated/deleted handling, Stripe-managed package/add-on sync, and protection for admin-managed package rows.
- Platform Stripe webhook dispatches subscription events while retaining existing guest PaymentIntent finalization.
- Past-due grace period remains 7 days.

Still to verify:
1. Run the plan and inventory tests locally.
2. Verify Stripe test-mode trial start, webhook activation, automatic trial-end charge, failed-payment grace, card update and cancellation with Stripe CLI/test clock.
3. Verify resource-based add-on quantities in Stripe test mode. Host selections derive per-property quantity from active visible properties and per-device quantity from registered connected smart locks (`BillingQuantityResolver`). The scheduled `billing:sync-resource-quantities` command reconciles existing Stripe subscriptions hourly with no mid-cycle proration; zero-quantity selections are retained for later reactivation.
4. Mark 80 and 58 done only after the end-to-end billing checks and James's acceptance.

## Other open items (small)

- Cleaning session status rejects `cancelled` (needs the column type: `show columns from cleaning_sessions where Field='status'`).
- `pms:sync` still needs a live-config verification run. Added `ChannexProviderTest` for booking revision normalization, OTA labels and availability push; tests do not replace a real Channex credential/account check.
- Application fee refund button for hosts is not built.
- Declined lease ID: applicant is not emailed and cannot re-upload.
- Lease next step after approval: waiting on James (rental contract vs convert to booking).
- PriceLabs, Wheelhouse and iCal import still need live provider/configuration verification. Host PriceLabs and Wheelhouse API keys are encrypted on the user model; Channex continues to use the platform credential pending James's ownership decision. Test rate precedence by writing a rate row in tinker.
- Refund email and host-paid email: confirm real values appear (not literal braces).
- Full test suite had 48 unattributed failures before this work. Run `php artisan test`.
- Remove dead code (old lease `storeId`/`retryId`, `updateDirect`, screening route, unused `PhotoIdDeclinedMail`, dead `cancel()` in `DirectBookingController`) and the six `-copy-` test listings.
- Check the test listing with a $500.00 application fee.

## Log

- 2026-11-13: Built the inventory foundation on `feature/complete-client-scope`: property-scoped item catalogue, locations and balances, receiving, transfers, usage, adjustments, waste/theft, photos, supplier/purchase history, cleaner session counts, fractional quantities, guest-use estimates and reporting. Added scheduled low-stock owner email alerts that send once per low-stock episode and re-arm after recovery. Added stock-service, cleaner session, low-stock alert and purchase-order tests; local migrations, tests and UI checks remain outstanding.
- 2026-11-13: Redesigned inventory admin into separate task-focused sections (Overview, Items, Add item, Stock, Purchase orders, Reports), with searchable item cards and clearer stock actions. Reworked cleaner inventory into mobile-first item cards with step-by-step count/replenishment explanations. Added a dedicated Tool category and progressive camera barcode scanning for existing product codes where the browser supports BarcodeDetector. Added a cleaner screen rendering test. Still requires local Blade/PHP lint, test run and visual verification; generated/printable internal asset barcode labels are not implemented.
- 2026-11-13: Added a public embeddable direct-booking widget with live quotes and a copyable iframe snippet in host listing settings; guests continue to GuestHub's top-level checkout for their details and payment. Channex import tests cover OTA names and availability push payloads.
- 2026-11-13: Verified the host Plan, Stripe BillingService, SubscriptionSync and billing portal implementation exist in the feature branch. Stripe test-mode end-to-end trial and automatic charge remain unverified.

- 2026-11-11: direct booking, lease, refund split, SEO fixes, notification templates (guest_refund_issued, host_card_paid), robots fix, packages data model and gate, admin Packages and Host billing pages, billing columns.

## Current feature-branch handoff (2026-11-13)

Work is being committed directly to remote branch `feature/complete-client-scope`; the user is continuing independent fixes on `main`. This branch is 0 commits behind main at the last comparison. Do not merge it into main until the user asks.

- Inventory: property-scoped catalogue, item editing/photos, stock locations/balances, receiving/transfers/consumption/adjustments/waste/theft, supplier and purchase history, purchase orders with partial receiving, one-click reorder from low-stock rows, cleaner fractional counts in active cleaning sessions, estimated guest usage and reporting. Scheduled owner low-stock emails suppress duplicates and re-arm after stock recovery.
- Billing: server-derived property and connected-lock quantities, with hourly Stripe subscription reconciliation and zero-quantity add-on selection retention.
- Booking: direct checkout shows live quote breakdown; published listings expose an embeddable booking widget for external websites; Channex OTA import preserves Booking.com/VRBO source labels.
- Tests added: `InventoryStockServiceTest`, `InventorySessionWorkflowTest`, `InventoryLowStockAlertTest`, `InventoryPurchaseOrderTest`, `BillingQuantityResolverTest`, `SyncBillingResourceQuantitiesTest`, `SubscriptionSyncTest`, `ChannexProviderTest`, and expanded `DirectBookingCheckoutTest`.
- No Docker migrations, PHP lint, PHPUnit run, Stripe test-mode run, or live OTA/provider verification has been observed. Do not mark related tracker tasks done yet.
- Remaining code/design work: confirm James's credential ownership preference before changing Channex from GuestHub-managed to host-managed; live supplier ordering/payment automation beyond purchase-order tracking; live Channex/PriceLabs/Wheelhouse certification; full Stripe E2E verification.

The following 2026-11-11 notes are historical and superseded where they conflict with the current branch handoff.

## Session handoff (2026-11-11, end of session)

**Doing now:** step 1 of "Next" under Packages build, the host Plan page with Stripe subscription billing. Nothing for it is written yet. Tasks 80 and 58 are "doing" in the tracker.

**State at handoff**
- Last step shipped: billing columns on `host_packages`. The migration ran. The grace-period test (`/tmp/billtest.php`) was given but its output was not seen, so rerun it and confirm all five lines match.
- Stripe is on test keys (`sk_test_`). Platform account exists (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`). Connect has its own webhook secret and route (`/webhooks/stripe/connect`). Platform webhook is `POST /webhooks/stripe` in `routes/web.php`, handled by `StripeWebhookController`. It carries guest payments, so do not break it.
- `PaymentService` builds its client with `new StripeClient(config('services.stripe.secret'))`. Reuse that pattern for billing instead of a second setup.
- `app/Services/Payments` holds only `PaymentService.php`. No billing code exists. Nothing from Cashier is installed. `stripe/stripe-php` ^17.4 is.

**First actions for the next AI**
1. Ask the user to run these and paste the output, then read before writing anything:
   `cat app/Http/Controllers/StripeWebhookController.php`
   `grep -n "fillable\|Billable\|class User\|stripe" app/Models/User.php`
   `grep -n "settings\|security" routes/web.php | head -12`
2. Rerun `/tmp/billtest.php` (or recreate it from the tracker log) to confirm the grace-period rules.
3. Fix the `down()` in `2026_11_11_180000_add_billing_to_host_packages.php`. It only drops two indexes and leaves the columns.

**Decisions already made (do not reopen without asking)**
- Hosts choose their own plan. Admin Host billing page stays as an override. A plan assigned by admin has `billing_source = admin`, and webhooks never change those rows.
- Plan page goes under Settings for roles owner and company. Not manager.
- Checkout: Stripe Checkout in subscription mode. Build recurring prices inline from the admin prices (package price plus add-on lines, quantity for per property and per device features). No Stripe products to keep in sync.
- Trial: the package's `trial_days`, card collected at checkout, automatic charge when the trial ends (James asked for this).
- A $0 package with no add-ons is assigned directly with no card.
- Features turn on only after the webhook confirms trialing or active. The return from checkout alone does not grant anything.
- Past due: features stay on for 7 days (`HostPackage::PAST_DUE_GRACE_DAYS`), then switch off.
- Card update and cancel through the Stripe billing portal. Downgrade or cancel takes effect at period end. Upgrade takes effect now.
- A feature's own price is its a la carte price. Never charged on top of a package that already includes it. Free-for-all features are never charged.

**Testing plan:** Stripe test mode, Stripe CLI forwarding webhooks to the local site (port 8001), one trial start and one automatic charge end to end (use a test clock for the trial end). Only then mark 80 and 58 done.

**Open question for James:** nothing blocking billing. Still waiting on him: lease step after approval, and whether inventory (task 56) comes before or after packages.
