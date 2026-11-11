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
| 58 | Tiered packages, API key linking, inventory, reorder alerts | doing | James: packages "should be next", inventory "now if not too much" |
| 56 | Inventory categories and fields | todo | Not started. Categories: high value (serial, photo), cleaning supply, guest supply, other. Cleaner records closet take and room count, partial amounts. Usage report per stay and per cleaner |
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

Next, in order:
1. Read `app/Http/Controllers/StripeWebhookController.php` and `app/Models/User.php` before touching billing. Do not break guest payments on that webhook.
2. Host Plan page (owner and company roles only, not manager): choose package, start free trial, add-ons with quantity. Stripe Checkout in subscription mode with inline recurring prices built from the admin prices. Features turn on only after the webhook confirms trialing or active.
3. Webhook sync for subscription events on the platform webhook (trial will end, invoice paid, payment failed). Rows with billing_source = admin are never changed by webhooks.
4. Stripe billing portal for card update and cancel. Past due handling uses the 7-day grace.
5. Test in Stripe test mode with the Stripe CLI forwarding webhooks. Keys are `sk_test_` now.
6. Mark 80 and 58 done only after one trial start and one automatic charge work end to end.

Known issue: the `down()` in the billing migration only drops two indexes and leaves the columns. Fix before any production rollback.

## Other open items (small)

- Cleaning session status rejects `cancelled` (needs the column type: `show columns from cleaning_sessions where Field='status'`).
- `pms:sync` fails in the Channex provider (need the top of the error).
- Application fee refund button for hosts is not built.
- Declined lease ID: applicant is not emailed and cannot re-upload.
- Lease next step after approval: waiting on James (rental contract vs convert to booking).
- PriceLabs, Wheelhouse, iCal import untested. Test rate precedence by writing a rate row in tinker.
- Refund email and host-paid email: confirm real values appear (not literal braces).
- Full test suite had 48 unattributed failures before this work. Run `php artisan test`.
- Remove dead code (old lease `storeId`/`retryId`, `updateDirect`, screening route, unused `PhotoIdDeclinedMail`, dead `cancel()` in `DirectBookingController`) and the six `-copy-` test listings.
- Check the test listing with a $500.00 application fee.

## Log

- 2026-11-11: direct booking, lease, refund split, SEO fixes, notification templates (guest_refund_issued, host_card_paid), robots fix, packages data model and gate, admin Packages and Host billing pages, billing columns.

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
