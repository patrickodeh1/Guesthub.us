# GuestHub Integration Plan

Audience: an AI coding agent working inside this repo. Follow this file top to bottom. Do not skip phases or declare "done" until the phase's acceptance checks pass.

Goal: every outside service GuestHub uses supports many hosts correctly. Providers in scope: Wheelhouse, PriceLabs, Seam, Channex, Stripe Connect.

OUT OF SCOPE, DO NOT MODIFY: Telnyx (text messages) and Google (Vision, Maps). They work as they are and the owner has decided to leave them alone. Do not edit, refactor, add tests to, or "harden" any of their code, config or env vars (`SmsNotificationService`, `CleaningSmsNotificationService`, `SmsConsentService`, the `telnyx`, `google_maps`, `google` and `google_vision` entries in `config/services.php`, and anything that calls them). If a task in another phase seems to require touching them, stop and ask the human.

---

## 0. Rules for the implementer

1. Work one phase at a time, in the order in section 3. One phase = one commit (or small PR) with its tests.
2. Read the provider's official docs (links in each phase) before writing client code. Do not guess endpoint paths, parameter names or response fields. If the docs and this plan disagree, the docs win; note the difference in your summary.
3. Never call real provider APIs in tests. Use `Http::fake()` with fixtures that copy the documented response shapes.
4. Never log, echo, or return secrets (API keys, tokens, webhook secrets). Store host-supplied keys encrypted (Eloquent `encrypted` cast). Show only a masked value (last 4 characters) in the UI.
5. The app is served under a sub-path (`/welcome`, i.e. `guesthub.us/welcome`). Always build redirect and webhook URLs with `route()` or `url()`. Never hardcode a domain or assume the app is at the root.
6. Every provider credential has exactly one owner (see section 2). A host must never be able to see, use, or affect another host's credentials, listings, devices or properties. Every phase must include a two-host isolation test.
7. Do not touch unrelated features. Do not rename existing columns or routes unless a phase says so. Add migrations; never edit old ones.
8. Anything you cannot verify (needs a live account, a human, or an approval), list under "Not verified" in your phase summary. Do not claim it works.
9. When a phase finishes, update `todos.md` / `completed-todos.md` for the todo items it covers (item numbers are listed per phase), and add an entry to the env var table in section 5.
10. If a "Questions" item (section 6) blocks you, stop and ask the human. Do not invent a decision.
11. Never modify Telnyx or Google code or config (see the out-of-scope note at the top).

Run tests with `php artisan test` (PHPUnit, see `phpunit.xml`). Existing tests live in `tests/Feature`.

---

## 1. Current state (verified in the repo)

Pricing and channel flow today:

```
RateSource (PriceLabsRateSource | WheelhouseRateSource)
  -> PriceLabsSync::apply  -> AvailabilityLedger
  -> channex_outbox table
  -> artisan channex:push-outbox (every minute) -> Channex -> Airbnb/Vrbo/Booking.com
```

Schedules in `routes/console.php`: `pricelabs:sync` every 6 hours, `wheelhouse:sync` every 4 hours, `channex:push-outbox` every minute, `channex:compare` nightly.

| Service | Credential today | Where | Problem |
|---|---|---|---|
| PriceLabs | Host's own key in `users.pricelabs_api_key` (encrypted), with a fallback to the shared `PRICELABS_API_KEY` | `PriceLabsRateSource::apiKeyFor`, `PropertyAvailabilityController` | The shared-key fallback mixes hosts. Pull-only. The listing must already exist in the host's PriceLabs account |
| Wheelhouse | Partner key `WHEELHOUSE_PARTNER_API_KEY` + per-host user key (`users.wheelhouse_user_api_key`) via the partner API | `WheelhouseApiClient`, `WheelhouseConnectionService`, `WheelhouseRateSource` | Needs a partner key GuestHub does not have; does not push the calendar/reservations the partner API requires |
| Seam | One platform key `SEAM_API_KEY`; the admin pastes a device ID | `SeamService`, `PropertyLockController::store`, `property_locks.seam_device_id` | No way for a host to connect their own lock account |
| Channex | One platform key `CHANNEX_API_KEY`; base URL defaults to staging | `ChannexProvider`, `ChannexWebhookController` | Properties/rooms/rate plans are linked by pasted IDs, not created by the app; no host channel-connection screen; staging only |
| Stripe | Platform keys + Connect OAuth (`users.stripe_account_id`) | `StripeConnectController`, `StripeWebhookController` | Connect client ID and Connect webhook secret not yet configured or live-tested; disconnect not handled |
| Telnyx | Platform key and one shared `TELNYX_FROM_NUMBER` | `SmsNotificationService` | OUT OF SCOPE. Leave as is |
| Google | Platform keys (Vision for ID scans, Maps) | `config/services.php` | OUT OF SCOPE. Leave as is |

Already built, do not redo: a per-property either/or choice exists (`properties.pricing_provider` = `pricelabs` or `wheelhouse`, validated in `PropertyAvailabilityController`, tested in `tests/Feature/PricingProviderTest.php`).

---

## 2. Target state (decisions already made by the owner)

| Service | Who holds the credential | How hosts connect |
|---|---|---|
| Channex | GuestHub (one platform key) | Hosts connect their own OTAs from GuestHub through Channex's channel iFrame; GuestHub creates properties in Channex by API |
| Seam | GuestHub (one workspace key) plus each host's own lock account | Host signs in to their lock brand through a Seam Connect Webview, then picks which lock belongs to which property |
| Wheelhouse | Each host (their own Wheelhouse key) | Host creates a key in Wheelhouse and pastes it into GuestHub. GuestHub only pulls price recommendations. No partner key, no accounts created by GuestHub |
| PriceLabs | Each host, until GuestHub is a certified PMS in PriceLabs | Interim: host pastes their key (only works if the property is already in their PriceLabs account via another connection). Target: GuestHub listed as a PMS in PriceLabs; the host selects GuestHub there |
| Stripe | Each host's own connected account | Host connects through Stripe Connect OAuth |
| Telnyx | GuestHub (one shared number) | Out of scope. Do not change |
| Google | GuestHub | Out of scope. Do not change |

---

## 3. Order of work and blockers

| Phase | Work | Size | Blocked by (human) |
|---|---|---|---|
| 0 | Safety fixes (PriceLabs shared key, isolation tests) | S | none |
| 1 | Wheelhouse: switch to host key, RM API | M | none |
| 2 | Seam: Connect Webview and device picker | M | Seam workspace key and redirect URLs |
| 3 | Channex: production readiness, property creation, channel iFrame | L | Channex production account |
| 4 | Stripe Connect: verify, add disconnect handling | S | Client ID + Connect webhook secret in env |
| 5 | PriceLabs: certified PMS integration (IAPI) | L | PriceLabs approval and credentials |
| 6 | Wrap-up: docs, env table, todo updates, final matrix | S | none |

Phase 5 starts early without credentials (build behind a flag), but cannot finish without them.

---

## Phase 0. Safety fixes

Todo items: 83, 112, 111.

Do:
1. Remove the shared-key fallback. In `PriceLabsRateSource::apiKeyFor`, return only `$property->owner?->pricelabs_api_key`. Remove the fallback in `PropertyAvailabilityController` (the `priceLabsKeySet` value and the default used by `verifyPriceLabsKey`). Remove `pricelabs.api_key` from `config/services.php` and `.env.example`.
2. `pricelabs:sync` and the controller must skip a property whose owner has no key, with status `skipped` and a clear message ("Add your PriceLabs API key").
3. Add an artisan command `integrations:report-shared-key-usage` that lists properties with `pricing_provider = 'pricelabs'` whose owner has no key (these relied on the shared key). Do not silently change their data. Output goes to the console for the human.
4. Confirm switching `pricing_provider` clears the other provider's linkage fields so a property is never linked to both. Extend `PricingProviderTest` if needed.

Acceptance:
- A host without a key can never list or link another host's PriceLabs listings (test with two owners).
- No code path reads `services.pricelabs.api_key`.
- Existing PriceLabs tests still pass.

---

## Phase 1. Wheelhouse (host key, RM API)

Todo items: 83, 111, 112.

Docs to read first:
- https://help.usewheelhouse.com/en/articles/15468234-getting-started-with-the-wheelhouse-rm-api
- https://docs.usewheelhouse.com/rm/reference/wheelhouse-revenue-management-api
- https://help.usewheelhouse.com/en/articles/15545621-how-to-sync-price-recommendations-to-your-system

Facts from those docs (verify against them):
- A host creates a key in their own Wheelhouse account under API & MCP, then API Keys. One key authenticates every call in the `X-Integration-Api-Key` header. No partner key and no OAuth.
- A read-only key can pull data but cannot change anything. GuestHub only pulls, so tell hosts to create a read-only key.
- Base URL: `https://api.usewheelhouse.com/ss_api/v1`. List listings with `GET /listings` (supports `per_page` up to 100 and `page`). Each listing has `id`, `channel`, `title`, `is_active`. Most endpoints need both `listing_id` and `channel`.
- Price recommendations: `GET /listings/{listing_id}/price_recommendations?channel=...`.
- Wheelhouse gets calendar and reservations from the host's own channel connection, so GuestHub does not push them.
- The RM API is in beta and may become a paid feature. Code defensively and surface clear errors.

Do:
1. `WheelhouseApiClient`: send the host's key as `X-Integration-Api-Key`. Remove the `X-User-API-Key` header, the partner-key requirement, `registerUser` and `upsertListing`. Methods: `listings($key)` with pagination (loop until a page returns fewer than `per_page`), `recommendations($listingId, $key, $channel)`.
2. Reuse `users.wheelhouse_user_api_key` to store the host's RM key. Confirm it uses the `encrypted` cast; add it if missing. Stop using `users.wheelhouse_integrators_user_id` (leave the column; mark it deprecated in a code comment).
3. `PropertyAvailabilityController`: keep `saveWheelhouseKey` and `saveWheelhouseListing`. `saveWheelhouseKey` validates by calling `GET /listings?per_page=1`: 200 saves the key; 401/403 shows "That key was not accepted" and saves nothing. Remove the `connectWheelhouse` action and its route (`properties.availability.connect-wheelhouse`) and any button for it.
4. After the key is saved, show the host's Wheelhouse listings (title, channel) and let them pick one for the property. Store `wheelhouse_listing_id` and `wheelhouse_channel` (both are required by the API).
5. `WheelhouseRateSource::fetch`: keep the existing mapping into `[date, rate, min_stay]`, but confirm the real response field names against the RM reference and update the parser plus a fixture test if they differ. Handle 401/403 (status `unauthorized`), 429 (back off and retry), 5xx (retry then `failed`).
6. Deprecate `WheelhouseConnectionService` (delete it if nothing else uses it) and `WHEELHOUSE_PARTNER_API_KEY` in `config/services.php` and `.env.example`. Inspect `WheelhouseWebhookController` and route `webhooks.wheelhouse`: if it only serves partner-registered users, remove it; otherwise keep and document.
7. UI help text next to the key field: where to create the key in Wheelhouse, and that a read-only key is enough. Never display the full key.

Acceptance:
- A host with a valid key and a picked listing gets rates through `wheelhouse:sync` into the ledger and the Channex outbox.
- Two hosts with different keys never see each other's listings or rates (test).
- Invalid or revoked keys show a clear message and do not crash the scheduler.
- No code reads `services.wheelhouse.partner_api_key`.
- Tests use `Http::fake()` fixtures.

Not in scope: pushing calendar/reservations to Wheelhouse, creating Wheelhouse accounts, editing Wheelhouse preferences.

---

## Phase 2. Seam (Connect Webview and device picker)

Todo items: 83 (credential ownership), lock-related items in `todos.md`.

Docs to read first:
- https://docs.seam.co/api/connect_webviews/create.md
- https://docs.seam.co/api/connect_webviews/object.md
- https://github.com/seamapi/api-docs/blob/main/docs/core-concepts/connected-accounts/README.md
- https://help.getseam.com/articles/8312445562-setting-up-customer-connections-with-connect-webviews

Facts from those docs (verify): a Connect Webview is a Seam-hosted screen where the end user signs in to their lock brand. You can tag it with your own customer ID. Seam creates a connected account and its devices appear under it, so the app lists devices by connected account.

Do:
1. Migration: table `seam_connected_accounts` with `id`, `user_id` (host), `seam_connected_account_id` (unique), `provider` (nullable string), `status` (`connected`, `needs_reconnect`, `disconnected`), `last_synced_at`, timestamps.
2. `SeamService`: add `createConnectWebview(User $host, string $successUrl, string $failureUrl)` using customer key `gh-{user_id}`, `getConnectWebview($id)`, `listDevices($connectedAccountId)`. Keep the existing methods. Read the docs for exact parameter names; do not guess.
3. Routes under the existing admin property/user area, limited to roles admin, owner, company:
   - `POST locks/connect` creates the webview and redirects the host to Seam.
   - `GET locks/connect/return` confirms the webview is authorized, stores the connected account, syncs devices.
   - `GET locks/connect/failed` shows a friendly error.
4. Property lock form (`resources/views/admin/properties/form.blade.php`): replace the free-text device ID with a dropdown of the host's devices that are not yet assigned (`seam_device_id` is unique). Keep manual device ID entry for the admin role only, as a fallback.
5. Authorization in `PropertyLockController::store/update`: a non-admin may only assign a device that belongs to one of their own connected accounts. Verify through Seam, not through the form input. Admins may assign any device.
6. `SeamWebhookController`: handle the connected-account events that mean the host's connection broke or was removed (check the docs for event names). Set the status to `needs_reconnect` or `disconnected` and show a "Reconnect" prompt. Do not delete locks.
7. Host-facing "Lock accounts" section on the property or profile screen: connect, reconnect, list connected accounts and devices, battery level.

Acceptance:
- A host connects a lock account (use a Seam sandbox device or `Http::fake()` for tests) and assigns a device to a property without ever seeing a device ID.
- A host cannot assign another host's device (test with two hosts).
- Existing guest unlock/lock and session door routes still work unchanged.
- Billing quantity per device still counts correctly (`BillingQuantityResolver` tests pass).

Not in scope: guest access codes (`createGuestAccessGrant` stays unused), per-device billing prices.

---

## Phase 3. Channex (production, property creation, channel iFrame)

Todo items: 14, 15, 26.

Docs to read first:
- https://docs.channex.io/guides/pms-integration-guide.md
- https://docs.channex.io/llms-full.txt (find the sections on property creation, one-time tokens and the channel iFrame)

Facts (verify): a PMS normally holds one Channex account with many properties and one API key. Channex offers an iFrame where users connect channels and map listings themselves. Production requires Channex certification and OTA account authorization.

Do:
1. Config: keep `CHANNEX_BASE_URL` configurable. Add `php artisan channex:verify` that prints the active base URL, whether it is staging or production, and whether the key works. Make the admin UI show an "Environment: staging/production" badge to admins only. Do not change the default silently; document the production value in `.env.example`.
2. `ChannexProvider`: add `createProperty`, `createRoomType`, `createRatePlan` (and the update/delete calls the guide requires). Use the exact endpoints from the docs. Store results in `properties.channex_property_id`, `channex_room_type_id`, `channex_rate_plan_id`. Make it idempotent: if the IDs already exist, do not create again. If creation fails halfway, store what exists and report the failure.
3. Property settings screen: an admin/owner action "Create in Channex" that runs the calls above and shows progress and errors. Log each call in `channex_push_logs` as the existing code does.
4. Channel connection: add a server action that requests the one-time token the docs describe and a screen that embeds the Channex iFrame for that property. Only the property's owner or an admin may open it. Token and iFrame URL must be created per request and never stored.
5. Isolation: every Channex call must use the IDs stored on the specific property. Confirm `ChannexWebhookController` and `BookingImportService` map incoming events by those IDs only. Add a test with two hosts' properties that proves a webhook for host A never changes host B's data.
6. Keep existing pushing (`pushAvailability`, `pushRestrictions`, the outbox) unchanged.

Acceptance:
- On staging: a new property goes from nothing to created in Channex through the button, with no pasted IDs.
- The iFrame opens for the right property; a different host gets a 403 for it.
- The two-host webhook test passes; `ChannexProviderTest` still passes.
- `channex:verify` reports environment and key status.

Not verified (human, list it in your summary): production account, Channex certification, real OTA authorization.

---

## Phase 4. Stripe Connect (verify and complete)

Todo items: 60 (guest payments stay with the host), 83.

Existing: `StripeConnectController` (OAuth connect, `account.updated`, payment intent events), `StripeWebhookController` (platform events: payment intents and subscriptions). Config keys: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_CLIENT_ID`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CONNECT_WEBHOOK_SECRET`.

Docs: https://docs.stripe.com/connect/oauth-standard-accounts (Stripe now recommends Connect Onboarding for Standard accounts; keep OAuth for now and note a later migration).

Do:
1. Handle `account.application.deauthorized` in `StripeConnectController::webhook`: clear `stripe_account_id`, `stripe_charges_enabled`, `stripe_payouts_enabled` for the matching user, and log it. Confirm in the docs that this event is delivered to the Connect webhook endpoint.
2. Audit every place a guest or lease payment is created. Each must be created on the host's connected account (`stripe_account`), never on the platform account. Add tests that fail if a host payment is created without a connected account.
3. If the host has no connected account or `stripe_charges_enabled` is false, card payment must be blocked with a clear message (see `PayByCcGatingTest`). Extend if needed.
4. Add a short checklist at the top of `StripeConnectController` or in docs for the human: client ID, redirect URI (`route('admin.connect.return')`), Connect webhook URL (`route('webhooks.stripe.connect')`) listening to events on connected accounts.

Acceptance: tests for the cases above pass. Not verified (human): a live test-mode connection and a real payment through a connected test account.

---

## Phase 5. PriceLabs as a certified PMS (IAPI)

Todo items: 14, 66, 83, 111, 112.

Blocked by the human for live activation only: the owner must apply at https://survey.zohopublic.com/zs/P6BUXk to receive `X-INTEGRATION-NAME` and `X-INTEGRATION-TOKEN`. The v2 Swagger schema and base URL have been supplied, so implementation is no longer blocked on schema access. Build behind config so it stays off until credentials exist.

Docs supplied/read for this phase:
- https://help.pricelabs.co/portal/en/kb/articles/building-an-integration-with-pricelabs
- https://help.pricelabs.co/portal/en/kb/articles/how-to-integrate-pricelabs-as-a-pms-via-iapi
- PriceLabs API Integration v2.0.0 Swagger (OAS 2.0), supplied by the project owner. Base URL: `https://api.pricelabs.co/v2/integration/api`.

How it works:
- The PMS calls PriceLabs with two headers: `X-INTEGRATION-NAME` (constant) and `X-INTEGRATION-TOKEN`.
- The PMS hosts three mandatory URLs and registers them with PriceLabs through the `/integration` endpoint: sync URL (PriceLabs pushes prices and settings), calendar trigger URL (PriceLabs asks for a full refresh of given listing IDs), hook URL (PriceLabs sends errors and notifications). PriceLabs supports only one set of URLs per integration, so GuestHub routes by listing ID internally.
- The PMS pushes listings (`/listings`), calendar (`/calendar`) and reservations (`/reservations`). `/get_prices` is optional and must only be called after a sync trigger, never on a cron.
- The host must already be registered with PriceLabs and enables GuestHub as their PMS. The host's PriceLabs account email goes in the `user_token` field when pushing a listing.
- Every `listing_id` must be unique across all GuestHub hosts and belong to exactly one host.
- A new listing's first calendar push needs at least 730 days of price and availability. Later pushes can be deltas.
- Calendar blocks (owner blocks, maintenance) go into `/reservations` as cost 0 with status `BLOCKED`.
- PriceLabs signs its requests to GuestHub. Verify before parsing:
  - `header_components = "v1:" + X-SOURCE + ":" + X-PL-TIMESTAMP + ":" + X-PL-REQUESTID`
  - `signed_headers = "v1." + HMAC_SHA256(header_components, token)`
  - The published v2 test vector signs the body using `signed_headers` followed by the JSON body with quotes escaped once; preserve the exact documented byte representation and compare with `hash_equals` to `X-PL-SIGNED-HEADERS` and `X-PL-SIGNED-BODY`.
  - Reject missing headers, unsupported signature versions, altered bodies, and altered headers.
  - Test vector from PriceLabs: token `26eea40c-f297-4b68-aeb8-bb626fe4b5e5`, `X-SOURCE=request_source`, `X-PL-TIMESTAMP=request_timestamp`, `X-PL-REQUESTID=request_id`, body `{"test":"body"}`. Expected signed headers `v1.567425e5a91b576652d591cabea78e9e1fe017d1c67e2d812ae2859c355965ff`; expected signed body `5011b348ff6691bb9cbe93f85ffb836ef0e3fec45a4ce924f5940cb1aa4071b8`. This must be a unit test.

Do:
1. Config in `config/services.php` under `pricelabs`: `integration_name`, `integration_token`, `iapi_base_url` (v2 base URL above). The IAPI features are enabled only when all three are set. Add to `.env.example`. The base URL is now known; the integration name/token remain external credentials.
2. Migration: `users.pricelabs_account_email` (host's PriceLabs registered email), `properties.pricelabs_iapi_listing_id` (unique, nullable), `properties.pricelabs_mode` (`key` or `pms`, default `key`).
3. New classes under `app/Services/Pms/PriceLabs/`: complete `PriceLabsIapiClient` for the Swagger operations `/integration`, `/listings`, `/sync_status`, `/calendar`, `/get_prices`, `/rate_plans`, `/reservations`, `/status`, and the integration callback-URL configuration operations. Set the HTTP timeout to 300 seconds, respect 429 `Retry-After`, and keep within 300 requests/minute per partner token. Complete `PriceLabsSignature` verification and `PriceLabsIapiSync` payload builders from `Property`, availability ledger and bookings.
4. Listing ID: `gh-{environment}-{property_id}`. It must map back to exactly one property.
5. Inbound controller `PriceLabsIapiController` with three actions, CSRF-exempt, throttled: `sync`, `calendarTrigger`, `hook`. Routes named `webhooks.pricelabs.sync`, `webhooks.pricelabs.calendar-trigger`, `webhooks.pricelabs.hook`. Verify the signature first and return 401 on failure. `sync` converts the pushed prices into the same `[date, rate, min_stay]` shape the existing `RateSource` uses and passes it to `PriceLabsSync::apply`, so prices reach the ledger and the Channex outbox exactly as today. `calendarTrigger` queues a full calendar push for the listed IDs. `hook` stores the notification (new table or `activity_logs`) and shows host-facing errors.
6. Artisan command `pricelabs:register-integration` that calls `/integration` with the URLs from `route()` and saves the returned token to a config-backed store (do not print it). Run by a human.
7. Host UI on the property availability panel: choose mode `pms`, enter the PriceLabs account email, press "Send listing to PriceLabs". Then show status (listing pushed, calendar pushed, last price received, last error). Keep mode `key` working as the interim path from Phase 0.
8. Push triggers: on enabling, push `/listings` then `/calendar` (730 days). On booking create/update/cancel, push `/reservations` then the affected calendar days. Reuse the outbox pattern if it fits; otherwise a queued job. Rate and availability changes push deltas.
9. When a property is in `pms` mode, `pricelabs:sync` must not also pull with a key.

Acceptance (before certification):
- Signature unit test passes with the PriceLabs test vector; tampered body or headers are rejected.
- Two-host test: a sync push for host A's listing ID changes only host A's property.
- Payloads match the Swagger field names (fixtures copied from the docs).
- With credentials unset, the feature is invisible and nothing calls PriceLabs.

Human steps after the build (list them in your summary, do not do them): get credentials, run `pricelabs:register-integration` on the test URLs, complete PriceLabs' certification checklist (https://docs.google.com/spreadsheets/d/1nSzDMLMneX3Byukieo9CYhbXksYIL_NfT87tmBA5aP4/edit) with their team, switch to production URLs, go live with selected hosts.

---

## Phase 6. Wrap-up

1. Create `INTEGRATIONS.md` in the repo root: for each in-scope service (Wheelhouse, PriceLabs, Seam, Channex, Stripe), who owns the credential, the env vars, how a host connects, how it is tested, and the human steps still open. Do not document, inspect or change Telnyx or Google beyond one line saying they are unchanged.
2. Update `.env.example` with every new, changed or removed variable.
3. Move finished todo items from `todos.md` to `completed-todos.md` following the existing format, and add verification notes ("needs live test") for anything unverified.
4. Produce the final matrix below, filled in.

---

## 4. Definition of done (per provider)

| Provider | Done when |
|---|---|
| Wheelhouse | Host pastes a read-only key, picks a listing, rates flow to the ledger and Channex outbox; two-host test passes; no partner key anywhere |
| PriceLabs | Phase 0 done (no shared key). IAPI build finished with passing signature and isolation tests. Certification and go-live are listed as open human steps until PriceLabs completes them |
| Seam | Host connects a lock account and assigns devices from a dropdown; host cannot assign another host's device; disconnects are surfaced |
| Channex | Property created by API; iFrame connect works for the right host; two-host webhook isolation test passes; `channex:verify` works; production and certification listed as open human steps |
| Stripe | All host payments go to connected accounts (tested); deauthorization handled; live test-mode check listed as an open human step |

Final summary must contain a table with, per provider: what was built, tests added, and "Not verified" items.

---

## 5. Environment variables

| Variable | Status | Owner |
|---|---|---|
| `PRICELABS_API_KEY` | Remove (Phase 0) | |
| `WHEELHOUSE_PARTNER_API_KEY` | Remove (Phase 1) | |
| `WHEELHOUSE_WEBHOOK_SECRET` | Remove if the webhook is removed | |
| `SEAM_API_KEY`, `SEAM_WEBHOOK_SECRET` | Keep | GuestHub |
| `CHANNEX_API_KEY`, `CHANNEX_BASE_URL`, `CHANNEX_WEBHOOK_SECRET` | Keep; switch to production when ready | GuestHub |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Already set | GuestHub |
| `STRIPE_CLIENT_ID`, `STRIPE_CONNECT_WEBHOOK_SECRET` | New, set by the owner when ready | GuestHub |
| `PRICELABS_INTEGRATION_NAME`, `PRICELABS_INTEGRATION_TOKEN`, `PRICELABS_IAPI_BASE_URL` | New (Phase 5); stays empty until PriceLabs issues credentials | GuestHub |
| `TELNYX_*`, Google keys | Out of scope. Do not change | GuestHub |

---

## 6. Questions to ask the human (do not guess)

1. Is there a Seam production workspace key, and which lock brands must be enabled for hosts?
2. Does a Channex production account exist, and who submits the certification?
3. What is the correct `CHANNEX_BASE_URL` for production?
4. Should properties that relied on the shared PriceLabs key (Phase 0 report) be contacted, switched to Wheelhouse, or left unlinked?
5. For Wheelhouse, if the RM API response shape differs from the existing parser, confirm the field mapping before deleting the old code path.
6. Should the Wheelhouse webhook route be removed or kept (Phase 1 step 6)?