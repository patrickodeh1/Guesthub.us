# Guest Hub — Client Backlog Work Log

**Working branch:** `work/2026-11-remaining-client-backlog`  
**Baseline commit:** `d0c0c920b270c5e3e08ae7086e53bd3a62b41cb1`  
**Task source:** `outstanding tasks undone.txt` (read in full on this branch; 31,954 characters)  
**Rule:** Keep the original task-list file unchanged. This log records investigation, implementation, tests, and anything that needs client/server access or clarification.

## How to use this log

- **Done** means implemented and verified by an automated test or reproducible code-level check.
- **Implemented — needs staging test** means code is committed but cannot be fully verified without the deployed application, browser/device, or real integration credentials.
- **Blocked** means the client must supply access, credentials, an example, or a decision before a safe implementation is possible.
- **Not started** means it remains in the backlog.
- Each item should be reviewed against the original client wording in `outstanding tasks undone.txt`.

## Already recorded as done or deferred in the source task list

| Item | Source-list status | Notes |
|---|---|---|
| Guest-only check-in step incorrectly shown to cleaners / duplicate photo prompt | Done | Client clarified the step is for guests. Do not delete the guest instruction. |
| `CODE: ask` in the cleaning guide | Done | Client wrote that the text was in the instructions and is now all good. |
| Optional room checkboxes blocking report progression | Deferred | Client asked to leave current behaviour until they raise it again. Do not change it proactively. |
| Vehicle plate scanner framing and recognition improvements | Done in code branch; needs staging verification | The branch contains the camera guidance and scanner changes. |

## Current execution log

### 1. Version number and client-facing update notes
**Status:** Not started  
**Client request:** Add a version number under the logo and provide a text file explaining everything updated and how to access/test it.  
**Acceptance checks:** Version is visible in the authenticated application shell; version is stored in source control; release notes list only changes actually delivered and give clear navigation/testing steps.

### 2. Bot/spam protection and Cloudflare
**Status:** Blocked for infrastructure; application assessment pending  
**Client request:** Remove junk/spam and deploy Cloudflare protection in the background without intrusive CAPTCHA interactions. Client described Cloudflare DNS/proxy/SSL, origin certificates, Turnstile and security headers.  
**Dependency:** Cloudflare account/domain/DNS access and confirmation of current proxy/SSL setup. Do not claim infrastructure is secured from application code alone.

### 3. ID scanning across browsers and devices
**Status:** Not started  
**Client request:** Resolve Android browser/camera selection problems and improve ID capture feedback. Detect unreadable/blurred/glared/poorly lit images while the camera is open and guide the guest to correct the issue or use a supported browser.  
**Acceptance checks:** Test real iOS Safari and Android Chrome devices; do not claim browser compatibility based only on desktop emulation.

### 4. New-user temporary password and first-login reset
**Status:** Not started  
**Client request:** Generate a temporary numeric password automatically, send it to the new user's phone and email with the Guest Hub link, and force a password change before access.  
**Dependencies:** Confirm SMS provider, message templates, email delivery, and intended account-creation flows.

### 5. Early check-in availability and cleaning turnaround rules
**Status:** Not started  
**Client request:** Prevent guests selecting unavailable early-check-in times; make the turnover gap configurable per property (client discussed changing four hours to two hours); allow admin/owner/manager to override warnings when cancelling/recreating or setting a cleaning.  
**Clarification to preserve:** Admin override should warn first and require an intentional second action; ordinary guests must only see selectable available times.

### 6. Cleaning session vs. cleaning report editing
**Status:** Not started  
**Client request:** Separate editing the scheduled cleaning/job from editing a completed/in-progress cleaning report. Allow report edits (photos, checkboxes, notes); make notes optional behind an Add Note action; define report reopening permissions (client says admins can reopen whenever, while the same-day restriction applies to other users). Improve the confusing/unfinished report screen.  
**Dependency:** Missing screen recording referenced in the task list; request a fresh recording if the intended screen cannot be identified from code.

### 7. Deleting and recreating cleaning jobs
**Status:** Not started  
**Client request:** Deleting/cancelling a cleaning should return the work to the assignable pool and preserve its guest association if recreated/rescheduled. Admins should be able to proceed despite schedule-conflict warnings.

### 8. Host-owned payment processing and liability separation
**Status:** Not started  
**Client request:** Charge hosts for Guest Hub subscriptions/add-ons, but charge each host's guests through that host's own Stripe/Square/other processor. Guest payments, disputes and chargebacks must belong to the host, not Guest Hub.  
**Dependency:** Product/payment architecture decision and supported provider requirements. Do not route guest payments through the platform's own merchant account without explicit approval.

### 9. Cleaning report charges
**Status:** Not started  
**Client request:** Record extra cleaning charges and replacement-item charges on a cleaning report so they can be included in the guest's incidentals/deductions.

### 10. Guest check-in reminders
**Status:** Not started  
**Client request:** If a guest starts check-in but does not finish, continue prompting them until they complete it. Client suggested very frequent reminders (even every 30 seconds).  
**Safety/product decision required:** Do not implement 30-second messaging without rate limits, opt-out/compliance review, deduplication, and a less disruptive cadence agreed with the client.

### 11. Integration API-key ownership and pricing-tool exclusivity
**Status:** Not started  
**Client request:** Advise which integrations use platform credentials versus host credentials. Wheelhouse and PriceLabs must be mutually exclusive per host, selectable during onboarding and easy to switch later.  
**Acceptance checks:** Enforce exclusivity server-side, handle disconnect/switch flows, and document which integrations use platform or host keys.

### 12. Global feature pricing, trials and sales pages
**Status:** Not started  
**Client request:** Global admin table to mark features included or paid and set prices; support 30-day trials and individually configured indefinite trials; public sales page and side-by-side plan comparison.

### 13. Photo retention and deletion placeholders
**Status:** Not started  
**Client request:** Keep issue photos for 60 days and required verification photos for 30 days; after purge, show a clear “removed from server” placeholder instead of making the photo appear never to have existed.  
**Acceptance checks:** Retention categories must be explicit; purge jobs must preserve database metadata/placeholders and must not delete files still required for another purpose.

### 14. Admin dashboard simplification and visual hierarchy
**Status:** Not started  
**Client request:** Make dashboard sections/headings clearer on desktop and mobile. Reduce guest-card clutter: show the key status (cleaning ready, deposit pending, arrival/check-in time, parking, guest record); show prior guest's cleaning report when needed; defer less relevant detail until the day before or day of arrival.

### 15. Rental agreement presentation
**Status:** Not started  
**Client request:** On the page leading to the agreement, show only a “View Rental Agreement” action. Ensure the agreement itself exposes the full terms and device ID as well as signatures.

### 16. Guest instruction release timing and host settings
**Status:** Needs reconciliation before implementation  
**Client wording:** Instructions should be available after 4 PM once manager guest approval is complete; if approval is incomplete, keep them hidden. Client also said instructions should be available one hour before property check-in time. The client additionally wants the extra manual approval layer to be optional per host.  
**Conflict:** “After 4 PM” and “one hour before property check-in” are not necessarily the same rule. Inspect current implementation and log the exact behaviour; ask for a decision only if code and existing settings do not resolve precedence.

### 17. Property-specific guest registration questions and add-ons
**Status:** Not started  
**Client request:** Let each host configure registration fields/add-ons per property (e.g. parking, grocery delivery); optional fee defaults to zero and must not display a price when no fee is configured. Selected paid extras should be charged through the host's payment processor.

### 18. Staff pay scales
**Status:** Not started  
**Client request:** Collect staff pay settings at account creation (per-job, hourly, or both); attach the selected pay basis/rate to each assigned cleaning job so a future accounting module can calculate and mark payments.

### 19. Mandatory training videos
**Status:** Not started  
**Client request:** Fix iPhone video playback; filter task choices by selected property; for multiple properties, avoid duplicate ambiguous task names; show only task-required items; group/sort tasks by room; allow video categories to be modified.  
**Dependencies:** Real iPhone Safari testing and confirmation of expected category permissions.

### 20. Media category navigation/ownership
**Status:** Not started  
**Client request:** Move the Categories navigation item out of Media. If categories are global, restrict management to admin settings; if host-specific, expose them as host settings.

### 21. External guest registration forms
**Status:** Blocked pending access and workflow details  
**Client request:** Add guest-profile links/actions to prefill the Livly and Parking.com forms; Parking.com is only needed for guests who require parking.  
**Dependency:** URLs, sample forms, login/access method, and confirmation whether prefill can be supported without an API.

### 22. Guest phone OTP and SMS consent wording
**Status:** Not started  
**Client request:** Verify guest phone numbers by OTP during registration; shorten SMS consent text and place the full terms behind a link/pop-up.  
**Dependencies:** SMS provider, compliance-approved short consent text and full terms URL.

### 23. Public homepage, compatibility, pricing and feature marketing
**Status:** Not started  
**Client request:** Add platform compatibility logos, section navigation (pricing, compatibility, workflows), login link, pricing comparison, and feature mentions including inventory management, AI image detection and claims reporting.

### 24. Login and account-request flow
**Status:** Partially present; needs review  
**Client request:** Show the brand logo and a Create Account link on login. Account types: Property Owner, Property Manager/Cohost, Cleaning Company, Cleaner. Cleaner signup must collect a numeric property ID; Cleaning Company signup must collect company name.

### 25. Cancel/recreate cleaning and guest association
**Status:** Not started  
**Client request:** If a job is cancelled/deleted and recreated, it must still be linked to the original guest and represented as a reschedule rather than disappearing from the guest record.

### 26. Direct-booking reviews and imported reviews
**Status:** Not started  
**Client request:** Allow guests to review direct bookings and, where platform terms/APIs permit, import reviews from other booking platforms into one seamless display.  
**Dependency:** Confirm supported source platforms and their review API/export permissions.

### 27. Mobile app handoff documentation
**Status:** Not started  
**Client request:** Prepare the Guest Hub repository link and API documentation for a separate app implementation.  
**Acceptance checks:** Document authentication, endpoints, request/response shapes, errors, rate limits, and app-relevant workflows; verify documentation against actual routes/controllers.

## Older outstanding requests

### 28. Host custom domains and Hospitality navigation
**Status:** Not started  
**Client request:** Put Manage Listings first under Hospitality; allow a host's own domain to point to their hosted Guest Hub listing.  
**Dependency:** Domain verification, DNS instructions, TLS provisioning and tenant/domain routing design.

### 29. Media/photos taxonomy
**Status:** Not started  
**Client request:** Unify Media and Photos; distinguish image/video categories and training/site-image categories.

### 30. Delete associated files
**Status:** Not started  
**Client request:** Deleting a record should remove all associated files as well.  
**Safety:** Audit every relationship and retention rule before implementing cascading file deletion.

### 31. GPS override approval alerts
**Status:** Not started  
**Client request:** Notify the client when a GPS override is requested; allow an authorized yes/no response that applies or rejects the override.

### 32. Guest check-in/out audit trail and timestamps
**Status:** Not started  
**Client request:** Never overwrite historical check-in/out events. Preserve an audit trail (guest checks out, admin checks in again, guest checks out again); report the last actual checkout event and use the property's local timezone consistently.

### 33. Company access and white-label account types
**Status:** Not started  
**Client request:** Company access should be similar to owner/admin access but must not permit global-property changes. White-label/rebranding should be a separate account type. Simplify notification-permission controls using the client's role list.

### 34. AI property assistant
**Status:** Future request  
**Client request:** Let guests ask questions about the property in natural language rather than navigating menus. Treat as a separately scoped future feature; requires property-content retrieval, permission boundaries and safe fallback behaviour.

## Important boundaries and unresolved inputs

- The original task list contains literal Git conflict-marker lines in its ONGOING section. This work log does not alter that source file.
- Some requests are product-scale projects, not isolated bug fixes. Each will be checked against existing architecture before code changes.
- Infrastructure changes requiring DNS, Cloudflare, payment-provider, third-party site, or production-device access cannot be truthfully marked complete from repository edits alone.
- Do not claim a feature is verified unless tests or a reproducible staging check actually ran.
