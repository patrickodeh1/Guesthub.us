# Task Queue — Part B (application layer)

Read `AGENTS.md` and `MERGE_PLAN.md` first. `TASKS_A.md` (the old `TASKS.md`,
renamed) covers the database-schema/role layer — tasks A001–A007, all done
except A006's verification and A007's column drop, both deferred until
DB population (task-B009 below). This file picks up everything else needed to
finish the merge and empty out `cleaning/`.

**GLOBAL FREEZE still applies**: no real `php artisan migrate` against any
database until a human says DB population has started. `--pretend` dry-runs
are always fine and required for verification.

Work top to bottom. All tasks below are unblocked and safe to run now — the
auth-system decision (Option A) has been made, so task-B005 is no longer
blocked.

---

## task-B001 — status: open
**Copy cleaning's 16 non-colliding models into `app/Models/`**

Same as previously scoped: `Property`, `User`, `Setting` are the only model
collisions and are already handled in TASKS_A. Copy these unchanged from
`cleaning/app/Models/` to `app/Models/`:
`AssignmentTrainingSnapshot.php`, `ChecklistItem.php`, `ChecklistItemPhoto.php`,
`ChecklistReport.php`, `CleanerInstructionFamiliarity.php`,
`CleaningSession.php`, `InstructionalVideo.php`, `NotificationLog.php`,
`PhotoBlob.php`, `PropertyCheckout.php`, `PropertyNotificationRecipient.php`,
`ResourceCompletion.php`, `Room.php`, `RoomPhoto.php`, `Task.php`,
`TaskMedia.php`, `TrainingCompletion.php`.

1. Copy each file, unchanged.
2. Cross-check any reference to `Property`/`User`/`Setting` fields against the
   merged models' current `$fillable` (post TASKS_A 002/004). If a referenced
   field is missing, STOP — log the gap in notes.md, mark this task `blocked`,
   do not add the field yourself.
3. No migrations, no route registration — model files only.
4. Once copied and step 2 passes clean, delete the 16 source files from
   `cleaning/app/Models/`. Leave `cleaning/app/Models/Property.php`, `User.php`,
   `Setting.php` in place as reference until controllers that use them are
   ported and verified (task-B005).

Report in notes.md: confirm all 16 copied; list anything flagged in step 2.

---

## task-B002 — status: open
**Copy cleaning's Services — rename the one real collision**

`cleaning/app/Services/SmsNotificationService.php` and root's
`app/Services/SmsNotificationService.php` are unrelated classes with zero
overlapping methods (root: guest Telnyx SMS; cleaning: cleaner/session SMS).
Resolve by rename, not merge.

1. Copy `cleaning/app/Services/SmsNotificationService.php` to
   `app/Services/CleaningSmsNotificationService.php`, rename the class inside.
2. In these 6 files inside `cleaning/`, change the import and every
   `SmsNotificationService::` call to `CleaningSmsNotificationService` —
   do this rename in the source files now so it travels when they're copied
   later:
   `cleaning/app/Services/TrainingService.php`,
   `cleaning/app/Services/EmailNotificationService.php`,
   `cleaning/app/Http/Controllers/PhotoController.php`,
   `cleaning/app/Http/Controllers/ChecklistController.php`,
   `cleaning/app/Http/Controllers/SessionController.php`,
   `cleaning/app/Console/Commands/SendTrainingReminders.php`.
3. Copy the remaining 13 services unchanged (already-updated versions of
   `EmailNotificationService.php`/`TrainingService.php`) into `app/Services/`:
   `AssignmentGroupingService.php`, `EmailNotificationService.php`,
   `GpsService.php`, `ICalService.php`, `ImageTimestampService.php`,
   `PersistentPhotoStorage.php`, `PhotoFilenameService.php`,
   `ReportItemFilter.php`, `SessionStageService.php`,
   `TrainingCompletionService.php`, `TrainingService.php`,
   `TrainingValidationService.php`, `VideoOptimizer.php`.
4. Do not delete `cleaning/app/Services/` yet — the 3 controllers above still
   reference it until task-B005 ports them. Mark this task done once steps 1–3
   land; deletion happens in task-B005.

Report in notes.md: grep `cleaning/` for any remaining bare
`SmsNotificationService::` reference — should be none outside the new file.

---

## task-B003 — status: open
**Copy cleaning's Form Request classes**

Root has no `app/Http/Requests/` yet — no collision. Create the directory and
copy unchanged from `cleaning/app/Http/Requests/`: `ChecklistNoteRequest.php`,
`ChecklistToggleRequest.php`, `ProfileUpdateRequest.php`,
`PropertyDuplicateRequest.php`, `PropertyStoreRequest.php`,
`SettingsUpdateRequest.php`, `StartSessionRequest.php`, `UserStoreRequest.php`,
`UserUpdateRequest.php`, plus the full `Auth/` subdirectory — list its contents
in notes.md, it hasn't been enumerated yet. Leave source files in `cleaning/`
until task-B005 ports the controllers that use them.

---

## task-B004 — status: open
**Copy cleaning's Console Commands**

No collisions with root's `app/Console/Commands/`. Copy `PruneOldSessionPhotos.php`
and `SendTrainingReminders.php` (already edited in task-B002 step 2 — copy that
version) unchanged. Then add to root's `routes/console.php` (append, don't
overwrite):
```
Schedule::command('photos:prune-old --days=14')->dailyAt('02:00');
Schedule::command('training:send-reminders')->hourly();
```
The 14-day value is still unconfirmed with the client (they said both "14" and
"15" in different messages) — use 14, it's what's currently deployed; a human
can change it in one word once confirmed. Leave source files in
`cleaning/app/Console/Commands/` until a human verifies the scheduler picks
these up (can't be verified in a dry-run — flag for human testing).

---

## task-B005 — status: done
**Port controllers, wire up routes — Option A auth wiring (decided)**

Per MERGE_PLAN.md's decision: root's `AuthController` stays the one login
entry point. Cleaning's Breeze extras (password reset, email verification,
forced password change) get kept and wired in as supplementary routes.
Registration is copied but left unlinked/unregistered for now.

### Step 1 — Auth controllers and routes
1. Copy `cleaning/app/Http/Controllers/Auth/` (all 10 files) unchanged into
   `app/Http/Controllers/Auth/`.
2. Open `cleaning/routes/auth.php` and root's `routes/web.php`. From
   cleaning's auth routes, register ONLY these into root's `routes/web.php`
   (add a new `// Cleaning-ported auth extras` block, don't touch root's
   existing `Route::get('/login', ...)`/`Route::post('/login', ...)`/
   `Route::post('/logout', ...)` lines — those stay exactly as they are):
   - password reset: forgot-password + reset-password routes
     (`PasswordResetLinkController`, `NewPasswordController`)
   - email verification: prompt/notification/verify routes
     (`EmailVerificationPromptController`, `EmailVerificationNotificationController`,
     `VerifyEmailController`)
   - forced password change: `ForcePasswordChangeController`'s route
   - in-session password change: `PasswordController`/`ConfirmablePasswordController`
     routes (used from an authenticated user's profile/settings page)
   Do NOT register cleaning's `login`/`logout` routes
   (`AuthenticatedSessionController`) or its `register` route
   (`RegisteredUserController`) — these stay defined in the controller file
   (already copied) but absent from `routes/web.php`, so the routes simply
   don't exist yet. This is intentional, not an oversight — note it plainly in
   notes.md so nobody "fixes" it later without checking here first.
3. Copy cleaning's 7 `resources/views/auth/*.blade.php` files (all except
   `login.blade.php`, which stays root's) into `resources/views/auth/` —
   `confirm-password.blade.php`, `force-password-change.blade.php`,
   `forgot-password.blade.php`, `register.blade.php`, `reset-password.blade.php`,
   `verify-email.blade.php`. `register.blade.php` has no route pointing at it
   yet per step 2 — that's fine, it just sits unused until a future task wires
   it up if the client ever wants it.
4. These 6 copied views likely `@extends('layouts.guest')` expecting
   cleaning's generic auth-shell layout, but that layout is being renamed to
   `layouts.cleaning-guest` per task-B006 (to avoid colliding with root's
   guest-booking layout) — update the `@extends` line in each of these 6 files
   to `layouts.cleaning-guest`. If task-B006 hasn't run yet, do that rename
   first or coordinate; don't leave these referencing a layout name that
   doesn't exist.

### Step 2 — Role-based redirect after login
5. In `app/Http/Controllers/AuthController.php`, change the `login()` method's
   `return redirect()->intended(route('admin.dashboard'))...` to branch by the
   authenticated user's Spatie role:
   - `admin` → `route('admin.dashboard')` (unchanged, root's existing behavior)
   - `owner`, `manager`, `staff`, `housekeeper`, `company` → `route('dashboard')`
     (confirmed: `cleaning/routes/web.php` line 183 registers
     `Route::get('/dashboard', DashboardController::class)->middleware(['auth','verified'])->name('dashboard')`
     — this name is preserved when its route gets merged into root's
     `routes/web.php` in step 7, so it's safe to hardcode here)
   - If a user has no role, or a role that doesn't map to anything above (data
     issue), fall back to root's existing `admin.dashboard` behavior rather
     than erroring, and log a warning via `ActivityLogService` so a human
     notices a user got misrouted.
   Guests are out of scope here — root's existing guest-session/token flow
   (`GuestController`) doesn't use this login form at all; don't add guests to
   this branch.
9. **Flag, don't fix yet**: cleaning's `dashboard` route carries a `verified`
   middleware (email verification required). Since registration/verification
   aren't publicly linked under this decision, any admin-created user without
   `email_verified_at` set will be blocked from reaching their dashboard after
   login. Note this in notes.md as a risk rather than silently removing the
   `verified` middleware or silently auto-verifying users — a human should
   decide whether admin-created users get auto-verified at creation time
   (likely the right fix, but it's a policy call, not implied by anything
   written here) or whether the middleware should be dropped from this route
   entirely.

### Step 3 — Everything else (controllers + routes)
6. Copy every file in `cleaning/app/Http/Controllers/` into
   `app/Http/Controllers/` unchanged, **except** `Controller.php` — skip that
   one file specifically, since it's a byte-identical trivial abstract base
   class to root's own `app/Http/Controllers/Controller.php` (root's stays).
   Do not rely on a specific count of "how many controllers that leaves" —
   this task has had the wrong number in it twice now (31, then 33; the real
   answer turned out to be 34 named files + `Controller.php` = 35 total, but
   don't hardcode that either). Just apply the rule: copy everything in that
   directory except `Controller.php`, confirm no filename collides with
   anything already in root's `app/Http/Controllers/` (none currently do, but
   check fresh — don't trust this note), and report the actual count you
   copied in notes.md so the real number is on record for once.
7. Merge cleaning's non-auth routes from `cleaning/routes/web.php` into root's
   `routes/web.php`. One collision already confirmed and resolved as of
   2026-09-23 — apply it, don't re-flag it:
   - `Route::get('/', function () { return view('welcome'); })` in cleaning —
     this is Laravel's stock, unmodified Breeze scaffold page, not real
     functionality. **Skip it, don't port it.** Root's `/` (`EarlyAccessController@show`)
     wins outright, no rename needed.
   A second collision was flagged (`/img/{path}`) but couldn't be confirmed
   against the repo when this task was last updated — before doing anything
   else, run `grep -n "img/{path}\|'/img" cleaning/routes/web.php routes/web.php`
   yourself and paste both matching lines into notes.md first. If cleaning's
   route turns out to be a different literal path (e.g. `/file/{path}`, which
   is what an earlier read of this repo found — that does NOT collide with
   root's `/img/{path}`, they're different URIs), note that and proceed, no
   human decision needed. If it's a genuine literal match on `/img/{path}`,
   STOP and log both controllers' full route definitions plus what each one
   actually does (serves from which disk/path, any auth middleware) in
   notes.md, and leave this task blocked — don't guess which should win.
   For any OTHER path collision you find beyond these two: STOP and log it in
   notes.md rather than guessing which should win — don't assume every
   collision resolves as easily as the `/` one above.
8. Once controllers/routes are copied and verified with a dry pass (route
   list check: `docker compose exec app php artisan route:list`, look for
   duplicate URIs — this doesn't touch the database so it's fine to run for
   real, not just `--pretend`), delete the ported controllers, the 6 ported
   auth views, and `cleaning/routes/auth.php` and the ported portion of
   `cleaning/routes/web.php` from `cleaning/`. Leave anything not yet ported
   (if step 7 found an unresolved collision and stopped) in place.

Report in notes.md: the exact dashboard route name used in step 5, any route
collisions found in step 7, and confirmation that `route:list` shows no
duplicate URIs after the merge.

---

## task-B006 — status: done (views, minus the auth-dependent ones)
**Copy cleaning's non-auth views**

`resources/views/admin/`, `components/`, `emails/` directories exist in both
apps but have zero overlapping filenames inside them (checked file-by-file) —
copy cleaning's files into the same-named root directories, don't overwrite
anything, just add alongside.

Also copy these cleaning-only view directories wholesale, unchanged:
`activity/`, `assignments/`, `calendar/`, `dashboard.blade.php`, `profile/`,
`properties/`, `reports/`, `resources/`, `rooms/`, `sessions/`, `settings/`,
`tasks/`, `training/`, `users/`.

One rename needed: `cleaning/resources/views/layouts/guest.blade.php` collides
with root's `layouts/guest.blade.php` (root's is a guest-booking-page shell
with a weather widget; cleaning's is a generic Breeze auth-page shell — totally
different purposes). Copy cleaning's version to
`resources/views/layouts/cleaning-guest.blade.php` instead, and update any
`@extends('layouts.guest')` reference inside the 14 directories above that
actually means cleaning's layout (not root's) to
`@extends('layouts.cleaning-guest')` — grep for `layouts.guest` inside the
copied cleaning views and check each hit's context before renaming it, since a
wrong guess here breaks page rendering, not just a build error.

Leave `cleaning/resources/views/auth/` and the 7 files in it untouched — those
are part of task-B005, blocked on the auth decision.

Do not delete anything from `cleaning/resources/views/` yet, even the folders
you've fully copied — wait until task-B005's controllers reference the new
locations and are verified working, since a view with no controller pointing
at it yet is harmless to leave duplicated, but a missing view breaks things
immediately.

---

## task-B007 — status: done
**Port public assets, including the `cal`/StaySync tool**

Per the decision already made: `cal/` moves over as-is, no integration work,
alongside everything else in this task.

1. Copy `cleaning/public/cal/` to `public/cal/` unchanged (StaySync tool —
   self-contained PHP/HTML/JS, doesn't touch Laravel's DB or auth).
2. Copy `cleaning/public/images/` into `public/images/` — check for filename
   collisions with root's existing `public/images/` first (not yet checked);
   if any exact filename collides, do not overwrite — rename cleaning's copy
   with a `cleaning-` prefix and note it in notes.md so any blade view
   referencing the old path can be caught.
3. Copy `cleaning/public/vendor/ffmpeg/` into `public/vendor/ffmpeg/` if root's
   `public/vendor/` doesn't already have an `ffmpeg` folder (check first).
4. Copy the favicon and any other loose root-level files in `cleaning/public/`
   only if root doesn't already have a same-named file — list what's there
   first, root's existing favicon likely wins per the naming rule, don't
   overwrite it blindly.
5. Do NOT run `php artisan storage:link` in this task — that's task-B009,
   after the final merged public/storage structure is settled.

Report in notes.md: any filename collisions found and how you resolved them.

---

## task-B008 — status: done
**Merge frontend build config**

1. Diff `cleaning/package.json` against root's `package.json` — add any
   dependency cleaning has that root doesn't (check versions don't conflict;
   if a shared dependency has different pinned versions, use root's per the
   naming rule, then flag in notes.md so someone confirms cleaning's code
   still works against the older/newer version root picked).
2. Diff `cleaning/vite.config.js` against root's — add any new entry points
   cleaning needs (its JS/CSS files) to root's config, don't replace root's
   entries.
3. If cleaning has its own `tailwind.config.js`, diff it against root's and
   merge `content` paths / theme extensions — don't replace root's file
   wholesale.
4. Run the frontend build once, inside Docker
   (`docker compose exec app npm install && npm run build` — check the real
   service name in `docker-compose.yml`), and report any build errors in
   notes.md rather than trying to fix unrelated errors yourself.

---

## task-B009 — status: blocked (awaiting backup confirmation and import review)
**Merge both live database backups into the new schema**

This is a data task, not a code task, and needs the client's two DB backups in
hand (root/checkin's and flipstatus/cleaning's) — confirm both are available
before starting. Broad shape (a human should refine this further once the
backups are actually in hand, since exact table-by-table import order and
conflict handling depends on what's actually in them):

1. Restore both backups into separate local/staging databases (never directly
   into the merged schema).
2. Run the full, real (not `--pretend`) migration set from `TASKS_A.md` +
   `TASKS_B.md`'s model/service work against a fresh empty database to get the
   final merged schema.
3. Import root/checkin's data first (it's the "wins on collision" side per the
   client's rule) — properties, users, settings, bookings, etc.
4. Import cleaning's data — rooms, tasks, checklist items, sessions, training
   records, and cleaning's users (cross-reference by email against
   already-imported users rather than blindly inserting duplicates; flag any
   email collision between the two user sets for human review rather than
   auto-merging or auto-renaming).
5. Once import is done and spot-checked, this is the point where TASKS_A
   task-006 gets its real verification (role counts, per-role login test) and
   task-007's `role` column drop can finally run.

This task should be treated as higher-risk than any prior one — a human should
review the import script/approach before it touches anything beyond a
disposable local database.

---

## task-B010 — status: open
**Deploy and test**

1. Push the merged app to the cPanel production environment (no Docker in
   production — direct PHP + `composer install` over SSH per
   MERGE_PLAN.md's environment section).
2. Run the real (non-pretend) migrations against production for the first
   time — only after task-B009's data merge is verified in staging.
3. Verify: login redirects each of the 7 roles to the right place; guest
   portal still works; checklist/session flow works end-to-end; property
   creation defaults checkout time to 10am; notification toggles work.
4. Resume investigating the disappearing logos/task-images bug in the live
   environment — prior leads not yet checked: `TaskMediaController.php`'s
   dedup/usage-count delete logic, `Task.php`'s `deleted` model hook, and
   whether the `public` disk symlink was ever pointed somewhere ephemeral.
5. Decide `cal/`'s fate now that it's live and testable — if nothing breaks
   without it and nobody asks, remove it as part of task-B011.
6. Confirm with the client the 14-vs-15-day photo retention number.

---

## task-B011 — status: open
**Final cleanup verification — the actual "done" checkpoint**

1. Confirm `cleaning/` is empty (or contains only files deliberately deferred
   with a documented reason — there should be none left by this point).
2. Delete the `cleaning/` directory entirely.
3. Confirm no code anywhere still references a `cleaning/`-relative path.
4. Remove `TASKS_A.md`, `TASKS_B.md`, `AGENTS.md`, `MERGE_PLAN.md`, `notes.md`
   from the repo root (or archive them elsewhere) once the merge is confirmed
   stable in production for a reasonable burn-in period — a human decides
   when, not an agent.
