# GuestHub Direct Booking Site: Handoff and Execution Guide

Read this whole file before touching code. It is the single source of truth for this project. Whoever continues the work (human or AI agent): follow the rules, update the **Status Log** at the bottom after every phase, and never skip ahead.

---

## 1. What we are building and why

GuestHub (https://github.com/patrickodeh1/Guesthub.us) is a **live Laravel 12 property management system (PMS)**. Hosts are leaving Airbnb because of new host rules, and the client (James) wants to market GuestHub as the place to build their **own direct-booking site**, free at launch.

We are ADDING a public layer on top of the PMS:

1. **Per-host booking sites** and a **shared browse/search page** across all hosts (OTA-style discovery, without reviews or guest accounts).
2. **Search-engine and AI discoverability built in**, with nothing for the host to configure.
3. **Checkout**: card (with application/processing fee) and manual methods (Zelle, Venmo, PayPal, etc.) with host-written instructions.
4. **Calendar and rate sync**: Airbnb/Vrbo iCal in and out, PriceLabs and Wheelhouse pricing.
5. **Long-term lease application**: a short form with ID scan (read-only fields), employment details and an application fee.
6. **Feature switches** so any feature can go from free to paid by ticking a box and setting a price.

**Out of scope (separate task):** host packages, trial days and automatic billing at trial end. We only build the feature-switch foundation here.

Every direct booking becomes a normal `Booking`, so the guest lands in the **existing** guest portal (identity, ID scan, contract, deposit, check-in).

---

## 2. Non-negotiable rules

1. **Additive only.** The app is in production. Do not change the behavior of the guest portal, cleaning flows, Channex/Seam/Telnyx integrations or cleaner pages. If a shared file needs a minimal change, make it and explain it in the report.
2. **Follow existing patterns:** guarded migrations (`Schema::hasColumn`), anonymous migration classes, `ActivityLogService`, `BookingConflictService::withPropertyLock()` + `assertFree()` for ANY booking creation, `Setting::getValue/putValue`, money as **integer cents** (never floats).
3. **Ship dark.** Every public route requires the feature gate `direct_booking_site` AND the listing's `published` flag. Nothing is public until a listing is published.
4. **Server-side pricing only.** Never trust a client-sent price.
5. **Public POST routes:** `throttle`, CSRF and a honeypot field.
6. **Secrets** (API keys, tokens) use Laravel `encrypted` casts and are never logged.
7. **One phase at a time.** Finish, test, commit, report, STOP. Do not start the next phase until the owner says so.
8. **Re-read before coding.** Before each phase, re-read the files it names. If reality differs from this document, say so and propose the smallest adjustment instead of improvising.
9. **Credential-gated integrations** (Wheelhouse partner key, Google, live Stripe Connect) must still be BUILT behind an interface with a fake implementation and tests. Mark them "needs live credentials to verify".
10. **Never claim something works unless it was run.** Say what you tested and what you could only read.

---

## 3. Verified facts about the repo

- Hosts are users with role `owner`; `properties.owner_id`. Admin routes use `property.access` and `role:` middleware.
- `Property`: slug, address, city, state, zip, latitude, longitude, beds, baths, header_image, `amenities()`, `ical_url`, `airbnb_ical_url`, `vrbo_ical_url`, `pricelabs_*` fields, `rate_source`. It has **no** public title/description, max guests, base rate, cleaning fee, tax, policies or photo gallery.
- `PropertyAvailability` (per property/date): is_available, status, source, rate, rates_source, min_stay_arrival/through, max_stay, stop_sell, closed_to_arrival, closed_to_departure.
- `AvailabilityLedger` and `app/Services/Pms/PriceLabsSync.php` write rates. **Current PriceLabs gap:** `sync()` returns "skipped" when `channex_rate_plan_id` is empty and forces `rate_source='guesthub'`; it uses one platform key (`config('services.pricelabs.api_key')`) and runs via `pricelabs:sync` every 6 hours (`routes/console.php`). Manual rates (`rates_source='manual'`) are never overwritten.
- `BookingConflictService::{withPropertyLock, assertFree, find}`. `Admin/BookingController@store` is the creation pattern: `booking_id = 'BK-'.strtoupper(Str::random(8))`, `token = Str::random(40)`, created inside the lock.
- `Booking` has **no price columns**. Check actual DB nullability before creating bookings from the public side.
- Guest portal: `guest.show` at `/guest/{booking_id}/{token}`.
- `PaymentService` (`app/Services/Payments`): Stripe on the platform account; `createPendingIntent`, `finalize`, `charge`. `Charge` types: deposit, parking, incidentals, early_checkin, late_checkout. `StripeWebhookController` handles `payment_intent.succeeded/payment_failed/canceled`. **No Stripe Connect exists.** `Setting 'processing_fee_percent'` is the global processing fee.
- ID scanning: `IdDocumentExtractor`, `AamvaParser`, `MrzParser`; see how `GuestController` uses them.
- Layouts: guest, cleaner, cleaning-guest, unified. Public views live in `resources/views/public`. Root `/` is the early-access page.
- Scheduler is in `routes/console.php`.
- Recent related work already shipped in the guest portal: the before-arrival instructions overlay (`guest/partials/pre-gps.blade.php`), the `pre_arrival_viewed_at` flag, and the check-in-day instructions button. Do not regress these.

---

## 4. Decisions already made

| Topic | Decision |
|---|---|
| Card payments | **Stripe Connect (Standard accounts, direct charges)**: each host connects their own Stripe; GuestHub can take an optional platform fee (default 0). |
| URLs | `guesthub.us/stay/{listing_slug}`, `/h/{host_slug}`, `/stays`, `/stays/{city}` (shared domain builds shared search authority). Custom domains are out of scope. |
| Booking mode | Instant book by default; per-listing toggle for request-to-book. |
| Pricing at checkout | Nightly rates from the availability calendar, host-set cleaning fee, tax %, processing fee (card only). |
| Cancellation | Presets: flexible (full refund until 24h before), moderate (until 5 days), strict (50% until 7 days). Host cancellation is always a full refund. |
| Manual payments | Dates held 24h, host marks payment received, unpaid holds auto-release. |
| Pricing providers | PriceLabs (host key, platform key fallback) and Wheelhouse (integrator model), behind a `RateSource` interface. |
| Lease application | No SSN stored; ID scan fields read-only; screening via the host's third-party link. |
| Feature switches | `is_free` flag + price, so free-to-paid is a settings change. |

---

## 5. What third parties actually allow (verified)

- **PriceLabs:** the Customer API key belongs to ONE PriceLabs account and only reads that account's listings ($1/listing/month). A listing must be live in PriceLabs through Airbnb, Vrbo or a PMS. The PMS/OTA **Integration API is "Coming Soon"**; an **RM Partner API** exists for consultants managing multiple accounts. So hosts paste their own key; the platform key covers the owner's own properties. Ask PriceLabs about Integration API / RM Partner access.
- **Wheelhouse:** has an integrator API where a partner registers users and listings under a partner key; a host with an existing account can alternatively generate a **User API Key** and send it in the `X-User-API-Key` header. Webhooks exist for price updates and calendar refresh requests. Verify every endpoint against the current OpenAPI spec (api.usewheelhouse.com/wheelhouse_pro_api) before coding.
- **Google/AI discoverability:** we can make pages crawlable, fast and structured (schema.org `VacationRental`, sitemap, robots, llms.txt, IndexNow). We **cannot guarantee rankings**, and Google's vacation-rental partner program is not integrated. Do not promise "surefire" results in any copy.
- **iCal sync is not real-time.** Airbnb/Vrbo refresh imported calendars on their own schedule, so overlaps are possible. Mitigate with advance-notice hours and same-day cutoff; say so honestly in the host UI.

---

## 6. Data model (all guarded migrations)

- `features` (key unique, name, is_free, price_cents nullable, active); `host_features` (user_id, feature_key, enabled, source free|package|addon|manual, price_cents nullable, starts_at, ends_at). Seed: direct_booking_site, manual_payments, lease_applications, calendar_export, wheelhouse_pricing, pricelabs_pricing (all free).
- `property_listings` (1:1 property): listing_slug unique, public_title, public_description, max_guests, bedrooms, base_rate_cents, cleaning_fee_cents, tax_percent, booking_mode, advance_notice_hours, same_day_cutoff_time, min_nights, cancellation_policy, house_rules, lease_enabled, lease_application_fee_cents, screening_provider_url, published_at, meta_title, meta_description.
- `listing_photos` (listing_id, path, alt, sort_order). Check `MediaService`/`MediaFile` first and reuse if it fits. Generate resized WebP variants.
- `host_payment_methods` (owner_id, type zelle|venmo|paypal|cashapp|other, label, handle, instructions, enabled).
- `bookings` (nullable): accommodation_cents, cleaning_fee_cents, tax_cents, processing_fee_cents, total_cents, payment_method, hold_expires_at, payment_confirmed_at, guests_count. New status `pending_payment`.
- `users`: stripe_account_id, stripe_charges_enabled, stripe_payouts_enabled, host_slug unique, pricelabs_api_key (encrypted), wheelhouse_user_api_key (encrypted), wheelhouse_integrators_user_id.
- `properties`: ical_export_token unique random, wheelhouse_listing_id, pricing_provider (none|pricelabs|wheelhouse).
- `lease_applications` (listing_id, property_id, status submitted|in_review|approved|declined, applicant fields, fee_cents, payment info, consent_at, consent_ip, consent_user_agent, timestamps). Sensitive columns use `encrypted` casts.
- `charges`: add types `accommodation`, `application_fee`; nullable `booking_id` plus nullable `lease_application_id` (check existing constraints first).

---

## 7. Phases

Each phase has **tasks** and a **DONE WHEN** checklist. Do not mark a phase done until every box is true and you have run the tests.

### PHASE 0: Foundations (no public output)
1. **Audit first, change nothing.** List every place a Booking triggers an action (cleaning jobs, notifications, SMS, door codes/Seam, reminders, dashboards, calendar views, iCal dedupe, BookingConflictService filters). Decide how `pending_payment` behaves in each: it must NOT trigger cleaning, door codes, SMS or reminders, and it MUST block dates in conflict checks. Report the list before coding.
2. Migrations, models (Feature, HostFeature, PropertyListing, ListingPhoto, HostPaymentMethod) and `FeatureGate::enabled(User $owner, string $key): bool` (is_free => true unless explicitly disabled; otherwise needs an enabled `host_features` row within its dates).
3. Global-admin page (role admin) to list features and edit `is_free` and `price_cents`. No billing logic.
4. Owner listing editor (admin routes with `property.access`): content, photo upload with drag-sort, pricing, policies, payment methods, publish toggle. Publishing requires: title, description >= 150 words, >= 5 photos, base rate, max guests, coordinates, and at least one payment method (connected Stripe or a manual method). Show an SEO checklist.
5. Auto-defaults: if title/description are empty, generate them from beds, baths, city and amenities when publishing.

**DONE WHEN**
- [ ] Migrations run and roll back cleanly
- [ ] Existing pages unchanged
- [ ] FeatureGate unit tests pass
- [ ] An owner can build, save and publish a valid listing; an incomplete one is blocked
- [ ] Audit report delivered

### PHASE 1: Public site, browse and SEO
Routes go in a NEW routes file (not `routes/web.php`): `/stays` (browse across hosts; filters city, check-in, check-out, guests, price via GET so URLs are crawlable; list + map), `/stays/{city}`, `/stay/{listing_slug}`, `/h/{host_slug}`, `/sitemap.xml`, `/robots.txt`, `/llms.txt`. New `layouts/public.blade.php`, mobile-first, matching the brand.

Services: `AvailabilitySearch` (bulk date-range availability) and `QuoteService::quote(listing, in, out, guests)` returning line items: nightly rates (PropertyAvailability.rate, else base_rate_cents), cleaning fee, tax, processing fee (card only), total. Enforce min/max stay, stop_sell, closed_to_arrival/departure, advance notice, same-day cutoff, max guests and conflicts via `BookingConflictService::find`.

Listing page: gallery, description, amenities, approximate-location map, house rules, cancellation policy, availability calendar (blocked dates greyed, min-stay aware), live quote widget (throttled JSON endpoint), and an "Apply for a long-term lease" link when enabled. **Never expose** lockbox, door codes, guest names or the exact street address before booking.

SEO (server-rendered, core content must not need JS): unique title/meta description, canonical, Open Graph/Twitter; JSON-LD `VacationRental` (city-level address, geo, image, amenityFeature, occupancy), `BreadcrumbList`, `ItemList` on browse/city pages, validated with Google's Rich Results Test; sitemap (published listings, hosts, cities, lastmod); robots allowing Googlebot, Bingbot, GPTBot, ClaudeBot, PerplexityBot, Google-Extended and disallowing /admin, /guest, /cleaning, checkout and quote endpoints; `noindex` on date/guest-parameter URLs, checkout and unpublished listings (404); responsive WebP images with width/height and lazy loading; IndexNow ping on publish/unpublish (configurable, silent when unset); `/llms.txt`.

**DONE WHEN**
- [ ] Quote math and every restriction tested
- [ ] Unpublished listing returns 404
- [ ] JSON-LD valid; sitemap and robots tested
- [ ] Lighthouse mobile performance and SEO scores reported
- [ ] A grep test proves no private data appears in page source

### PHASE 2: Checkout and payments
Flow: listing -> `/stay/{slug}/checkout` (guest details, guest count, accept terms and cancellation policy) -> pay -> confirmation -> redirect to `/guest/{booking_id}/{token}`; email the portal link via `EmailNotificationService`.

`CheckoutService::start()` inside `withPropertyLock`: re-run QuoteService, `assertFree`, create the Booking (source `direct`, status `pending_payment`, `hold_expires_at` now+15 min card / +24h manual, `reservation_id = 'DB-'.strtoupper(Str::random(8))`, price columns from the quote).

**Card via Stripe Connect:** onboarding controller (account + Account Link), store the three users columns, `account.updated` handled on a SEPARATE Connect webhook route with its own secret (`STRIPE_CONNECT_WEBHOOK_SECRET` in `config/services.php`). Extend `PaymentService` without breaking existing methods: PaymentIntent with the `stripe_account` option and `application_fee_amount` (Setting `direct_booking_platform_fee_percent`, default 0). The processing-fee line the guest pays covers Stripe's cost. Verify exact Stripe usage against current Stripe docs and state what you relied on. On `payment_intent.succeeded`: idempotently confirm the booking, clear the hold, set `payment_confirmed_at`, create a Charge (type accommodation), send emails. Replays must not double-confirm. Hosts with no connected account get manual methods only.

**Manual methods** (feature `manual_payments`): checkout shows the host's enabled methods; selecting one creates the pending booking, uses the booking_id as the payment reference and shows the host's instructions. The host gets an email and an admin "Mark payment received" action. Command `directbookings:release-expired-holds` runs every 5 minutes: cancels expired holds, frees dates, notifies the guest; send a reminder at the half-way point.

**Request-to-book:** booking waits for host approval; approval sends a payment link; decline frees the dates.

**Cancellations:** guest cancel link applies the listing's preset and refunds through the same Stripe account. Host cancellation is a full refund.

**DONE WHEN**
- [ ] Concurrent double-booking test: two requests, one wins
- [ ] Hold expiry and webhook idempotency tested
- [ ] Price-tamper attempt rejected
- [ ] `pending_payment` triggers no cleaning/SMS/door code
- [ ] Expired hold frees the dates
- [ ] Manual flow works end to end; request-to-book works; every cancellation tier tested
- [ ] Stripe test-mode run completed with screenshots

### PHASE 3: Calendar sync
1. Outbound feed `GET /ical/{ical_export_token}.ics`, optional `?for=airbnb|vrbo|other`: confirmed and `pending_payment` bookings plus manual blocks as anonymous "Reserved" events. With `for=X`, EXCLUDE bookings that originated from X so a channel never receives its own reservations back. Add a copy-button URL and plain Airbnb/Vrbo instructions on the host availability page (feature `calendar_export`).
2. Imports run at least every 30 minutes (inspect existing importers and scheduler). A new direct booking invalidates the feed cache immediately.
3. Per-listing advance notice and same-day cutoff. Show the host an honest note about channel refresh lag.

**DONE WHEN**
- [ ] The feed validates in an iCal validator
- [ ] Excluded-source test passes
- [ ] A direct booking appears in the feed within seconds
- [ ] Import schedule verified

### PHASE 4: Rates (PriceLabs + Wheelhouse)
Do NOT build separate one-off integrations. Refactor `app/Services/Pms/PriceLabsSync.php`:
1. Split into `fetch` (diff against existing rows, honor `rates_source='manual'`, unchanged-refresh early exit) and `apply` (existing ledger write). The `channex_rate_plan_id` check and `rate_source='guesthub'` forcing apply ONLY to properties using the Channex outbox; direct-only properties sync without them. **Channex-linked behavior must not change** (regression test).
2. `RateSource` interface with `PriceLabsRateSource` (existing logic) and `WheelhouseRateSource`.
3. PriceLabs credentials resolve in order: host key (`users.pricelabs_api_key`) -> platform key (config) -> none. Host UI: paste key, "Verify" via `GET https://api.pricelabs.co/v1/listings_minimal` (header `X-API-Key`), choose the listing, set `pricelabs_listing_id` / `pricelabs_pms`. Clear error on `403 API_KEY_INVALID`.
4. Wheelhouse (integrator model): `config/services.php` entry `wheelhouse` (partner_api_key, base_url). Two paths: (a) GuestHub registers the host as a Wheelhouse user and creates/updates listings under the partner key (store `wheelhouse_integrators_user_id`; ids must be unique across environments, so prefix `prod-` / `test-`); (b) a host with an existing account pastes a User API Key, sent as `X-User-API-Key`. Pull price recommendations per listing into the ledger via the same `apply` step. Support their webhooks (price updated, calendar refresh requested). Build and test against a fake client; mark live verification pending credentials.
5. Per-property `pricing_provider`; only one active at a time. Switching providers must not leave stale rates (document and test the behavior).
6. Precedence in quotes: manual override > provider rate (ledger) > `base_rate_cents`.
7. Keep `pricelabs:sync` every 6h; add `wheelhouse:sync` every 4h (webhooks trigger an immediate pull).

**DONE WHEN**
- [x] A direct-only property with no Channex plan gets provider rates in quotes
- [x] A Channex-linked property behaves exactly as before
- [x] Manual rates are never overwritten
- [x] Invalid keys show errors and fall back to the base rate; keys are encrypted in the DB
- [x] Wheelhouse fake-client tests pass; provider switching tested

### PHASE 5: Long-term lease application
Feature `lease_applications`; `lease_enabled` per listing. Route `/stay/{slug}/apply`. Keep it SHORT, 4 screens:
1. **Scan ID** (reuse the extractor and storage; images private). Extracted name, address and ID number (store last 4 only) are shown READ-ONLY, enforced server-side. If the scan fails: retry, then a manual-review path flagged for the host.
2. **About you and move-in:** email, phone, move-in date, lease term, occupants, pets, vehicles, current address and duration.
3. **Employment:** employer, title, supervisor contact, monthly income, years employed, previous landlord name/phone, emergency contact.
4. **Consent and fee:** screening authorization checkbox plus typed signature (consent_at, ip, user agent), then the application fee (per-listing amount + processing fee) via card (Connect) or manual methods.

Do NOT collect or store SSN or full DOB beyond what the ID yields. Screening goes through the host's `screening_provider_url`. No criminal-history or eviction questions by default. Encrypt employer/income columns. Retention setting (default 90 days after decline) plus a purge command. Host review screen: list, detail, status changes, notes, "request screening". Applicant status emails. Fee page text: non-refundable unless the host's policy says otherwise; hosts are responsible for local fee caps.

**DONE WHEN**
- [x] Edited scanned fields are rejected server-side
- [x] Fee payment tests pass
- [x] Encrypted columns verified in the DB
- [x] Throttle and honeypot tests pass
- [x] Applications visible only to the listing's owner and admins
- [x] Purge command tested

**Phase 5 implementation notes:** the OCR parser reuses `IdDocumentExtractor::readRawText()` and only returns name, address, and ID-number last four; raw OCR text and DOB are not persisted. ID images use the private local disk and are available only through the authenticated owner/admin review route. Declined application retention is configurable in Global Settings (default 90 days); abandoned drafts are purged after 24 hours. Application fees support the listing amount plus the configured card processing fee, Connect card payment, or manual host confirmation.

---

## 8. Completion gate (before calling the project finished)

- Run the full test suite and report results.
- Run `migrate` and `migrate:rollback` on a copy of the database.
- Confirm every public route is dark until a listing is published.
- Confirm existing guest-portal, cleaning, Channex and admin flows still pass their tests.
- Provide a seeded demo host with one published listing for manual testing.
- List every deviation from this document and every item awaiting live credentials.

---

## 9. Owner checklist (things code cannot do)

1. Enable **Stripe Connect** on the Stripe account; have test keys and the Connect webhook secret ready for Phase 2.
2. Ask **Wheelhouse** for integrator/partner access; ask **PriceLabs** about Integration API or RM Partner access.
3. Submit `sitemap.xml` in Google Search Console after Phase 1; set the IndexNow key.
4. Have counsel review the lease-application and application-fee wording.
5. Market it as "built to be found," not "guaranteed to rank."

### Manual test script (after each phase)

| Phase | Test | Complete looks like |
|---|---|---|
| 0 | Build and publish a listing; try publishing an incomplete one | Incomplete is blocked, complete publishes, existing pages unchanged |
| 1 | Open `/stays`, a city page and a listing on a phone; view page source | Fast pages; JSON-LD present; no address or lockbox in source; unpublished listing returns 404 |
| 2 | Book with a Stripe test card and with a manual method; try the same dates in two tabs | One booking wins; card goes to the host's account; manual hold auto-releases; guest lands in the portal |
| 3 | Paste the feed URL into a calendar app; make a direct booking | Date blocks within seconds; a channel's own bookings never echo back |
| 4 | Paste a PriceLabs key on a no-Channex property | Quotes show provider rates; manual overrides survive |
| 5 | Apply from a phone with a real ID | Scanned fields locked; fee paid; host sees the application |

---

## 10. Reporting format (after each phase)

Files changed, migrations added, commands to run (`php artisan migrate`, scheduler entries), tests added and results, what you could NOT verify, and any deviation from this document with the reason. Then update the Status Log below and stop.

## 11. Known limitations (state these in the final report)

- Deposits, parking and incidentals still use the platform Stripe account; Connect applies to the stay payment and application fee.
- iCal sync lags by the channel's refresh schedule.
- No reviews, guest accounts or ranking algorithm.
- Google rich results do not guarantee rankings; Google's vacation-rental partner program is not integrated.
- Wheelhouse and live Stripe Connect behavior are unverified until real credentials exist.

---

## 12. Status Log (update after every phase)

| Phase | Status | Commit | Date | Notes / deviations / unverified items |
|---|---|---|---|---|
| 0 Foundations | Implemented | 64d0672 | 2026-11-09 | Focused tests pass; full-suite regression sign-off remains outstanding. |
| 1 Public site + SEO | Implemented | 95bb91d | 2026-11-09 | Focused tests pass; external Rich Results, Lighthouse, and manual browser checks remain. |
| 2 Checkout + payments | Implemented; sign-off blocked | | 2026-11-10 | 27 focused tests pass; full suite has 48 failures of unresolved attribution. Isolated migration rollback passes. True concurrent checkout and Stripe test-mode/webhook/refund verification remain unavailable because Connect credentials are missing; changes are uncommitted. |
| 3 Calendar sync | Implemented; external verification pending | | 2026-11-10 | Tokenized anonymous iCal export, source exclusion, cache invalidation, and host instructions implemented. 33 focused tests pass; migration applied and rolled back in isolation. PMS poll is every 2 minutes. Manual OTA subscription/refresh and third-party validator checks remain; Phase 2 is still unsigned off. |
| 4 Rates | Implemented; live provider verification pending | | 2026-11-10 | 10 pricing-provider tests pass. Configured MySQL migration applied. PriceLabs/Wheelhouse live credentials/API verification remain unavailable. |
| 5 Lease application | Implemented; live/manual verification pending | | 2026-11-10 | 8 focused tests pass; lease migration applied to configured MySQL and passed isolated SQLite up/rollback. Real Google Vision/Stripe Connect and real-ID mobile tests remain; counsel should review fee wording. |
| Completion gate | Pending | | | Full suite, concurrency, external OTA, Stripe, Vision, SEO/browser, and manual owner checks remain. |

**Next action for whoever picks this up:** perform the outstanding completion-gate checks, especially Phase 2 full-suite attribution/concurrency/Stripe validation, Airbnb/Vrbo refresh, live pricing/OCR/Connect checks, and counsel review of lease-fee wording. Phases 4 and 5 are implemented, but the project is not signed off.
