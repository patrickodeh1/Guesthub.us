# Direct Booking Implementation Tracker

Source of truth: `DIRECT_BOOKING_HANDOFF.md`

## Current status

- **Phase 0 — Foundations:** Implemented and committed as `64d0672`; full-suite sign-off remains open because unrelated existing tests fail.
- **Phase 1 — Public site + SEO:** Implementation and focused tests complete; external Rich Results and Lighthouse validation remain outstanding.
- **Phase 2 — Checkout and payments:** Implemented; sign-off remains blocked by missing Stripe Connect credentials, unresolved full-suite failures, and lack of concurrent checkout testing.
- **Phase 3 — Calendar sync:** Implemented; Airbnb/Vrbo subscription refresh remains externally unverified.
- **Phase 4 — Rates:** Implemented and focused tests pass; live PriceLabs/Wheelhouse verification remains blocked by credentials.
- **Phase 5 — Lease applications:** Implemented and focused tests pass; live OCR/Stripe checks and counsel review remain.
- **Owner approval:** Phase 3 was approved while earlier validations remained pending; Phase 4 and Phase 5 were authorized in sequence.
- **Next gate:** Completion-gate validation and outstanding external/manual checks; do not treat the project as signed off yet.

## Phase 0 — Foundations

### Booking-impact audit

- Booking creation/status/date changes trigger `CleaningFlowService::syncForBooking()`. Pending-payment bookings are now treated as inactive for cleaning creation, auto-tagging, and staff notifications.
- The scheduled checkout-cleaning sync, manual cleaning assignment/scheduling, cleaner accountability, dashboard day views, and manage-session views now exclude pending-payment bookings.
- Checkout and registration reminders, automatic checkout/archiving, operational calendars, checkout overview, guest portal pages, and guest door-access paths exclude pending-payment bookings.
- Pending-payment bookings remain in booking overlap and availability-hold logic so they continue blocking dates until cancelled or expired.
- Existing iCal/PMS import behavior remains unchanged; outbound calendar export belongs to Phase 3.

### Implemented

- Added guarded migrations and models for feature switches, host feature access, property listings, listing photos, and host payment methods. Added Stripe Connect account/status columns for the later payment phase.
- Added `FeatureGate` behavior and unit tests, plus a global-admin feature settings page.
- Added an owner listing editor with publication requirements, generated title/description defaults, SEO checklist, payment methods, WebP photo processing, ordering, and media-library registration. Monetary listing fields are stored as integer cents.
- Added pending-payment exclusions to guest, reminder, checkout, dashboard, calendar, and cleaning workflows while preserving date blocking.
- Added tests for listing publication/drafts, cents/photo handling, feature settings, reminder exclusion, guest access, date conflicts, and manual cleaning-assignment rejection.

### Verification

- Focused tests: `docker compose exec -T app php artisan test --filter='FeatureGateTest|DirectBookingFoundationsTest'` — **8 passed, 29 assertions**.
- The new migrations were previously applied and rolled back successfully against an isolated SQLite database. Rollback drops the unique Stripe-account index before its column.
- Pint check of all newly added PHP files — **passed (12 files)**. A broad check reported style issues in pre-existing files; no broad formatting changes were applied.
- Full suite: **158 passed, 46 failed (533 assertions)**. Failures include unrelated tests expecting undefined legacy routes (`users.deactivate`/`users.reactivate`) and settings/navigation content; the focused direct-booking tests pass. Existing-page behavior is not fully verified.
- The test run created a root-level SQLite artifact (`guesthub`); it was removed after testing.

## Phase 1 — Public site + SEO

### Implemented

- Added the separate public route file and registered it through `bootstrap/app.php`: browse, city, listing, host, quote, availability, sitemap, robots, llms.txt, and optional IndexNow key verification.
- Added server-rendered, responsive browse and listing pages with GET filters, map locations rounded to city-level precision, photo dimensions/lazy loading, payment-option display, house rules, cancellation policy, and an optional configured screening-provider link.
- Added public listing/host eligibility checks for publication, active properties, the global feature switch, and current host entitlements. Public data excludes street addresses, lockbox data, guest records, and exact coordinates.
- Added bulk availability and integer-cent quote services. Quotes enforce date conflicts (including pending-payment holds), rate-ledger fallback, guest limits, stay limits, stop-sell, arrival/departure closures, advance notice, same-day cutoff, taxes, and card-only processing fees.
- Added SEO metadata, Open Graph/Twitter tags, `VacationRental`, `ItemList`, and `BreadcrumbList` JSON-LD, query-filter `noindex`, sitemap, robots rules, llms.txt, and optional IndexNow notifications on publish/unpublish.
- Added nullable photo width/height columns and store those dimensions during upload.

### Verification

- Focused public listing tests: **11 passed, 67 assertions**.
- Full suite: **169 passed, 46 failed (606 assertions)**. The 46 failures remain in existing admin/settings, navigation, route, and unrelated feature tests; the public listing tests pass.
- Pint on changed PHP files and `git diff --check`: **passed**.
- `php artisan migrate` applied the three pending direct-booking migrations to the configured app database. The isolated test SQLite artifact was inspected as empty and removed.
- Google's Rich Results Test, Lighthouse mobile scores, and manual phone/browser checks have **not** been run; these require a deployed/reachable public site or browser test environment.

## Phase 2 — Checkout and payments

### Implemented

- Added guest checkout with server-side quotes, persisted price snapshots, terms acceptance, guest count, manual/card payment selection, and locked date-conflict checks. Checkout rejects client price tampering.
- Added card checkout using Stripe Connect Standard-account OAuth and direct charges on the connected account, optional application fees, a dedicated Connect webhook, and account capability refresh. Existing platform deposit flows remain unchanged.
- Added manual-payment instructions, host payment confirmation, request-to-book approval/decline, guest/host cancellation, policy-based refunds, and email notifications through `EmailNotificationService`.
- Added 15-minute card holds and 24-hour manual/request holds, expiry and halfway-reminder scheduling, terminal-status availability release, and guest portal access after confirmation.
- Added booking price/payment fields and charge connected-account/refund fields; updated booking overlap and availability-ledger logic so expired, declined, and cancelled holds release dates.
- Added admin Connect onboarding and booking controls, public checkout/status/cancel routes, and guest-portal links.

### Verification

- Focused regression command: `docker compose exec -T app php artisan test --filter='DirectBookingCheckoutTest|DirectBookingFoundationsTest|PublicListingTest'` — **27 passed, 171 assertions**.
- Covered server-side price calculation/tampering, hold/date release, request approval/decline, card confirmation idempotency, manual confirmation, policy refund tiers, Stripe OAuth redirect/state, pending-payment cleaning exclusions, and public listing behavior.
- Pint and `git diff --check` pass after formatting.
- The same-date conflict test is sequential, not a simultaneous concurrency test. Stripe test-mode payments, real webhook delivery/replay, connected-account refunds, and screenshots remain unverified because Connect OAuth and webhook credentials are not configured.
- Full suite: **177 passed, 48 failed (675 assertions)**. Failures include legacy-route/settings/navigation, Stripe-dependent tests with invalid fake credentials, and pre-check-in charge assertions. The suite is not green; attribution of all failures is unresolved.
- The Phase 2 migration was applied and rolled back successfully on an isolated SQLite database after fixing rollback to remove indexes before indexed columns.
- Stripe test-mode payments, real webhook delivery/replay, connected-account refunds, and screenshots remain unverified: the Stripe Connect client ID and Connect webhook secret are not configured. No real Stripe Connect requests were made.
- A simultaneous same-date checkout race has not been exercised. The existing conflict test submits requests sequentially, so it is not evidence of concurrent locking.
- Owner authorized Phase 3 to proceed while Phase 2 validations were still pending; Phase 2 remains uncommitted and unsigned off.

## Phase 3 — Calendar sync

### Implemented

- Added a public tokenized iCalendar feed at `/ical/{ical_export_token}.ics`, gated by the host's `calendar_export` entitlement and active property status. Existing and newly created properties receive unique 64-character tokens.
- The feed exports active stays (including `pending_payment` holds) and manual availability blocks using anonymous `Reserved` events, exclusive checkout dates, stable opaque event UIDs, and no guest or property details.
- Added `?for=airbnb|vrbo|other` origin exclusion to prevent a channel's own reservations from being sent back to it. Invalid filter values are rejected.
- Cached event data for 60 seconds; booking lifecycle writes and availability-ledger changes invalidate the cache. Feed responses instruct clients and crawlers not to cache/index the token URL.
- Added a copyable feed link and plain Airbnb/Vrbo import steps to the host availability page, including the iCal refresh-delay caveat and existing advance-notice/same-day-cutoff guidance.
- Existing `pms:sync` already polls imports every two minutes in the current environment (scheduled every minute with a two-minute poll condition). The separate Airbnb iCal seed/import remains manual; it replaces a 500-day calendar range and is part of the existing Channex setup workflow.

### Verification

- Focused command: `docker compose exec -T app php artisan test --filter='CalendarExportTest|DirectBookingCheckoutTest|DirectBookingFoundationsTest|PublicListingTest'` — **33 passed, 216 assertions**.
- Tests parse the feed with Sabre VObject, verify anonymous events, source exclusions, manual blocks, feature gating, cache invalidation, and host UI instructions.
- The Phase 3 migration passed an isolated SQLite migrate/rollback; it was also applied to the configured app database and backfilled unique tokens for both existing properties.
- Pint and `git diff --check` pass. The route is registered at `GET /ical/{token}.ics`.
- No external Airbnb/Vrbo import refresh was performed; these services poll iCal on their own schedules. External OTA subscription/refresh and a third-party validator remain manual checks. Phase 2's separate Stripe/concurrency/full-suite blockers remain unresolved.
- Phase 3 changes are uncommitted together with the prior Phase 2 work.

## Phase 4 — Rates (PriceLabs + Wheelhouse)

### Implemented

- Added the `RateSource` abstraction with PriceLabs and Wheelhouse implementations, preserving one shared diff/apply flow through the availability ledger.
- Provider rates sync for direct-only properties; Channex-linked properties retain their rate-plan gate and outbox behavior. Manual rates remain protected, and quote precedence is manual override > active provider rate > listing base rate.
- Added encrypted host API keys, PriceLabs verification and listing selection, Wheelhouse existing-account and GuestHub-managed setup, provider switching with stale-rate cleanup, and a protected Wheelhouse webhook.
- Registered PriceLabs sync every six hours and Wheelhouse sync every four hours.

### Verification

- `PricingProviderTest`: **10 passed**; the final combined direct-booking focused run passed **45 tests / 352 assertions**.
- `Pint --test` passed on the changed Phase 4/5 PHP files; `git diff --check` passed.
- The Phase 4 pricing-provider migration and Phase 5 lease migration were applied to the configured MySQL database. The lease migration also passed an isolated SQLite up/rollback check.
- Scheduler and routes are registered. No live PriceLabs or Wheelhouse API calls were made; provider credentials/access remain pending.

## Phase 5 — Long-term lease application

### Implemented

- Added the four-screen public application flow with private ID image storage, reused OCR access, one scan retry followed by manual review, and OCR-derived values displayed read-only and rejected if edited in requests.
- Only the ID number’s last four are retained; no DOB or full ID number columns exist. Applicant, scan, employment/income, consent, and host-note fields use encrypted casts and are hidden from model serialization.
- Added card fee collection through the host’s Connect account, manual methods with host confirmation, retry after failed card payments, typed screening consent, and applicant/host status notifications.
- Added owner/admin-only application review, private ID viewing, status/notes controls, and a screening-request action.
- Added configurable declined-application retention (default 90 days), daily purge scheduling, private image deletion, and cleanup of abandoned drafts after 24 hours.

### Verification

- `LeaseApplicationTest` and `LeaseApplicationIdScanServiceTest`: **8 passed / 112 assertions**. The combined direct-booking focused run passed **45 tests / 352 assertions**.
- Covered server-side scanned-field immutability, no SSN/DOB storage, encryption at rest, card/manual fee handling, owner/admin access, honeypot/throttle behavior, status/screening notifications, retry/manual-review behavior, and purge deletion.
- The lease migration passed isolated SQLite up/rollback and was applied to the configured MySQL database. The purge command is scheduled daily at 02:00.
- No live Google Vision or Stripe Connect transactions were made. OCR and card intent behavior are covered with fakes; real-ID/mobile flow and external credential verification remain pending. The application-fee disclaimer does not replace local counsel review.

## Phase status

| Phase | Status | Notes |
|---|---|---|
| 0 Foundations | Implemented; regression sign-off pending | 46 unrelated full-suite failures remain. |
| 1 Public site + SEO | Implemented; external validation pending | Focused tests pass; Rich Results/Lighthouse/manual checks remain. |
| 2 Checkout + payments | Implemented; sign-off blocked | 27 focused tests pass; full suite has 48 failures of unresolved attribution. Migration rollback passes. True concurrent checkout and Stripe test-mode/webhook/refund verification remain unavailable. |
| 3 Calendar sync | Implemented; external OTA verification pending | 33 focused tests pass; migration applied and isolated rollback passed. PMS imports poll every 2 minutes. Manual Airbnb/Vrbo subscription/refresh checks remain. |
| 4 Rates | Implemented; live provider verification pending | Provider regression tests pass. No PriceLabs/Wheelhouse credentials or API validation. |
| 5 Lease application | Implemented; live credential/manual validation pending | 8 focused tests pass; Vision/Stripe test transactions and real-ID mobile flow remain unverified. |
| Completion gate | Pending | Full-suite attribution, concurrency, external OTA, Stripe, Vision, public SEO/browser, and manual owner checks remain. |
