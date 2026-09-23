# Instructions for coding agents (Copilot, opencode, etc.)

Read `MERGE_PLAN.md` first for full context. Read this file fully before doing
anything. Then work **only** from `TASKS.md` — do not invent work, do not "helpfully"
fix things you notice that aren't in an open task, do not reorganize files beyond
what a task explicitly says.

## Hard rules — never do these without a human explicitly adding a task for them
1. Never modify the schema/columns of `properties`, `users`, or `settings` beyond
   exactly what a task specifies. These three tables are the merge's collision
   points and every change to them has already been decided by a human — your
   job is to execute the decision, not make a new one.
2. Never touch role/permission logic (`User::ROLES`, `isOwner()`/`hasRole()` calls,
   `role:` middleware, Spatie config) unless a task explicitly covers it.
3. Never run `php artisan migrate` against a real/production database. Only run
   it against a local/throwaway DB for verification, and say so in your report.
   **Local dev runs entirely in Docker** — do not assume `php`/`composer` exist
   on the bare host. Check `docker-compose.yml`/`Dockerfile` at repo root for
   the actual service name, then run commands through it, e.g.
   `docker compose exec app php artisan migrate --pretend` (adjust service name
   to match what's actually in the compose file — don't guess `app` blindly).
   If Docker isn't running or available when you pick up a task, skip the
   verification step, say so in notes.md, and do not fall back to installing
   PHP on the host or running anything against production instead.
   Production (cPanel) never uses Docker — it runs PHP directly via cPanel and
   `composer install` over SSH. Don't conflate the two environments in any
   command you write or suggest.
4. Never delete anything from `cleaning/` until the copied version is confirmed
   working at the destination. Copy → verify → then delete the source. If you
   can't verify (no working local environment), copy only and say so — leave the
   delete step for a human or a follow-up task.
5. Never install a new composer/npm package unless the task says to.
6. If a task is ambiguous, or you find something unexpected (a file that doesn't
   match what the task describes, a conflict, a test failure), STOP, write what
   you found in `notes.md`, and mark the task `blocked` in `TASKS.md`. Do not guess.

## Workflow
1. Open `TASKS.md`. Find the first task with status `open`.
2. Change its status to `in-progress`.
3. Do exactly what it says — no more, no less.
4. Append your findings to `notes.md` (never overwrite existing content — append
   only, newest at the bottom, with a timestamp and the task ID).
5. Mark the task `done` (or `blocked` with a reason) in `TASKS.md`.
6. Stop. Do not move to the next task automatically unless told to.

## notes.md format
```
## [task-00X] YYYY-MM-DD HH:MM
- What you did
- Any files touched (full paths)
- Anything unexpected found
- Anything you skipped and why
```
