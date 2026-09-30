# Task Queue — Part D (real unification: theme, shell, activity, properties, settings)

Read `AGENTS.md`, `MERGE_PLAN.md`, `TASKS_A.md`, `TASKS_B.md`, `TASKS_C.md` first.
Log in `notes.md` as before. **Global freeze still applies**: no real
`php artisan migrate` against any real database, `--pretend` only. This file
needs **no schema changes** — if you find yourself writing a migration that
alters or drops an existing column, STOP; it means you're on the wrong path
(see D005's reason for that rule).

Standing rules from the client, unchanged:
- Root wins on any collision; never strip a cleaning feature unless root
  already does the same job.
- The demo `guesthub` SQLite file committed at the repo root is known and
  ignored — do not touch it.
- Do NOT rename a route name that any JS `fetch()`/Alpine code or a blade
  `route()` call still references. Before renaming or removing ANY route, run
  `grep -rn "route('<name>" app resources` and update every hit in the same
  commit. When in doubt, keep the old name and add a redirect.

---

## Why this file exists — what TASKS_C left undone

TASKS_C got the two halves *linked*. They still don't feel like one product
because the shared foundations are still two implementations. Verified by
reading the pulled code (2026-09-28):

1. **Dark mode is broken at the root, not just ugly.**
   - `resources/css/app.css` is Tailwind 4 (`@import 'tailwindcss'`) and never
     declares `@custom-variant dark`. In Tailwind 4 that means every
     `dark:` utility follows the **OS** `prefers-color-scheme`, not the `.dark`
     class. So the toggle can't control them, and a user on a dark OS sees dark
     styling regardless. This is the single cause of "theme change isn't
     functional" and "some dark shows by default".
   - Both theme-init scripts (`layouts/app.blade.php` ~line 62 and
     `resources/js/cleaning-app.js` `getTheme()`) fall back to
     `prefers-color-scheme: dark` when nothing is stored → default is dark on
     dark-OS devices. Client wants **light by default, dark only after the
     button is pressed**.
   - Cleaning's Tailwind 3 `tailwind.config.js` (deleted from `cleaning/`)
     defined `colors.dark.eval-0..3` (`#151823`, `#222738`, `#2A2F42`,
     `#2C3142`). Nothing in Tailwind 4 recreated them, so the ~24 usages of
     `bg-dark-eval-*` in views generate **no CSS at all** (transparent
     surfaces in dark mode).
   - The same config registered `@tailwindcss/forms`, `typography`,
     `aspect-ratio`. `app.css` has no `@plugin` lines, so those never load.
   - `resources/css/cleaning.css` has `.hover\\:bg-theme-primary:hover` etc.
     with a **double** backslash — the selector never matches, so
     theme-color hover states silently do nothing.
   - The root admin shell (`layouts/admin.blade.php`) has **zero** `dark`
     handling and ~750 hardcoded light-palette utilities across 38 admin views
     (`bg-white` ×80, `bg-slate-50` ×85, `text-slate-*00` ×429,
     `border-slate-*00` ×163). Only 3 admin views (`admin/videos/*`) have any
     `dark:` classes. Turning dark mode on for the root side as-is would
     produce white cards on a dark page.
2. **Theme/brand/button colors only reach half the site.**
   - `--theme-primary` / `--button-primary-color` are emitted only in
     `layouts/app.blade.php` and `layouts/cleaning-guest.blade.php`.
     `layouts/admin.blade.php` emits nothing.
   - The root shell hardcodes the sidebar `bg-[#082b49]`, `.btn-primary`
     `bg-[#0b2d4d]`, `.btn-accent` `bg-blue-600` in `app.css`.
   - Admin views use 169 raw `<button>` tags vs 10 `<x-button>` (which is the
     only thing that reads `button_*_color`).
   - There are **three** color concepts: `theme_color` (cleaning),
     `brand_color` (read by the guest-portal `layouts/guest.blade.php`), and
     the hardcoded navy. Changing "brand" in settings changes only some of them.
3. **The merged Settings page lost cleaning features (regression from C003).**
   `admin/settings.blade.php` only has `brand_color, button_primary_color,
   contact_*, default_deposit_cap_dollars, favicon, gps_radius_meters,
   processing_fee_percent, site_logo, site_name, theme_color`. Cleaning's
   `settings/index.blade.php` (now unreachable — `/settings` redirects to
   `/admin/settings`) had: `application_icon`, `logo_alignment`,
   `mandatory_instruction_viewing`, `global_required_instruction_views`,
   `notify_cleaning_started_global`, `notify_cleaning_finished_global`,
   `notify_photo_started_global`, `notify_task_notes_global`. Code that reads
   them is live (`ChecklistController`, `TrainingValidationService`,
   `CleaningSmsNotificationService`, sidebar header) but **no UI can change
   them any more**. Also read but editable nowhere:
   `button_success/danger/warning/info_color` and all `report_*_color` keys.
4. **Two shells, two sidebars.** 32 admin views use `<x-admin-layout>`
   (→ `layouts/admin.blade.php`, hardcoded sidebar, no dark mode); 41+ views
   use `<x-app-layout>` (→ `layouts/app.blade.php`, its own Alpine
   `mainState`, perfect-scrollbar, `components/sidebar/*`, heroicons v1).
   Different look, different collapse behaviour, different icons. C002 only
   added cross-links.
5. **Two activity logs, two pages.** `admin.logs.index` (root
   `activity_logs`, model `ActivityLog`, 75 `ActivityLogService::` call sites)
   and `activity.index` (Spatie `activity_log`, `Spatie\Activitylog\Models\Activity`,
   ~20 `activity()` call sites). Different columns, different filters,
   different nav entries.
6. **Two property systems on one table.** `admin.properties.*` (guest-portal
   form/list, guide, instructions, locks, availability/iCal) and
   `properties.*` (cleaning: rooms, tasks, property-tasks, assignments,
   notifications, order, duplicate). Same `properties` table, two lists, two
   forms, two duplicate actions (`admin.properties.duplicate` vs
   `PropertyDuplicateController`), two nav entries.

---

## task-D000 — status: done
**The `verified` middleware locks admin-created users out (production bug)**

Approved by the user on 2026-09-28. `/dashboard` uses
`middleware(['auth','verified'])`, but registration/verification aren't
publicly linked, so any user an admin creates (no `email_verified_at`) is
redirected away after login. It is also why `AssignmentGroupingTest` gets 302
instead of 200.

Set `email_verified_at = now()` when an admin creates a user in
`Admin\UserController@store`; keep factory/seeder-created users verified by
default; and back-fill existing NULLs for **already-active** users with a
data-safe one-off Artisan command (not a migration, preview-only by default).
Do NOT remove `verified` from the route.

---

## task-D001 — status: done
The user confirmed on 2026-09-28 that the dark theme works across the required
pages. Implementation and verification are complete.
**Fix the theme foundation: light by default, dark only by button, correct everywhere**

Do this before any other visual task; D002/D003 build on it.

1. In `resources/css/app.css`, right after the `@import`, add
   `@custom-variant dark (&:where(.dark, .dark *));` so `dark:` utilities are
   driven **only** by the `.dark` class.
2. Recreate cleaning's dark surfaces inside `@theme` in `app.css`:
   `--color-dark-eval-0: #151823; --color-dark-eval-1: #222738;
   --color-dark-eval-2: #2A2F42; --color-dark-eval-3: #2C3142;` (this restores
   `bg-dark-eval-*` / `dark:bg-dark-eval-*` / `text-dark-eval-*`, which are
   currently no-ops). Grep `dark-eval` in `resources/` to confirm every usage
   now resolves.
3. Add the plugins cleaning was built with, in `app.css`:
   `@plugin '@tailwindcss/forms'; @plugin '@tailwindcss/typography';
   @plugin '@tailwindcss/aspect-ratio';` (all three are already in
   `package.json`). Then build and eyeball the **root** guest portal and admin
   forms — the forms plugin resets input styling. If a root page regresses,
   fix that page's classes; do not drop the plugin (cleaning's forms need it).
   Note what you changed in notes.md.
4. Fix `resources/css/cleaning.css`: change `.hover\\:bg-theme-primary:hover`,
   `.hover\\:text-theme-primary:hover`, `.focus\\:ring-theme-primary:focus`
   to single-backslash escaped selectors (`.hover\:bg-theme-primary:hover`).
5. **One** theme initialiser, shared by every layout, placed in `<head>`
   before first paint (new partial `resources/views/layouts/partials/theme-init.blade.php`
   included by unified/admin, `app`, `guest`, `cleaning-guest`, and the auth
   pages):
   - storage key stays `dark` (existing users keep their choice);
   - **no `matchMedia('(prefers-color-scheme: dark)')` anywhere** — if the key
     is absent, the theme is light;
   - accept both legacy stored shapes (`'true'`/`'false'` string and JSON
     booleans) so nobody's saved preference breaks;
   - sets/removes `.dark` on `<html>` and sets `color-scheme` accordingly.
   Delete the duplicate init in `layouts/app.blade.php` and the
   `getTheme()` OS fallback in `resources/js/cleaning-app.js` (keep
   `toggleTheme()` and the storage write).
6. **Root side needs real dark support.** Do NOT hand-edit 38 files. Add a
   dark compatibility layer in `app.css` (`@layer components`/`base`) keyed on
   `.dark`, for the palette the root uses:
   - override the slate scale variables under `.dark`
     (`--color-slate-50` … `--color-slate-950`) to an inverted ramp built on
     the dark-eval colors, so `text-slate-*`, `bg-slate-*`, `border-slate-*`
     flip automatically;
   - do **not** override `--color-white` (it also drives `text-white` on
     colored buttons); instead add explicit rules for the surface utilities
     `.dark .bg-white`, `.dark .bg-slate-50`, `.dark .divide-slate-*`,
     `.dark .border-slate-*`, `.dark .ring-slate-*`, `.dark .shadow-*` that use
     the dark-eval surface colors;
   - give the shared component classes in `app.css` (`.card`, `.card-pad`,
     `.page-title`, `.page-subtitle`, `.eyebrow`, `.field-label`,
     `.field-help`, tables, inputs, `.btn-*`, badges, modals) explicit
     `.dark` variants — these are the highest-leverage edits.
   Then find what still looks wrong in dark on the root pages and fix those
   specific spots (expect: hardcoded `bg-[#…]`, inline `style="background:#fff"`,
   SVG/chart colours, `tour` overlay, flatpickr/date pickers, dropdown items —
   `components/dropdown/item.blade.php` and `action-dropdown.blade.php` currently
   toggle inline colours with a `document.querySelector('.dark')` hack; replace
   with plain `dark:` classes).
7. Put the theme toggle button in the **one** shared top bar (D002 creates it;
   until then keep the existing navbar toggle and add the same button to the
   root `layouts/admin.blade.php` header, using `x-data`/`localStorage`
   consistent with step 5). Icon shows sun/moon by current state.
8. Verification (report results in notes.md — this is the acceptance test):
   - With the OS set to dark and `localStorage.dark` cleared: every page in the
     list below renders **fully light**.
   - Press the toggle: every page renders fully dark, no white cards, no
     invisible text, no dark-on-dark, hover and non-hover states of buttons,
     links, nav items, table rows, dropdown items, inputs (focus/disabled),
     badges and modals all legible.
   - Reload: choice persists. Toggle back: fully light.
   Pages to cover: `/login`, `/force-password-change`, `/admin`, `/dashboard`,
   `/admin/properties`, `/properties`, a property edit page, `/admin/guests`,
   `/admin/users`, `/admin/settings`, `/admin/logs`, `/activity`,
   `/assignments`, `/calendar`, `/sessions/*` (checklist), `/reports/*`,
   `/training`, `/admin/videos`, and one guest-portal page.
   Extend `scripts/capture-screenshots.js` (Node/Puppeteer already in repo)
   with a `--theme=dark|light` option and a second pass so a human can diff
   the screenshots quickly; commit the script change, not the images.
9. Add a Feature test asserting the built layout HTML contains the shared
   theme-init partial and does **not** contain `prefers-color-scheme` in any
   layout blade (grep test over `resources/views/layouts`).

Report in notes.md: the exact list of pages checked in each theme and any page
you could not fix and why.

---

## task-D002 — status: done
The unified shell and navigation changes are complete. Per the user's latest
direction, the original Guest Admin, Settings, and Administration order is
preserved, with Cleaning Ops navigation grouped under one collapsible. The
Guest Admin and Cleaning dashboards remain separate. The requested manual
browser checks are recorded in `manualtest.md` and remain pending; D002 is
marked done at the user's direction without claiming those checks were run.

Updated navigation visibility: housekeeping links are standalone; cleaner roles
do not see Guest Admin, admin pages, or dashboard links, and the Guest Admin
dashboard route denies cleaner roles. Property and Settings submenus expand below
their parent rows. Admin/owner/company cleaning management links remain in the
Cleaning Ops collapsible.

Admin navigation now keeps only the management-side Cleaning entries in the
collapsed Cleaning menu. Cleaner-facing My Jobs, Calendar, Sessions, Training,
Photos, Videos, Guides, and My Account links are standalone for housekeepers and
are not rendered in the admin Cleaning menu.

Standalone cleaner links explicitly exclude administrators, including accounts
that have both `admin` and `housekeeper` roles.

The configured preview account redirects to login. An owner dashboard render
also exposed a pre-existing undefined `Property::users()` relation in
`DashboardController`.

**Navigation order override (user direction):** Preserve the root sidebar's
existing order and Settings submenu. Add Cleaning Ops navigation as a separate
collapsible rather than reorganizing root links. Do not merge the dashboard
implementations.
**One shell: root's sidebar and header for every authenticated page**

Decision (client): **root's sidebar is the sidebar.** Cleaning pages render
inside root's shell; cleaning's own sidebar/navbar are retired.

Current wiring (verified): `<x-admin-layout>` → `layouts/admin.blade.php`
(sidebar `#admin-sidebar`, `data-tour` attributes used by the onboarding tour,
`{{ $slot }}` content, mobile open/close JS at the bottom).
`<x-app-layout>` → `AppLayout::render()` → `layouts/app.blade.php` (cleaning:
`components/sidebar/*`, `navbar`, Alpine `mainState`, flash alerts, plus the
Add-Note / photo-upload / inventory modals at ~lines 280–470 that the
checklist pages depend on).

1. Create `resources/views/layouts/unified.blade.php` from
   `layouts/admin.blade.php` (keep its sidebar markup, `data-tour` hooks,
   mobile drawer behaviour, header/search, flash and error banners, tour
   scripts). Make `AdminLayout` **and** `AppLayout` both render it.
   `layouts/admin.blade.php` / `layouts/app.blade.php` become thin
   compatibility wrappers or are deleted once nothing references them.
2. Slots: `<x-app-layout>` pages pass `<x-slot name="header">` (44 uses).
   Render it in the unified header's page-title area (and keep `$title` for
   admin pages). Do not change those 44 call sites.
3. Move cleaning-only page machinery out of the shell into partials that the
   unified layout includes **only when needed**:
   `layouts/partials/cleaning-modals.blade.php` (the note / photo / inventory
   modals), cleaning `mainState`/Alpine setup and perfect-scrollbar init,
   the `window.__userCanUpload` script. Use `@stack('scripts')`/`@stack('modals')`
   or a `$cleaning = true` prop; pages that don't use them must not pay for
   them. **Load both Vite entries** (`app.js` and `cleaning-app.js`) only where
   cleaning pages need them, or unconditionally if simpler — but verify the
   admin pages still pass (Alpine double-init is the known trap: root's
   `app.js` and cleaning's Alpine bootstrapping must not both call
   `Alpine.start()`; confirm in the browser console, zero errors).
4. **One sidebar, three sections**, built from a single PHP config array (new
   `config/navigation.php` or `app/Support/Navigation.php`) rendered by the
   unified layout — no more hand-written duplicate link lists:
   - *Guest Portal*: Dashboard, Guests/Bookings, Categories, Media Library,
     Early Access Signups, Instructions, Availability, Videos (`admin.videos.*`).
   - *Cleaning Ops*: Dashboard, Assignments, Calendar, Sessions/Reports,
     Rooms, Tasks, Training.
   - *Shared*: **Properties** (single entry — see D006), Users, Activity Log
     (single entry — see D005), Settings (single entry — see D004), Security,
     Admin Guide, My Account.
   Each item lists its route name(s) and required roles copied **from the
   existing route middleware**, not invented. Render an item only if
   `Route::has()` and the user can access it (`hasAnyRole`). A housekeeper must
   see only what they can use; an owner sees Cleaning Ops + their properties;
   an admin sees everything. Verify each of the 7 roles by loading the page
   and listing the rendered sidebar links in notes.md.
5. Active-state highlighting must work for both route families (use the
   `routeIs` patterns from the config).
6. Icons: root uses `<x-icon name="…">`; cleaning used Heroicons v1
   (`x-heroicon-o-*`, and a v2 name already broke once — see the
   `switch-horizontal` fix). Use **only** `<x-icon>` in the shell. Add any
   missing glyphs to root's icon component (`resources/views/components/icon.blade.php`
   or the icon set it uses). Keep `blade-heroicons` installed for other views.
7. Brand block at the top of the sidebar uses one helper for logo/icon/site
   name (see D003 step 3) — remove the hardcoded "Admin Panel"/"AP" and the
   hardcoded `<title> … Welcome Guide` suffix; use `site_name`.
8. User menu (profile, logout), theme toggle (D001), notifications and the
   header search live once in the unified header. Extend the header search's
   result set with cleaning entities (rooms, tasks, sessions) **only if** it's
   a small addition to the existing endpoint; otherwise note it as a follow-up.
9. Delete `resources/views/components/sidebar/*`, `navbar.blade.php`,
   `layouts/app.blade.php` internals and any other now-orphaned cleaning shell
   components **only after** `grep -rn` shows zero references. Do not delete
   `cleaning-guest.blade.php` (auth-page shell) or components still used inside
   page content (`x-button`, `x-form.*`, `x-dropdown.*`, etc.).
10. Mobile: at 375px wide, both families of pages open/close the drawer with
    the same control, no horizontal scroll on the body, and the checklist
    (camera/photo) flow still works (this is the housekeepers' main screen —
    test it, don't assume).

Report in notes.md: the final nav config, per-role visible links, files deleted,
and the browser-console result for one admin page and one checklist page.

---

## task-D003 — status: done
**Theme, brand and button colours reach the whole site, every shell, both themes**

Depends on D001. Goal: an admin changes colour(s) in Settings once and the
sidebar, buttons, links, focus rings, active nav, badges, tabs, auth pages,
guest portal, reports and emails all follow — in light and dark.

1. **One source of truth.** `theme_color` is canonical. `brand_color` (read by
   `layouts/guest.blade.php` via `Setting::getValue('brand_color', '#082b49')`
   and shown as a separate field on the settings form) must stop being an
   independent colour: the settings form writes the same value to both keys
   (keep both keys in the table — **no schema/data deletion**, restores must
   keep working), and every reader uses one helper. Remove the duplicate
   "Brand colour" field from the form; label the single field "Brand / theme
   colour".
2. New helper, e.g. `App\Support\Branding` (or static methods on `Setting`):
   `themeColor()`, `buttonColor('primary|success|danger|warning|info')`,
   `contrastText($hex)` (returns white/near-black by luminance so text on a
   light brand colour stays readable), `logoUrl()`, `iconUrl()`, `faviconUrl()`,
   `siteName()`. All logo/favicon URL building goes through it — today the
   admin shell builds `url('/img/'.$favicon)` while the cleaning shell checks
   `Storage::disk('public')->exists()`; unify to one method that works for
   files stored via either path and falls back gracefully (never a broken
   image).
3. One Blade partial `layouts/partials/brand-vars.blade.php` emitting
   `:root { --theme-primary; --button-primary-color; --button-success-color;
   --button-danger-color; --button-warning-color; --button-info-color;
   --on-primary; … }` plus `color-mix()`-derived `-hover`, `-active`, `-soft`
   (tint for badges/backgrounds) and dark-mode-adjusted variants
   (`.dark { --theme-primary-soft: …}`; lighten the primary a little on dark
   backgrounds if contrast < 4.5:1). Include it in the unified layout,
   `guest.blade.php`, `cleaning-guest.blade.php`, report pages, auth pages and
   the base email layout (D007). Delete the duplicate `:root` blocks in
   `layouts/app.blade.php`, `cleaning-guest.blade.php` and
   `reports/session/index.blade.php` (the report page keeps its own
   `report_*_color` mappings, but reads the shared vars for primary).
4. Map the root design system to the variables in `app.css`:
   - `.btn-primary` → `background: var(--button-primary-color)`,
     `color: var(--on-primary)`, hover/active/focus/disabled states;
   - `.btn-accent`, `.btn-danger`, `.btn-secondary`, `.btn-ghost`, `.eyebrow`,
     links, form focus rings, checkboxes/radios accent-color, active nav item,
     tabs, badges, progress bars, pagination current page;
   - the sidebar surface (`bg-[#082b49]`) → a variable derived from the brand
     colour (`color-mix(in srgb, var(--theme-primary) 85%, black)`), with
     readable text/hover states for any brand colour an admin picks (test with
     a very light colour and a very dark colour and a saturated yellow).
   Replace hardcoded `#082b49`, `#0b2d4d`, `bg-blue-600`, `text-blue-700`,
   and cleaning's `bg-theme-primary`-style patches **in the shell, shared
   components and `app.css`**. For the ~169 raw `<button>`s and ad-hoc
   `bg-blue-*`/`bg-indigo-*` in individual pages: do a scripted pass that
   converts the common patterns to `.btn-primary` / `.btn-secondary` /
   `.btn-danger`; list what you converted and what you left (with reasons) in
   notes.md. Do not restyle intentionally semantic colours (green success,
   red destructive, amber warnings) beyond routing them through the
   success/danger/warning/info variables.
5. `<x-button>` (`components/button.blade.php`) and any component doing
   `Setting::get('button_*_color')` per render: switch to the CSS variables so
   there's one code path and no per-button DB/cache lookups.
6. Every interactive state must look right in **both** themes: default,
   hover, focus-visible, active, disabled, loading — for buttons, links,
   nav items, table row hover, dropdown items, inputs. Contrast ≥ WCAG AA for
   text on the brand colour (that is what `contrastText()` is for).
7. Feature test: set `theme_color` to `#facc15` (light) and `#111827` (dark)
   in a test, request `/admin` and `/dashboard`, assert the rendered `<style>`
   contains those values and `--on-primary` resolves to the right contrast
   colour.

Report in notes.md: helper location, the list of raw-button patterns converted,
and screenshots-script output for two extreme brand colours.

---

## task-D004 — status: done
**Restore the cleaning settings that became unreachable; one complete Settings page**

Regression from C003 (see "Why" #3). Read both
`resources/views/settings/index.blade.php` and
`resources/views/admin/settings.blade.php`, plus both controllers and
`SettingsUpdateRequest`, before editing.

1. Make `admin.settings.edit` the only settings page, organised as tabs/sections
   (single form or one form per tab, one Save each, no page reload loss):
   **Branding** (site name, logo, app icon, favicon, logo alignment, brand /
   theme colour, button colours: primary, success, danger, warning, info;
   live colour swatches/preview using the D003 variables), **Guest Portal**
   (contact email/phone, deposit cap, processing fee — existing),
   **Cleaning Ops** (`gps_radius_meters` — note it exists in both worlds, keep
   one field; `mandatory_instruction_viewing`,
   `global_required_instruction_views`, `auto_save_enabled`,
   `auto_save_delay`, `items_per_page`, `date_format`, `time_format`,
   timezone default = `America/New_York` per client), **Notifications**
   (existing `admin.settings.notifications.edit` content **plus**
   `notify_cleaning_started_global`, `notify_cleaning_finished_global`,
   `notify_photo_started_global`, `notify_task_notes_global`,
   `notify_assignments`, `notify_session_started`, `notify_session_completed`
   — check which of these are actually read by code before showing them;
   show only live ones), **Reports** (the `report_*_color` keys, if any code
   reads them — `reports/session/index.blade.php` does), **Legal** (existing).
2. Every key the codebase reads via `Setting::get/getValue` must be editable
   somewhere, or explicitly documented in notes.md as internal/no-UI. Produce
   the audit table (key → read-by → editable-where) in notes.md.
3. Saving must call the single `Setting::set/putValue` path so the 1-hour cache
   is busted; add `Cache::forget` for the derived branding helper if it caches.
4. Old `/settings` and cleaning `SettingsController` stay as a redirect only;
   the unreachable `settings/index.blade.php` can be deleted once every field
   is confirmed present on the new page (diff the field lists in notes.md).
5. Authorisation: keep `canManageSettings()` (admin only) exactly as today.
   Owners/managers do not gain access.
6. Tests: extend `AdminSettingsNavigationTest` — admin can load each tab; a
   POST of each tab persists each key; a non-admin gets 403.

---

## task-D005 — status: done
**One Activity Log, without touching either table**

Client requirement: unify even though the columns differ, **and it must not
affect DB restore/update.** So: **no migrations, no column changes, no data
copying, no dropping or renaming either table.** Both `activity_logs` (root)
and `activity_log` (Spatie) stay byte-for-byte as they are; a backup taken
before or after this task restores identically; the client's legacy cleaning
data (Spatie rows) can be imported straight into `activity_log` in B009 and
appears in the unified view immediately. The unification is a **read-time
view** plus a **write facade**.

Column mapping (both verified in migrations/models):

| Unified field | Root `activity_logs` | Spatie `activity_log` |
|---|---|---|
| id | `id` | `id` |
| source | `'portal'` | `'ops'` |
| occurred_at | `created_at` | `created_at` |
| actor_name | `actor_name` (fallback `users.name` via `user_id`) | `users.name` via `causer_id` (`causer_type = App\Models\User`) |
| actor_email | `actor_email` | `users.email` via causer |
| actor_type | `actor_type` (`admin`/`guest`/…) | derive: `'staff'` (or role) |
| event/action | `action` | `event` (fallback `description`) |
| description | `description` | `description` |
| module | `module` | `log_name` |
| severity | `severity` | `'info'` (or map from `event`: deleted→warning) |
| subject_type / subject_id | same | `subject_type` / `subject_id` |
| property_id | `property_id` | when `subject_type = App\Models\Property` then `subject_id`, else NULL |
| ip / user_agent | `ip_address`, `user_agent` | from `properties` JSON if present, else NULL |
| detail payload | `metadata`, `old_values`, `new_values` | `properties` (JSON), `batch_uuid` |

1. New `App\Services\UnifiedActivityFeed`:
   - builds two normalized `SELECT`s (raw column aliases as above) and
     combines with `UNION ALL`, wraps as a subquery, applies **all filters and
     ORDER BY occurred_at DESC, id DESC** on the outer query, and paginates
     (30/page) — so paging and sorting are correct across both tables in SQL,
     not by merging collections in PHP;
   - watch MySQL collation mismatches in `UNION` (cast string columns with an
     explicit `COLLATE utf8mb4_unicode_ci` if the two tables differ) and
     type mismatches (cast ids to `CHAR`/`UNSIGNED` consistently, JSON to text);
   - filters: text search (description, actor, action/event, module),
     source (portal / ops / all), actor, module/log_name, severity, property,
     subject type, date range;
   - a `find($source, $id)` for the detail page.
2. Routes: canonical `admin.logs.index` (list) and
   `admin.logs.show` with `{source}/{id}`. Keep `activity.index` (and the old
   `admin.logs.show` numeric URL) as **redirects** so bookmarks/emails survive.
   One sidebar entry "Activity Log" (D002). Access: same as today's
   `canViewLogs()` (admin, manager) for the full log; **owners** may see only
   entries whose `property_id` is one of their properties (reuse the property
   scoping the cleaning `ActivityController` used — read it first and preserve
   its authorisation exactly; do not widen access).
3. Views: one `admin/logs/index.blade.php` (source badge "Guest Portal" /
   "Cleaning Ops", severity chip, actor, subject link when resolvable, time in
   `America/New_York`), one `show` that renders the source-specific detail
   (old/new values diff for portal rows; formatted properties JSON for ops
   rows). Must work in light and dark (D001) and use brand colours (D003).
   Retire `activity/index.blade.php` and the old cleaning `ActivityController`
   once redirected and nothing references them.
4. Write side: **leave all existing writers working unchanged** (75 ×
   `ActivityLogService::…`, ~20 × `activity()`); do not mass-refactor. Add a
   short "how to log" section to `AGENTS.md`: new admin/guest events use
   `ActivityLogService`; model-tied cleaning events may keep using
   `activity()`. Both appear in the unified page.
5. Dashboard "recent activity" widgets (both dashboards, D007) read from
   `UnifiedActivityFeed::latest($n)`.
6. Tests: create rows in both tables (different actors, dates interleaved),
   assert the feed returns them interleaved in correct order, that pagination
   totals equal the sum of both, that each filter works, that an owner sees only
   their properties' entries, and that **the two tables' schemas are unchanged**
   (assert `Schema::getColumnListing` for both equals the expected lists — this
   is the regression guard for the "don't affect restores" rule).
7. Performance note in notes.md: `activity_log` has no `created_at` index
   (root's has `al_created_idx`). Do **not** add one by migration in this task;
   just record row counts from the demo DB and flag it for the human to decide
   after B009 shows real volume.

---

## task-D006 — status: done
**One Properties experience (list, form, detail, duplicate, nav)**

Both families already operate on the same `Property` model/table. Today: two
lists (`admin.properties.index` with duplicate/edit/delete; `properties.index`
with rooms/tasks/activate/edit/delete), two forms (`admin/properties/form`
vs `properties/create|edit`), two duplicate actions, two nav entries, plus
separate pages for guide, instructions, locks, availability, rooms, tasks,
notifications. Client wants them as one.

Do NOT change any data or columns. Field-level merge only.

1. **Inventory first** (write into notes.md before coding): list every field on
   `admin/properties/form.blade.php` and on `properties/create.blade.php` /
   `edit.blade.php` (they use `<x-form.*>` components — read the component
   `name=` props, a plain `grep name=` misses them), the validation rules in
   `Admin\PropertyController` and `PropertyStoreRequest`, and which model
   columns each writes. Produce the union and flag any field that exists in
   both under different names (`active` vs `is_active` — the alias accessor
   exists; timezone; iCal urls: root has `airbnb_ical_url`/`vrbo_ical_url`
   plus `ical_url` from cleaning — decide with the code, flag in notes, don't
   delete either).
2. **One list**: `admin.properties.index` is canonical. Columns: name, address
   /city, owner (`Unassigned` when null — already handled), active badge,
   counts (rooms, tasks), guest-portal status, next check-in / next cleaning if
   cheaply available. Row actions: Edit, Rooms, Tasks, Guide, Availability,
   Notifications, Duplicate, Activate/Deactivate, Delete — permission-gated with
   the **existing** policies (`PropertyPolicy` from C000) and roles.
   `properties.index` → redirect.
3. **One create/edit page with tabs** (`admin.properties.edit`): *General*
   (union of fields), *Guest Portal* (welcome copy, wifi, check-in/out,
   categories/guide entry, instructions, locks), *Cleaning Setup*
   (rooms + tasks + property-tasks + assignments + ordering — embed or link
   the existing `properties/rooms/*`, `property-tasks/*` views; keep the Alpine
   panels `__assign_rooms_panel`, `__preview_panel` working), *Calendar &
   iCal* (existing availability view), *Notifications* (recipients + history
   from `PropertyNotificationSettingsController`, keep its JSON endpoints),
   *Activity* (unified feed filtered by property, D005). One Save per tab is
   fine; unsaved-changes warning is optional.
   The store/update path must persist **both** field sets; merge the two
   validation rule sets into one FormRequest (start from the existing
   `PropertyStoreRequest`, add the guest-portal rules). New properties default
   `checkout_time` = `10:00` and `timezone` = `America/New_York` (already true
   at DB level — confirm the form doesn't override with blanks).
4. **One duplicate action.** Compare `Admin\PropertyController::duplicate`
   (guide/portal content?) with `PropertyDuplicateController::store`
   (rooms/tasks via `Room::cloneForProperty`) — read both. Build one duplicate
   dialog with checkboxes (Guest Portal content, Rooms & tasks, Property tasks,
   Notification recipients) that calls both routines, transactionally. Keep
   `Room::cloneForProperty` intact — it is load-bearing (see the Decision
   Record in TASKS_C.md).
5. **Routes**: keep every route **name** that JS or blades reference (the
   `api.properties.*`, `properties.rooms.*`, `properties.tasks.*`,
   `properties.property-tasks.*`, `properties.*.order`, `properties.notifications.*`
   families). Only the *page* routes (`properties.index/create/edit/show`)
   become redirects to the `admin.properties.*` equivalents. Run
   `php artisan route:list` before/after and paste the diff in notes.md.
6. **Sidebar** (D002): a single "Properties" entry linking to the unified list,
   with root's existing per-property submenu extended (Overview, Rooms, Tasks,
   Guide, Availability, Notifications). Remove the second "Properties" entry
   and the "Guest Portal Properties"/"Cleaning Properties" labels C002 added.
7. Owners see only their properties (existing `owner_id` scoping in the
   cleaning controller — preserve exactly; don't widen).
8. Tests: an admin can create a property with fields from both worlds and see
   them on the edit tabs; duplicate with each checkbox combination; an owner
   can't open another owner's property; the old URLs redirect.

---

## task-D007 — status: blocked
**Remaining duplicated surfaces — dashboard, account, videos, emails, auth, naming**

**Blocker:** Focused property-notification tests returned unexpected 302 redirects for admin and owner users; three SMS notification tests also failed because `App\Helpers\TimezoneHelper` could not be loaded. See the D007 entry in `notes.md`.

Do these after D002 (shell) and D003 (brand). Each is small; keep every
existing feature.

1. **One dashboard.** `admin/dashboard` (guest portal KPIs) and `dashboard`
   (`CleaningDashboardController`) become one page at `/dashboard`
   (route name `dashboard`; `admin.dashboard` redirects to it and keeps
   working as a name). Panels are role-gated: Guest Portal panels for roles
   that can use it, Cleaning Ops panels likewise, admin sees both; recent
   activity uses the unified feed (D005). Post-login redirect in
   `AuthController::login()` becomes simply `dashboard` for all staff roles
   (the role-based branching from B005 can go, but keep the "no role → log a
   warning" safety). Keep the existing KPI queries; move them, don't rewrite.
   Remove the C004 cross-widgets — they're redundant now.
2. **One account page.** Cleaning has `profile/*` (`ProfileController`) and root
   has `admin.security` (password/2FA-style). Merge into one "My Account"
   page (profile fields, password change, notification preferences,
   preferences from the `add_preferences_to_users` migration, force-password
   change flow keeps working). One nav entry.
3. **Videos & training.** `admin.videos.*` (instructional video CRUD) and
   `training.*` (completion tracking) are one feature area. Group them in the
   sidebar (Cleaning Ops → Training with a Videos sub-item); don't merge the
   controllers.
4. **Notifications, one mental model.** Global toggles live in Settings →
   Notifications (D004); per-property recipients live in the property's
   Notifications tab (D006). Make both pages cross-link and use identical
   wording. Do not merge `PropertyNotificationSettingsController` into the
   global one.
5. **Emails.** Create `resources/views/emails/layouts/base.blade.php` (logo via
   `Branding::logoUrl()`, `site_name`, brand colour inline — email clients
   ignore CSS variables, so resolve to hex in PHP, plus footer). Make all
   emails in `resources/views/emails/*` extend it (root's early-access,
   guest-alert, photo-id-declined and cleaning's session-started/completed).
   Keep each email's body copy; only the frame changes. Same treatment for
   any Mailable that builds HTML inline (grep `Mail/`).
6. **Auth & guest-facing pages.** `/login`, forgot/reset password,
   verify-email, force-password-change, and `cleaning-guest` layout: show the
   configured logo + site name, brand colour, and honour the theme toggle
   (D001). The guest portal (`layouts/guest.blade.php`, has a weather widget)
   uses the same brand variables; its light/dark behaviour is light-only
   unless it already had a toggle — don't add one there, just make sure
   `.dark` from the shared init can't half-apply (guest portal must stay
   fully light).
7. **Naming sweep.** One product name from `site_name` everywhere: `<title>`
   suffixes ("· Welcome Guide", "HK Checklist" fallbacks), sidebar brand,
   emails, PDFs/reports headers. Fallback constant lives in `config/app.php`
   `name`; update `.env.example` `APP_NAME` to the client-approved product
   name — if unknown, leave as is and flag in notes.md.
8. **Leftover dedupe.** Search for other pairs (both sides implementing the
   same thing): `resources/views/components/*` with near-identical names
   (`alert`, `modal`, `card`, `button`, `dropdown`, form inputs), duplicated
   JS helpers between `resources/js/app.js` and `cleaning-app.js`, two
   flash-message renderers, two pagination views. Record findings in notes.md;
   consolidate only when the two are functionally identical, otherwise list
   them for a human.

---

## task-D008 — status: open
**Acceptance pass — do last, and don't mark the merge "feels unified" without it**

1. Run `php artisan test`; report pass/fail counts. Failures caused by D000
   (`verified`) are expected until that's decided; every **other** failure is
   yours to fix or explain in notes.md (a failing test here is a real merge
   gap, not noise). Add tests named in D001, D003, D004, D005, D006.
2. Click-through as each of the 7 roles (`admin, company, owner, manager,
   staff, housekeeper, viewer`): login → lands on `/dashboard`; sidebar shows
   the right entries; no 403 from a link that's visible; no visible link
   leads to a redirect loop. Record a per-role table in notes.md.
3. Theme matrix: for `{light, dark} × {default brand colour, very light brand,
   very dark brand}` load `/dashboard`, `/admin/properties`, a property edit
   page, `/sessions/*`, `/admin/settings`, `/login` — screenshot script output
   attached to notes.md (paths only).
4. `php artisan view:cache`, `php artisan route:list` (no duplicate URIs, no
   route pointing at a deleted controller), `npm run build` — all clean.
5. `grep -rn "prefers-color-scheme" resources/` returns nothing outside
   comments; `grep -rn "components.sidebar\|x-sidebar" resources/` returns
   nothing if D002 step 9 was done.
6. Update `README.md` to match the unified app (a regenerated draft exists in
   the reviewer's notes; a human will supply it — do not write your own
   version unless told to).
7. Tick the statuses in `TASKS_C.md`/`TASKS_D.md`/`TASKS_B.md` to what is
   actually true, and record anything still pending (B009 data import, B010
   deploy, B011 delete `cleaning/`) — those stay blocked on the client's
   backups and are **not** part of this file.
