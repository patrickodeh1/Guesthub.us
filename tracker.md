# Direct Booking Implementation Tracker

Source of truth: `DIRECT_BOOKING_HANDOFF.md`

## Current status

- **Phase 0 — Foundations:** Implemented and committed as `64d0672`; full-suite sign-off remains open because unrelated existing tests fail.
- **Phase 1 — Public site + SEO:** Implementation and focused tests complete; external Rich Results and Lighthouse validation remain outstanding.
- **Owner approval:** Received for Phase 0 and Phase 1.
- **Next gate:** Complete/report Phase 1; do not start Phase 2 without owner approval.

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
- `php artisan migrate` applied the three pending direct-booking migrations to the configured app database. The generated, untracked SQLite `guesthub` test artifact is separate and will be removed.
- Google's Rich Results Test, Lighthouse mobile scores, and manual phone/browser checks have **not** been run; these require a deployed/reachable public site or browser test environment.

## Phase status

| Phase | Status | Notes |
|---|---|---|
| 0 Foundations | Implemented; regression sign-off pending | 46 unrelated full-suite failures remain. |
| 1 Public site + SEO | Implemented; external validation pending | Focused tests pass; Rich Results/Lighthouse/manual checks remain. |
| 2 Checkout + payments | Not started | |
| 3 Calendar sync | Not started | |
| 4 Rates | Not started | |
| 5 Lease application | Not started | |
| Completion gate | Not started | |
