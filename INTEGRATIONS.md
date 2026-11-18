# GuestHub external integrations

This document records the integration boundaries, credential ownership, setup steps, and verification status for the integration work on this branch.

## Credential ownership

| Integration | GuestHub-owned configuration | Host-owned connection or credential | Isolation rule |
|---|---|---|---|
| PriceLabs legacy/key mode | None. There is no shared `PRICELABS_API_KEY` fallback. | Each host's PriceLabs API key is stored encrypted on the host's User record. | A property can only use its owner's key. |
| PriceLabs IAPI | `PRICELABS_INTEGRATION_NAME`, `PRICELABS_INTEGRATION_TOKEN`, and `PRICELABS_IAPI_BASE_URL`. | The host supplies their PriceLabs account email (`pricelabs_account_email`). | Listing IDs are unique per GuestHub property and environment. IAPI is disabled unless all three GuestHub credentials are configured. |
| Wheelhouse RM API | `WHEELHOUSE_BASE_URL`; the legacy webhook signing secret remains `WHEELHOUSE_WEBHOOK_SECRET`. | Each host supplies one read-only RM API key, encrypted on the host's User record. | Requests use only that host's `X-Integration-Api-Key`. There is no partner API key fallback. |
| Seam smart locks | `SEAM_API_KEY` and `SEAM_WEBHOOK_SECRET`. | Each host connects their Yale or August account through Seam Connect Webview. | Connected accounts are stored against the property owner. Device listing is scoped to that connected account; a host cannot assign another host's device. |
| Channex | `CHANNEX_API_KEY`, `CHANNEX_BASE_URL`, `CHANNEX_DEFAULT_CURRENCY`, and `CHANNEX_WEBHOOK_SECRET`. | No per-host Channex API key. Hosts connect channels for their own property through the property-scoped iframe. | Every property, room type, rate plan, and iframe token is scoped to the selected GuestHub property. |
| Stripe Connect | GuestHub's platform API keys, OAuth client ID, platform webhook secret, and Connect webhook secret. | Each host authorizes their own Stripe Standard account through OAuth. | Guest deposit and itemized charge intents are created on the property's connected account; disabled or missing host accounts are blocked. |

**Secrets must be configured in the server environment or secret manager.** Do not paste them into source control, issue comments, chat, or test fixtures.

## Environment variables

### PriceLabs key mode and IAPI

- Host key mode uses the encrypted `users.pricelabs_api_key` field. There is intentionally no global `PRICELABS_API_KEY` setting.
- IAPI configuration uses:
  - `PRICELABS_INTEGRATION_NAME`
  - `PRICELABS_INTEGRATION_TOKEN`
  - `PRICELABS_IAPI_BASE_URL`
- PriceLabs API Integration v2.0.0 (OAS 2.0) has now been supplied for this implementation. Its base URL is `https://api.pricelabs.co/v2/integration/api`; configure that value as `PRICELABS_IAPI_BASE_URL`. The Swagger defines integration, listings, sync status, calendar, get-prices, rate-plans, reservations, status, sync-URL, calendar-trigger-URL, and hook-URL operations, plus the request/response models. The schema blocker recorded in the earlier draft is resolved.
- PriceLabs must still issue GuestHub's `X-INTEGRATION-NAME` and `X-INTEGRATION-TOKEN`. Onboarding requires PMS name, commercial/custom PMS classification, development account email, and commercial contact/email. The host's PriceLabs account email is sent as `user_token`; use the development account email during testing and the host-provided account email in production.
- The API limit is 300 requests/minute per partner token. Handle HTTP 429 using `Retry-After`; use a 300-second client timeout for long calendar operations. Send one listing per calendar request. New listings require at least 730 days of initial calendar data.
- The signature verifier implements the published HMAC-SHA256 header/body scheme and includes PriceLabs' published signature vector. The three inbound callback URLs (sync, calendar trigger, hook) are registered through the integration API; they are not credentials. PriceLabs sends `{"verify":true}` to validate each callback URL, which must return HTTP 200. Signed callback requests must be verified before processing.
- The IAPI HTTP client supports the Swagger operations used by GuestHub: integration, listings, calendar, reservations, get-prices, status, sync-status, and rate-plans. It sends both partner headers, uses a 300-second timeout, retries transient connection/server failures, and honours `Retry-After` on 429 responses. A shared cache-backed limiter enforces the 300-request/minute partner-token ceiling; multi-server deployments must use a shared Laravel cache store (such as Redis or database cache) for this limit to be global.
- The three inbound callback routes are `webhooks.pricelabs.sync`, `webhooks.pricelabs.calendar-trigger`, and `webhooks.pricelabs.hook`. The callback controller accepts PriceLabs' unsigned `{"verify":true}` URL-verification probe, verifies HMAC signatures on real requests before parsing, maps `data[].price`, `date`, and `min_stay` into GuestHub's availability ledger, requests a full calendar refresh for the referenced listing, and records hook notifications/errors against the matching listing. Callback URLs are registered through `php artisan pricelabs:register-integration`; the command enables only `min_stay`, because other incoming PriceLabs settings are not yet consumed.
- Host setup is available on the property Availability page when IAPI credentials are configured. The host enters their PriceLabs account email; GuestHub registers the listing, sends a 730-day initial calendar (one listing per request), and backfills reservations from 2020-01-01. The calendar requires a nightly rate for every date; it uses the stored date rate or the listing's base rate. GuestHub currently sends USD and models each listing as one unit. The host-facing page shows listing/calendar/last-price timestamps and the latest provider error.
- Booking create/update/cancel events enqueue a reservation sync and a calendar delta for the affected stay. Other ledger availability/rate changes also enqueue a calendar delta; incoming PriceLabs rates are explicitly excluded from this outbound trigger to prevent echo loops. These queued jobs require the application's queue worker to be running. Host-entered blocked dates are sent as zero-cost blocked reservations; reopening a previously sent manual block sends a matching cancelled reservation. `PriceLabsIapiSync::applyForListingId()` protects manually entered rate dates and applies incoming prices through the existing AvailabilityLedger/Channex outbox path. Properties in `pricelabs_mode=pms` are skipped by legacy key-based polling.
- **Not verified in this editing session:** PHP lint, focused PHPUnit tests, route registration under the deployed `/welcome` sub-path, queue-worker execution, MySQL migration, live PriceLabs requests, callback URL verification, and PriceLabs certification/pilot. The partner integration name/token must be issued by PriceLabs before the integration can be registered or used. Do not enable production IAPI until local tests pass and the PriceLabs pilot/certification is complete.
- The inbound Swagger adapter maps the documented `listing_id` plus `data[]` rows (`date`, `price`, optional `min_stay`) into the normalized ledger shape. The `/get_prices` endpoint remains optional and is not polled by cron.
### Wheelhouse

- Hosts create a read-only RM API key in Wheelhouse and enter it in the property availability settings.
- Listing discovery uses the host's key and paginates results. The selected listing's channel is stored with the listing ID.
- Recommendations are read from the RM API and mapped using `data[].stay_date`, `price`, and `min_stay`.
- `WHEELHOUSE_PARTNER_API_KEY` and the old partner-registration flow are removed.
- The existing Wheelhouse webhook route and `WHEELHOUSE_WEBHOOK_SECRET` are retained pending an explicit owner decision about retiring that endpoint. The legacy webhook controller does not reintroduce a shared partner API key.

### Seam — Yale and August only

- Accepted Connect Webview providers are `yale` and `august`.
- Each property uses the owner's Seam customer key (`gh-{owner_id}`).
- Seam redirects back to the authenticated property settings flow. The callback verifies the webview, customer key, provider, and connected-account ownership before saving it.
- Device discovery is account-scoped. Host-facing UI displays lock labels/provider/battery, not raw Seam device IDs.
- Seam's documented `connected_account.reauthorization_requested` event changes the host account to `needs_reconnect`; `connected_account.connected` restores `connected`. Both update `last_synced_at` and are logged without deleting assigned lock records. Seam's public changelog does not document a separate `connected_account.disconnected` event, so no handler for that event is assumed.
- Server setup still requires a Seam workspace API key, webhook secret, and registered callback/webhook URLs.

### Channex

- The default API base URL remains staging: `https://staging.channex.io/api/v1`.
- Channex's documentation uses `https://app.channex.io/api/v1` for production. Production must not be enabled until the production account and certification are ready.
- Set `CHANNEX_DEFAULT_CURRENCY` to the currency used for new Channex properties. The current default is `USD`; change it before creating properties in another currency.
- A two-letter ISO country code is stored on the GuestHub property and sent during Channex property creation. Google Places fills it when available; otherwise the host must enter it.
- The create action is resumable: successful Channex IDs are saved after each step, so retrying continues without intentionally creating duplicates.
- `php artisan channex:verify` prints the configured base URL/environment and verifies the API key without printing the key.
- Channel management uses a short-lived one-time token. The token is not stored or logged; the iframe response is marked `no-store`.

### Stripe Connect

- Keep the existing Standard-account OAuth flow for this release.
- Register the OAuth callback route `admin.connect.return` in Stripe.
- Register `webhooks.stripe.connect` as a Connect webhook endpoint and subscribe to connected-account events including `account.updated` and `account.application.deauthorized`.
- Store the Connect endpoint signing secret in `STRIPE_CONNECT_WEBHOOK_SECRET`.
- The deauthorization handler uses the connected account ID from the top-level Stripe event `account` field, not `data.object.id`, and clears only the matching host's account/capability fields.
- New guest deposit and itemized charges require `pay_by_cc`, a host `stripe_account_id`, and `stripe_charges_enabled=true`. PaymentIntent creation and retrieval use the connected-account context.

## Operations and verification

### PriceLabs shared-key audit

Run:

```bash
php artisan integrations:report-shared-key-usage
```

This is a read-only report. It does not migrate, delete, or unlink existing property data. Review the results and decide how each legacy/shared-key property should be handled before making data changes.

### Test commands

Run focused tests first, then the complete suite:

```bash
php artisan test --filter=PricingProviderTest
php artisan test --filter=SeamConnectionTest
php artisan test --filter=ChannexProviderTest
php artisan test --filter=ChannexConnectionTest
php artisan test --filter=ChannexWebhookIsolationTest
php artisan test --filter=StripeConnectTest
php artisan test --filter=PayByCcGatingTest
php artisan test --filter=PaymentServiceConnectedAccountTest
php artisan test --filter=PriceLabsSignatureTest
php artisan test --filter=PriceLabsIapiTest
php artisan test
```

These tests have been added but were **not executed** during the remote repository editing session. Do not treat this branch as production-certified until the focused tests and full suite pass in the project's PHP environment.

## Outstanding external setup and decisions

1. **PriceLabs IAPI — external onboarding and verification:** the branch contains the v2 client, listing/calendar/reservation synchronization, callback handlers, callback registration command, host email setup, and rate-plan/sync-status client operations. These are implemented but have not been runtime- or live-provider-verified in this editing session. Obtain the integration name/token from PriceLabs, configure the development account email, run the local test and route/migration checks listed above, register and verify the callback URLs, then complete the PriceLabs certification/pilot before enabling production use.
2. **Channex:** verify staging setup with a real account, then complete Channex production access/certification before changing the production base URL.
3. **Seam:** configure the workspace API key, webhook signing secret, and registered callback/webhook URLs; run a real Yale and August connection and device assignment.
4. **Stripe:** configure the OAuth client and both webhook secrets; test host authorization, deauthorization, deposits, and itemized charges with two separate connected test accounts.
5. **Legacy PriceLabs properties:** review the shared-key report and decide whether affected properties should be migrated, switched to Wheelhouse, or left unlinked.
6. **Wheelhouse webhook:** the old webhook endpoint is retained until the owner confirms whether it should be removed.
7. **Todo reference mismatch:** the integration plan references todo #60 for connected-account payment routing, but the repository's current todo #60 is about guest checkout timestamps/audit history. That unrelated todo has not been changed.

## Scope boundary

Telnyx and Google integrations are unchanged by this work.
