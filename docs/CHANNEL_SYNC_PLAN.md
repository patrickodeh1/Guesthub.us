# GuestHub — Repull Channel Sync: Plan, Status & Code Guide

> **Handoff document.** Any AI (Copilot CLI, Claude Code, a developer) can read this and continue.
> Written 2026-11-21. Focus: **Repull** as the OTA connectivity + listing-content layer. Other vendors (NextPax, Rentals United) are parked.
> **Every session:** tick boxes in section 7, update section 9 (blockers) and append to section 11 (progress log) before stopping.

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked · **VERIFY** = taken from Repull's docs/marketing, not yet proven against the real API.

---

## 0. Ground rules

1. **Never edit, commit to or merge existing branches** (`main`, `fix/outstanding_work`, `integrations/phase-0-pricelabs-safety`, `work/2026-11-*`). The owner keeps them unmerged so he knows what to zip. Work on `feature/repull-foundation` (or a child of it).
2. **No secrets** in Git, logs, UI or chat. `REPULL_API_KEY` is a **workspace-wide, production** key (Repull has no sandbox/test mode).
3. **Host isolation is ours to enforce.** One Repull key sees *every* host's connected accounts. All reads/writes must go through `channel_connections.owner_id` (see `RepullListingService::connectionFor`). Never pass a key or account id from one host to another. Every feature needs a two-host test.
4. **Ids are strings.** Airbnb ids are 19 digits (exact in PHP's int64, corrupt in JS/floats). Always `(string)`-cast before storing, comparing or sending back.
5. **Deliveries are zipped by the owner:** every delivery bumps the version, adds an `update.txt` entry (what changed, how to access it, deploy steps: migrations, `npm run build`, `php artisan view:clear`, queue restart).
6. The app is deployed under a **`/welcome` sub-path** — build callback/redirect URLs only with `route()`/`url()`.

---

## 1. What the client wants (priority order)

1. **Direct booking site that looks like the host's own site:** unique slug, host logo as header, "Powered by GuestHub" footer, custom domain (domain last).
2. **Edit once in GuestHub → push to Airbnb, Vrbo, Booking.com** (+ Furnished Finder, Zillow — parked, section 8). Hosts want one place to edit.
3. Calendars/rates synced everywhere; PriceLabs/Wheelhouse feed rates (one per host).
4. **Import an existing Airbnb listing** into GuestHub (OutKeepr-style "paste your Airbnb link").
5. Guest/tenant screening (Autohost for guests — later). Reviews: out of scope.

**Client's sequencing:** logo/branding → OTA push → custom domains. He is open to a third-party provider; he chose Repull to go ahead with.

---

## 2. Repo facts (verified by reading the code)

- Laravel 12 / PHP 8.2 / MySQL / Blade + Vite / PHPUnit. Roles: `admin, company, owner, manager, housekeeper` (spatie). Queue + scheduler required.
- **Public direct-booking site exists:** `/stays`, `/stay/{listing_slug}`, `/h/{host_slug}`, checkout, lease application (`routes/public.php`, `PublicListingController`). `property_listings.listing_slug` is globally unique. `host_slug` is *derived* (`slug(name).'-'.$ownerId`). **No host logo / branding / custom domain / "Powered by"** yet.
- Listing model: `property_listings` (+ `listing_photos`, public photo URL = `url('/img/'.$path)`), `properties` (address — may be a one-line full address —, city, state, zip, country, latitude, longitude, beds, baths, contact_email/phone, `channex_*`). `amenities` is free text per property (no OTA taxonomy).
- `PmsProviderInterface` (bookings + ARI only). Channex is the live provider (`config/services.php` → `channex.base_url`, `channex.api_key`, `channex.webhook_secret`; header-secret webhook). `ChannexOutbox` batches ARI pushes. **No listing-content/publish code existed.**
- Integrations branch also has: Channex property/room/rate-plan creation + channel iFrame, PriceLabs IAPI (disabled until PriceLabs issues credentials), Wheelhouse host key, Seam, Stripe Connect.

---

## 3. Branches

| Branch | vs `main` | Holds |
|---|---|---|
| `main` | — | Matches the server. |
| `integrations/phase-0-pricelabs-safety` | 262 ahead / 23 behind; merges cleanly (except deleted todo files) | All integration work (Channex create/iFrame, PriceLabs IAPI, Wheelhouse, Seam, Stripe Connect). **Base for new work.** |
| `fix/outstanding_work` | 7 ahead | Timezone alerts, per-owner notification settings, photo guides, OG image. Unrelated. |
| `work/2026-11-remaining-client-backlog` | 64 ahead | Turnover gap, plate capture, misc. (contains `work/2026-11-client-outstanding-backlog` — ignore that one). |

**`feature/repull-foundation`** = new, created from `integrations/phase-0-pricelabs-safety` (patch also applies cleanly to `main`).

---

## 4. Repull — verified facts (from Repull's own docs, read 2026-11-21)

Sources: `https://repull.dev/llms-full.txt`, `/docs/api/channex`, `/for-platforms`, `/airbnb-api`, `/docs/create-a-listing`, `/docs/publish-a-listing`, `/docs/connect-with-laravel`. These are the vendor's own docs: authoritative for *what they claim*, still to be proven by section 6.

**Model**
- Base `https://api.repull.dev`, `Authorization: Bearer sk_live_…`. **One key type, no sandbox** — every call hits real channels. `Idempotency-Key` on POST/PUT/PATCH (stored 24 h). Rate limits: Free 60/min, Starter 500/min.
- **Platform model (`/for-platforms`):** one workspace + one key for GuestHub; each host connects *their own* OTA account via hosted Connect (`POST /v1/connect` with `redirectUrl`, `state`, `allowedProviders`). Every webhook/record names its `account` (`provider`, `externalAccountId`). Keys can't be scoped per listing → **isolation is on us.** Optional per-customer workspace via `X-Workspace-Id`. Billing is on GuestHub's workspace (no reseller billing). White-label Connect: logo/colors/support email at `/dashboard/settings/connect`; URL stays `connect.repull.dev`; "Powered by Repull" link stays; no custom domain.
**Pricing (current public `/for/operators`, checked 2026-11-21; re-confirm with vendor):** one listing counts once regardless of how many channels it is on. Free: up to 3 listings and 1,000 API calls/month. Starter: $99/month includes 10 listings; $5 per additional listing up to 100; 100,000 calls/month; extra calls $1/1,000. Above 100 is custom. Current public operator pricing does not list a separate Extranet-login surcharge; confirm the actual workspace contract before quoting hosts.

**Airbnb** (`/airbnb-api`)
- Repull holds Airbnb partner approval; host authorises via OAuth. Tiers: `full_access` (property management), `messaging`, `read_only`.
- **Airbnb access depends on tier.** `full_access` is the channel-management path and must not compete with another full-management integration. `messaging` access is documented to coexist with an existing PMS/channel manager; in that mode GuestHub must not push listing content or ARI. Do not migrate a host from Channex unless they opt into full management.
- Airbnb authorises sync **per listing** (`syncCategory`: `sync_all | sync_rates_and_availability | none`; `GET /v1/channels/airbnb/listings` returns `syncCategory` + `writable`). A connected account can still hold unwritable listings (host must enable API sync on the listing in Airbnb; reconnecting does not help).
- **Create** a listing: `POST /v1/listings/{id}/publish/airbnb {hostId}` (needs a `full_access` connection, `lat/lng`, full postal address). **Update:** `{airbnbConnectionId}`; `force:true` re-pushes all sections.
- A publish is up to **8 independent calls**, partial success is normal, no rollback. Result: `{published, live, sections[], errors[], warnings[], lockedFields[]}`. `published` ≠ `live`; `live` *absent* means "not asked" (not false). Instant-booking-unknown ⇒ listing is **not auto-activated** (warning, not error).
- **Locked fields:** on established listings Airbnb returns **200 and applies nothing** for host-managed fields (title, summary/space text, property type category, check-in option, address, some amenities). Success = empty `blockedFields`, **not** a 2xx. `GET /v1/listings/{id}/publish-status` → `connections[].lockedFields`.
- Airbnb rejects descriptions/house rules containing **links, emails, phone numbers** (`ContentSanitizer` strips them).
- Address is creation-only on Airbnb. Checkout tasks need Airbnb certification (unavailable until then).

**Create/content/publish API (`/docs/create-a-listing`, `/docs/publish-a-listing`)**
- `POST /v1/listings {name, propertyType, street, city, state, postalCode, countryCode, lat, lng, bedrooms, beds, bathrooms, personCapacity, summary, description, defaultDailyPrice, cleaningFee, cancellationPolicy, checkInTimeStart, checkOutTime, allows*}` → `201 {id}` (**id is a string**). `defaultDailyPrice` seeds **365 nights** of calendar (`calendarDaysSeeded`); no price ⇒ no calendar ⇒ Booking.com can't open the property.
- `PUT /v1/listings/{id}/content` (sections: `title|name, description, summary, address, occupancy, details, policies, amenities, photos, rooms, checkoutTasks, pricing`, + `locale`, `photosMode`). **Local to Repull — reaches no channel.** `amenities`, `photos`, `rooms`, `checkoutTasks`, `guestSafetyDisclosures` are **FULL REPLACEMENT** (a partial amenity write wipes the rest). Returns `{id, changed[], deferred[]}`; read `changed`, don't assume.
- `GET /v1/listings/{id}/publish-status` → `addressReadiness.<channel>.{ready, missing[]}`, `channels[]` (`pushStatus`, `pushError` = channel's own words: render, never branch on it), `connections[]` (`lockedFields`).
- `POST /v1/listings/{id}/offline|online` fans out across channels; **`PATCH {active:false}` does NOT unlist** (listing stays live and unseen — never use it to unlist).
- Photos: public https URLs in `photos[]`, or `POST /v1/listings/{id}/photos/upload-url`.

**Booking.com**
- Repull is a designated connectivity provider (Booking.com is pausing *new* providers). Create flow: `POST /v1/channels/booking/setup {action:"create-property", listing_id, contact{name,email,phone}}` → `propertyId` (status `being_built`, `sellable:false`) → `add-room` (check `rateId` ≠ null) → `advance` (readiness: photos processed, availability, licence where required) → `POST /v1/listings/{id}/publish/booking {hotelId}` (409 `ambiguous_booking_mapping` if listing maps to several properties).
- Extranet-login (beta) lets Repull run *next to* an existing channel manager.

**Vrbo** (`/vrbo-api`, llms-full §8)
- **Host signs in with their own Vrbo account** (`allowedProviders:["vrbo-login"]`). **Repull is not a Vrbo/Expedia Group partner** ⇒ login-based; ToS/ban risk to explain to the client.
Current `/docs/channels/capabilities` says Vrbo beta host sign-in supports new listing creation (queued 5–15 minutes) but does not support re-publishing content to an existing listing. Generic ARI requires full access. This supersedes the older section-edit notes; verify with a throwaway account before enabling.

**Channex-compatible API (`/docs/api/channex`)** — *the key architecture fact*
- Repull serves the **Channex API as a drop-in**: base `https://api.repull.dev/channex/api/v1`, header `user-api-key: <Repull key>`; same paths, bodies, filters, envelopes, webhooks and HMAC signing. Booking.com/Airbnb/Vrbo channels (`BDC/ABB/VRB`). `/properties`, `/room_types`, `/rate_plans`, `/availability`, `/restrictions`, `/booking_revisions` (feed, ack), `/webhooks`, `POST /meta/airbnb/connection_link`, `POST /auth/one_time_token` + `https://api.repull.dev/channex/auth/exchange` (mapping iFrame; only `redirect_to=/channels`).
- Differences: new channels start **deactivated** (call `activate`); Airbnb/Vrbo sell **one rate** (primary plan); **no card charging**; revision ids start fresh on import; some webhook events not sent (`updated_channel`, `airbnb_notification`, `booking_unmapped_rate`…); read `meta.warnings`.
- **Migration:** "Switch from Channex" (`/docs/migrate/channex`, `POST /v1/migrations/channex`) imports a Channex account keeping property/room/rate-plan/webhook/channel ids.

**Other**
- Webhooks: `X-Repull-Signature: t=<unix>,v1=<hex hmac-sha256 of "<t>.<raw body>">`, `X-Repull-Event`, `X-Repull-Delivery`, `X-Repull-Account(-Id)`. 40 event types (`connect.session.completed`, `account.created`, `account.disconnected`, `channel.sync.failed`, `channel.degraded`, `listing.suspended`, `reservation.*`, …). Retries 7× over 24 h, same `eventId`; replay ≤3/hour.
- PHP SDK exists (`repull/sdk`) but the foundation uses Laravel `Http` (explicit endpoints, `Http::fake`-testable, no generated-code risk).

---

## 5. Architecture decisions (revised)

- **R1. Repull is the full-management OTA layer for hosts who opt into it.** Airbnb `full_access` is exclusive against another property-management app, so those hosts must switch off the incumbent manager at cutover. Airbnb/Vrbo `messaging` and Booking Extranet login can coexist with an existing PMS; in messaging mode GuestHub must not push listing content, calendar or prices.
- **R2. ARI/bookings: start with the Channex-compatible API** (`CHANNEX_BASE_URL=https://api.repull.dev/channex/api/v1`, `CHANNEX_API_KEY=<Repull key>`; existing `ChannexProvider`, `ChannexOutbox`, webhook controller and tests stay). If the staging test (T3.1) passes, no ARI rewrite is needed. Fallback: native `PUT /v1/availability/{propertyId}` / `PATCH /v1/availability/batch`.
- **R3. Content: Repull-native API** via `RepullClient` → `RepullListingService` (create listing → write canonical content → publish → record per-section result). No abstraction layer yet (YAGNI); add `ChannelContentProvider` only if a second provider is adopted.
- **R4. Per-channel state is explicit** (`listing_channel_links`: `not_connected | publishing | published | live | partial | error | unlisted`) with per-section results, `locked_fields`, `warnings`, `last_error`. UI shows what is automated vs host-managed instead of failing silently.
- **R5. Connections are host-owned.** `channel_connections` (owner_id, channel, external_account_id **string**, status). Connect `state` is HMAC-signed (`RepullConnectService::stateFor`) so a webhook can only attach an account to the host we sent through Connect; an account can never be re-assigned to another host.
- **R6. Vrbo remains connect-only in the foundation until the beta sign-in and publish job are tested with a throwaway account; do not enable existing-listing content writes because current Vrbo guide and capability matrix conflict.**
- **R7. Cost is passed through deliberately** (T7.1): Repull bills GuestHub per active listing.

---

## 6. What the foundation patch contains (`repull-foundation.patch`, base = `integrations/phase-0-pricelabs-safety`; also applies to `main`)

> **Status of verification:** all PHP files lint clean (PHP 8.3). The framework-free logic (signature verification incl. replay window & rotation, content sanitizer, big-id decoding) passed a standalone 20-check harness. **The Laravel tests were written but NOT run** (no Composer registry in the authoring sandbox) — **first action for the next session: apply, migrate, run `php artisan test --filter=Repull` and `--filter=ListingChannelAccess`, fix any failures, record results in section 11.**

```
config/repull.php                                   REPULL_* config (enabled flag, base_url, key, webhook secret, tolerance)
app/Services/Repull/RepullException.php             errorCode / httpStatus / retryable; notConfigured()
app/Services/Repull/RepullClient.php                Bearer, Idempotency-Key, JSON_BIGINT_AS_STRING, error mapping; methods:
                                                    createConnectSession, createListing, updateListingContent, getPublishStatus,
                                                    publishAirbnb, publishBooking, bookingSetup, airbnbListingAction, bookingPropertyAction
app/Services/Repull/RepullSignature.php             webhook HMAC verify (pure PHP)
app/Services/Channels/ContentSanitizer.php          strips links/emails/phones before OTA push
app/Services/Channels/ListingContentMapper.php      PropertyListing -> create/content payloads, missing() blockers, warnings(), hash()
app/Services/Channels/ChannelPublishBlocked.php     "we refuse to attempt this" (missing fields / no connection)
app/Services/Channels/RepullListingService.php      ensureRemoteListing, pushContent, publish (airbnb|booking), unlist, readiness, per-section result recording
app/Services/Channels/RepullConnectService.php      signed state, Connect session, recordConnection (anti-theft)
app/Jobs/PublishListingToChannel.php                unique per listing x channel; retries only retryable errors
app/Http/Controllers/RepullWebhookController.php    signature + replay check, delivery dedupe, connect/disconnect/problem events
app/Http/Controllers/Admin/ListingChannelController.php   show / connect / connected / publish / unlist / readiness (owner-scoped)
resources/views/admin/listings/channels.blade.php   Channels screen (state badge, errors, locked fields, warnings, sections, buttons)
app/Models/{ChannelConnection,ListingChannelLink,ChannelSyncEvent}.php
database/migrations/2026_11_22_10000{1..4}_*.php    channel_connections, listing_channel_links, channel_sync_events, property_listings.repull_listing_id
routes/web.php                                      admin channel routes + POST /webhooks/repull
bootstrap/app.php                                   CSRF exemption for webhooks/repull
app/Models/PropertyListing.php                      + repull_listing_id fillable, channelLinks()
resources/views/admin/properties/index.blade.php    "Channels (Airbnb, Booking.com)" menu link
.env.example                                        REPULL_* block
tests/Unit/{RepullSignatureTest,ContentSanitizerTest}.php, tests/Feature/{RepullClientTest,RepullWebhookTest,ListingChannelAccessTest}.php
docs/CHANNEL_SYNC_PLAN.md                           this file
```

**Apply:**
```bash
git checkout -b feature/repull-foundation integrations/phase-0-pricelabs-safety   # keep existing branches untouched
git apply --index repull-foundation.patch
php artisan migrate
php artisan test --filter=Repull && php artisan test --filter=ListingChannelAccess
# env (staging first):  REPULL_ENABLED=true  REPULL_API_KEY=sk_live_…  REPULL_WEBHOOK_SECRET=<from webhook creation>
# create the webhook once:  POST https://api.repull.dev/v1/webhooks {"url":"https://<host>/welcome/webhooks/repull","events":["connect.session.completed","account.created","account.disconnected","channel.sync.failed","channel.degraded","listing.suspended"]}
```

**Known gaps in the foundation (by design — each has a task below):** amenities/rooms/beds are **not** mapped yet (Airbnb publish likely needs them); no `property_type`/`room_type` fields; Booking.com `add-room`/`advance`/licence UI missing; per-listing Airbnb `syncCategory` not shown; Vrbo connect-only; no "Import from Airbnb"; no ARI wiring; Connect `accessType: full_access` request and webhook payload nesting are assumptions (V1/V2).

---

## 7. Task list (spec for the next AI)

### Phase 1 — Verify before trusting (the "no dead end" gate) — **do first, with a throwaway OTA test account + the free key (≤3 listings)**
- [ ] **T1.0** Apply patch, migrate, run tests, fix failures, log results.
- [ ] **T1.1 (V1)** Create the webhook (`POST /v1/webhooks`), use `POST /v1/webhooks/{id}/test/connect.session.completed` and `/ping`; **record the real payload** (where `state` is echoed, `account.*` field names, whether a connection id is present). Adjust `RepullWebhookController::accountConnected` + tests. Store the signing secret in `REPULL_WEBHOOK_SECRET`.
- [~] **T1.2 (V2)** Code now uses provider-specific `/v1/connect/airbnb` and `/v1/connect/booking` for full management, plus the documented multi-provider `vrbo-login` and `booking-extranet-login` flows. Hosts can choose Airbnb/Vrbo full vs messaging access; callback state is owner-bound and the connection is confirmed with `GET /v1/connect/{provider}`. Still requires a real Connect round-trip and confirmation of provider response fields.
- [ ] **T1.3 (V3)** Create + publish one test listing to Airbnb and one to Booking.com. Record the exact publish response (is `result` a wrapper?), the **connection-id field** needed for `airbnbConnectionId` updates, and how Booking.com `sellable`/`blockers` are reported. Adjust `RepullListingService::applyResult/publishBooking`.
- [ ] **T1.4 (V4)** Confirm Repull can download photos at `url('/img/…')` **under the `/welcome` sub-path** (public, no auth, https, reasonable size). Fix the URL builder if not.
- [ ] **T1.5 (V6)** Vrbo: verify beta host sign-in and terms; test creating a new listing and separately test whether an existing listing can be republished/edited. Current capability matrix says create is supported but existing content re-push is not. Record actual responses and disclosure wording.
- [ ] **T1.6 (V8/V9)** Email `hello@repull.dev` (platform agreement): confirm the free tier (3 listings; docs say $0 but a chatbot said $99), the "active listing" definition, price at the client's expected host count, SLA, and what happens to hosts if an OTA revokes access. Ask specifically: *is Vrbo access login-based, and is it covered by Vrbo's terms?*
- [ ] **T1.7** Write findings into section 4 and tick the gate. **Do not onboard real hosts before T1.1–T1.5 pass.**

### Phase 2 — Make a publish actually succeed
- [~] **T2.1** Added a per-property amenity editor field for the exact Repull canonical amenity ID; mapper sends only explicitly mapped active amenities, deduplicated. Remaining: build the global amenity catalog/host picker and map existing free-text amenity records. Because Repull treats `amenities` as full replacement, unmapped values are not guessed or pushed.
- [~] **T2.2** Added optional per-listing room layout with room type/number/name, privacy and bed type/quantity; supports multiple bed types in the stored canonical payload and imports full Airbnb bed arrays. Room layout is full-replacement only when the host enables room sync. The editor currently exposes one bed type per room; add multi-bed editor controls before marking complete.
- [~] **T2.3** Added property/room type selectors, bathroom count, check-in/out times, cancellation policy mapping, total beds and publish blockers. Still verify accepted enum values and add `publish-status.addressReadiness` as a live pre-flight once Repull response is available.
- [~] **T2.4** Added `RefreshChannelStatus`: scopes Airbnb listing discovery to the owner, reads per-listing `publish-status` for `syncEnabled`/`lockedFields`, and stores writability/category on the channel link. UI explains that API sync must be enabled on the individual Airbnb listing; test with a real connected account.
- [x] **T2.5** Channel UI distinguishes content-published from live/bookable and warns that a published response is not proof the channel is live.
- [~] **T2.6** Per-listing locked fields are stored and shown on the Channels screen with a clear instruction to edit them on Airbnb. Field-level markers in the listing editor remain to be added.
- [~] **T2.7** Added Booking.com create-property → add-room (requires roomId + rateId) → advance readiness gate; shows blockers through the channel error state, reads regional licence requirements and submits typed licence data, and exposes hotelId override for ambiguous mappings. Needs real Booking.com test and improved structured licence-field editor.
- [~] **T2.8** Initial auto-sync wiring added for published listing edits, relevant property-detail changes and listing-photo changes. Jobs are queued after commit and skip unchanged content hashes. Needs runtime/queue verification and coverage for all canonical content mutations before marking complete.
- [~] **T2.9** Added all-channel offline/online actions with per-channel results and failure aggregation, alongside channel-specific unlist. Needs real vendor verification and confirmation of response shape.

### Phase 3 — Calendar, rates, bookings (ARI) via Repull
- [ ] **T3.1** Staging test: set `CHANNEX_BASE_URL=https://api.repull.dev/channex/api/v1` + `CHANNEX_API_KEY=<Repull key>`; run `php artisan channex:verify`; create property/room type/rate plan with the existing integrations code; push ARI via the outbox; confirm a booking revision webhook arrives and is acked. Read `meta.warnings`. Call `activate` on new channels.
- [ ] **T3.2** Determine the mapping between a Repull-native `listing` id and the Channex-compat `property_id` (not documented). Ask Repull or test; store whichever link is needed so content and ARI target the same property.
- [ ] **T3.3** Migrate any host currently on Channex: use `POST /v1/migrations/channex` (keeps ids) **after disconnecting that host's Airbnb from Channex** (exclusive). Acknowledge unacked Channex revisions first. One host at a time; keep Channex as rollback until verified.
- [ ] **T3.4** If the compat API is not sufficient, implement `RepullAriProvider` (`PmsProviderInterface`) using `PUT /v1/availability/{propertyId}` / `PATCH /v1/availability/batch` and Repull reservations feed; keep `config/pms.php` switchable.
- [ ] **T3.5** Pricing tools: PriceLabs IAPI / Wheelhouse code is already on the integrations branch — it writes through the same ARI path, so re-test after T3.1.

### Phase 4 — Import from Airbnb (client request #4)
- [ ] **T4.1** With Repull (since Channex is no longer on the account): connect Airbnb (`read_only` is enough for import), `GET /v1/channels/airbnb/listings` + `…/{id}/details`, "Refresh from Airbnb" (`/docs/refresh-from-airbnb`). Build `ImportFromAirbnbService` → creates a draft `PropertyListing` (title, description, photos downloaded through the existing photo pipeline, occupancy, amenities → catalog), host reviews, publishes direct site. **No scraping of Airbnb pages.** A "paste your Airbnb link" front door may start the authorization flow only.

### Phase 5 — Vrbo
- [ ] **T5.1** Depending on T1.5: implement documented Vrbo new-listing publish only after a throwaway-account test; support generic ARI only if full access is proven; do not promise editing/re-publishing existing Vrbo content. Show beta/login disclosure.

### Phase 6 — Host-branded direct site (the client's #1 ask; independent of Repull — can run in parallel)
- [x] **T6.1** Added `host_sites` + slug alias tables and migration backfill for owners with published listings; preserves existing `/h/{slug}` host URLs.
- [~] **T6.2** Added 3–40 lowercase slug validation, reserved-word blocking, collision auto-suffixing and permanent redirects through `host_site_aliases`. A live availability checker in the editor remains.
- [x] **T6.3** Added host branding settings, logo upload via `MediaService`, brand color, support email, optional Powered by GuestHub footer, and branded headers on host and individual listing pages. Global `/stays` remains GuestHub-branded.
- [x] **T6.4** Kept the existing `/h/{slug}` route to preserve current URLs and avoid root-route collisions; no new route prefix was introduced.
- [ ] **T6.5 (last)** Custom domains: `host_site_domains` (pending→verified→active), DNS instructions (CNAME + TXT), verification job, host-resolving middleware, canonical = custom domain, block admin/guest routes on custom domains; TLS via Cloudflare for SaaS or on-demand TLS.

### Phase 7 — Money
- [~] **T7.1** Repull is implemented as a host-selectable subscription add-on (`repull_channel_sync`), billed per active OTA listing through the existing Stripe add-on flow and entitlement gate. Admin configures the per-listing price under Packages; a one-listing minimum reserves the add-on before the first publish. Remaining: confirm Repull workspace cost/free-tier rules with the vendor, set the commercial price, and confirm client pass-through policy. At ~15–20+ listings Repull may cost more than Channex.

### Parked (unchanged, not Repull)
- Furnished Finder (Supply API, per-listing $199/yr subscription, 30-day min, awaiting a **human** reply), Zillow feed (PMS approval, no nightly stays, awaiting formal request), PriceLabs PMS listing, Autohost guest screening (+ separate FCRA provider for tenants), NextPax/Rentals United (alternatives), iCal as interim for FF/Zillow. Specs were in the earlier plan; revisit after Phase 3.

---

## 8. Verification gate checklist (copy into the Progress Log when done)

| # | Claim to prove | How | Result |
|---|---|---|---|
| V1 | Webhook payload shape for connect/disconnect | `POST /v1/webhooks/{id}/test/<event>` | |
| V2 | Connect accepts `state`; `full_access` can be requested for Airbnb | real Connect round-trip | |
| V3 | Publish response shape; connection-id field; Booking `sellable`/`blockers` | publish a test listing to each | |
| V4 | Repull can fetch `/welcome`-hosted `/img/...` photos | publish with photos | |
| V5 | Channex-compat ARI works with `ChannexProvider` + outbox; ids map to native listing | staging run (T3.1/T3.2) | |
| V6 | Vrbo existing-listing edits work; new-listing creation unknown | test Vrbo account | |
| V7 | Airbnb create needs nothing beyond `full_access` + postal address + lat/lng | create a test listing | |
| V8 | Free tier = $0 for 3 listings; "active listing" definition | email Repull | |
| V9 | Vrbo access is login-based; terms/ban risk; revocation/SLA | email Repull | |

---

## 9. Blockers / waiting on

| # | Waiting on | Who | Status |
|---|---|---|---|
| B1 | Repull key (free tier) + throwaway Airbnb/Booking.com/Vrbo test accounts | Owner | not started |
| B2 | Repull platform answers (V8/V9) | Owner | not asked |
| B3 | Per-host choice: full Repull channel management (requires Airbnb full-access cutover) or messaging-only alongside existing PMS; confirm host migration and commercial pass-through | Client | review access-mode choice and per-listing add-on price |
| B4 | Client decision: `/<slug>` vs `/h/<slug>` | Client | open |
| B5 | Furnished Finder human reply · Zillow formal request · PriceLabs PMS listing | Client/Owner | emailed; FF/Zillow gave AI auto-replies |
| B6 | Channex production certification (only if Channex stays for non-Repull hosts) | Owner | staging only |

---

## 10. Risks

1. **Login-based Vrbo (and Booking Extranet login)** — account restrictions, not an official partner connection for Vrbo. Disclose; keep Vrbo optional.
2. **Silent non-application on Airbnb** (locked fields, per-listing sync off, instant booking) — success is `blockedFields` empty, never HTTP 200. UI must show it.
3. **Full-replacement sections** (amenities/photos/rooms) — a partial write wipes data. Always send the complete set.
4. **Workspace-wide key** — a bug that skips `owner_id` filtering exposes every host. Two-host tests mandatory.
5. **Cost scaling** — per-active-listing billing; compare with Channex at the client's target size.
6. **Dependence on a small vendor** — adapter seams (`RepullClient`, `RepullListingService`) keep a future swap (NextPax/Rentals United/Channex) bounded.
7. **No sandbox** — every test touches real channels. Throwaway accounts; never experiment on a host's live listing.
8. **`/welcome` sub-path** — callbacks/photo URLs must be built with `route()`/`url()`.

---

## 11. Progress log (append newest at the bottom)

| Date | Session | Done | Next |
|---|---|---|---|
| 2026-11-21 | Research + planning (earlier) | Read repo & all branches; vendor research (Channex, NextPax, RU, Beds24, FF, Zillow, Autohost, screening). | — |
| 2026-11-21 | Repull foundation (authoring) | Read Repull's real docs (llms-full, Channex-compat, for-platforms, Airbnb guide, create/publish). Resolved: Airbnb full-access exclusivity ⇒ Repull must replace Channex per host; Channex-compatible API exists (drop-in for ARI); Vrbo is login-based with section edits on existing listings. Built the foundation patch (section 6) on `feature/repull-foundation` off the integrations branch. All PHP linted; standalone harness 20/20 (signature, sanitizer, big-id decode). **Laravel tests not run (no Composer in sandbox).** Found+fixed: phone regex missed `+1 (305) 555-0142`; 19-digit ids decode as exact ints (cast to string). | **T1.0** (apply, migrate, run tests) → **T1.1–T1.7** verification gate → Phase 2. Phase 6 (branding) can run in parallel. |
| 2026-11-21 | Implementation continuation | Fixed channel unlisting so Repull failures are recorded and returned to the UI instead of being reported as success. Connect webhook now persists the returned account access tier and display name; Channels UI shows the tier. Added a regression assertion for `full_access`. All changes committed directly to `feature/repull-foundation`. | T1.0 runtime migration/tests and T1.1–T1.7 remain unverified: this session has no checked-out repository, Composer, or configured application database (PHP CLI is available) and no Repull production key or throwaway OTA accounts. Continue implementation work; do not mark vendor verification complete without real test results. |
| 2026-11-21 | Phase 2 implementation | Added automatic queued channel re-sync for already-published listings when listing fields, property address/occupancy/amenity fields, or listing photos change. Added a content-hash no-op guard in the publish job. T2.8 is in progress, not verified. | Continue Phase 2 (amenity taxonomy, room/bed mapping, listing fields, per-listing Airbnb sync state, locked fields, Booking setup) and run the Laravel/queue tests when the repository environment is available. |
| 2026-11-21 | Phase 7 billing implementation | Added `repull_channel_sync` as a configurable feature/add-on in the existing package/subscription system. Added `per_channel_listing` quantity resolution scoped to the host's active published/live/partial listings, one-listing minimum to allow initial activation, admin pricing configuration, plan-page quantity display, subscription entitlement checks on connect/publish/unlist/readiness, and tests for unique listing counting and host isolation. Vendor price confirmation and commercial price remain external decisions. | Continue Phase 2–6 implementation; confirm Repull vendor billing and choose the host-facing per-listing price before enabling the add-on for real customers. |
| 2026-11-21 | Phases 2, 4 and 6 implementation | Added OTA property/room types, bathroom/bed/check-in/out inputs, mapped amenities, optional room/bed layout, per-listing Airbnb sync refresh, published-vs-live/locked-field UI, Booking.com setup/readiness/licence/hotel-id handling, all-channel offline/online, Airbnb draft import with photo download, and host-branded pages with slug aliases. Tasks are marked partial where runtime/vendor checks or richer editing remain. | Complete Channex-compatible ARI routing and verification (Phase 3), real import and channel tests, Vrbo publish decision (Phase 5), custom domains (Phase 6.5), then run all Laravel tests/migrations locally. |
