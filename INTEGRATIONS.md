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
- The IAPI base URL is deliberately blank in `.env.example`. PriceLabs provides the exact Swagger base URL and credentials after its integration onboarding process. Do not guess the URL.
- The signature verifier implements the published HMAC-SHA256 header/body scheme and includes PriceLabs' published signature vector.
- The IAPI transport is config-gated and supports the documented endpoint names. The exact Swagger request methods and payload schemas have not been supplied to this repository yet. Listing registration, calendar/reservation push payload builders, inbound HTTP routes, calendar-trigger handling, hook handling, and `pricelabs:register-integration` remain blocked until those schemas are available. No guessed live payloads are enabled.
- `PriceLabsIapiSync::applyForListingId()` accepts normalized `date`, `rate`, and optional `min_stay` rows and applies them through the existing AvailabilityLedger path. The inbound Swagger adapter must map the real PriceLabs request into this internal shape before production use.
- Properties in `pricelabs_mode=pms` are skipped by the legacy key-based polling command.

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

1. **PriceLabs IAPI:** obtain the official Swagger document/base URL, integration name, and token from PriceLabs. Then finish the exact listing, calendar, reservations, inbound sync, calendar-trigger, hook, status, and registration command payloads; run PriceLabs certification before enabling the feature.
2. **Channex:** verify staging setup with a real account, then complete Channex production access/certification before changing the production base URL.
3. **Seam:** configure the workspace API key, webhook signing secret, and registered callback/webhook URLs; run a real Yale and August connection and device assignment.
4. **Stripe:** configure the OAuth client and both webhook secrets; test host authorization, deauthorization, deposits, and itemized charges with two separate connected test accounts.
5. **Legacy PriceLabs properties:** review the shared-key report and decide whether affected properties should be migrated, switched to Wheelhouse, or left unlinked.
6. **Wheelhouse webhook:** the old webhook endpoint is retained until the owner confirms whether it should be removed.
7. **Todo reference mismatch:** the integration plan references todo #60 for connected-account payment routing, but the repository's current todo #60 is about guest checkout timestamps/audit history. That unrelated todo has not been changed.

## Scope boundary

Telnyx and Google integrations are unchanged by this work.
