---
title: Pull production DB to local (db:pull-from-production)
tags: [tooling, database, postgres, laravel-cloud, dev-workflow]
status: stable
completed: 2026-07-11
commits: [260d258]
pr: 31
---

# Pull production DB to local

A `php artisan db:pull-from-production` command that overwrites the local dev
database with a fresh read-only copy of production, so local dev/debug runs
against realistic content. Prod holds far more than a fresh local (7 spot
guides vs 3, 75 media rows vs 14).

## What shipped

- **`pgsql_prod` connection** in `config/database.php`, fed by gitignored
  `PROD_DB_*` env vars (placeholders in `.env.example`). Nothing in the app
  writes through it — the command uses it for a read-only `pg_dump` and a
  server-version probe.
- **The command** (`app/Console/Commands/PullFromProduction.php`): guards →
  `pg_dump` prod (custom format) → `pg_restore --clean --if-exists --no-owner
  --no-privileges` into local → report row counts.
- **Guards, all before any external work:**
  1. local env only (`APP_ENV=local`) — can't run in prod/CI;
  2. `PROD_DB_*` present;
  3. destination host must be local (`127.0.0.1`/`localhost`/`::1`) — the
     command can only ever overwrite local;
  4. `pg_dump` major ≥ server major, with a `brew install postgresql@N` hint;
  5. confirm-before-wipe prompt (`--force` skips).

## Findings worth keeping

- **Prod DB name is `main`**, not `seabound_souls` (Neon's default database
  name on Laravel Cloud). The old `project.md`/notes were wrong — corrected in
  this PR. Prod server is **Postgres 17.10**, reachable externally over SSL
  (`sslmode=require`).
- **pg_dump version gotcha:** `pg_dump` refuses to dump a server newer than
  itself. Local had 16.13 (the `postgresql@16` server's client); dumping the
  PG17 prod server needed `postgresql@17` client tools. The command prefers
  `/opt/homebrew/opt/postgresql@17/bin` (overridable via `PG_DUMP_PATH` /
  `PG_RESTORE_PATH`) and asserts the version before dumping.
- **Benign restore warning:** the PG17 dump emits `SET transaction_timeout`,
  which the local PG16 *server* rejects on restore (`errors ignored on restore:
  1`). Data restores completely (row counts confirm). Upgrading the local
  server to PG17 would silence it — captured as a follow-up.

## Media (out of scope for v1)

DB only. **Gotcha found during verification:** production stores media on the
R2 (`s3`) disk, so the pulled Spatie `media` rows carry `disk = 's3'`. Locally
there is no bucket configured, so the first image URL blew up with
`InvalidArgumentException: The GetObject operation requires non-empty parameter:
Bucket` — a hard **500**, not just a broken `<img>`. So the command now
**repoints every `media` row at the local media-library disk**
(`config('media-library.disk_name')`, i.e. `public`) after the restore. Pages
then render; the files themselves aren't synced, so those images 404 until
`--with-media`. Local `MEDIA_DISK` stays `public`, so local test uploads keep
working and stay isolated from prod. A `--with-media` flag (R2 → local disk
sync, needs read-only R2 creds) is the follow-up for full visual fidelity.

## Test plan

- **Unit** (`tests/Unit/PullFromProductionTest.php`): `majorVersion()` parses
  pg_dump banners + bare server versions; `isLocalHost()` for loopback vs
  remote/empty/null.
- **Feature** (`tests/Feature/Console/PullFromProductionTest.php`): refuses to
  run outside `local`; aborts when `PROD_DB_*` is missing. (Happy path shells
  out to Postgres, verified manually.)
- **Manual:** ran the real pull — local `spot_guides` 3 → 7, `media_library`
  14 → 75, matching prod. Suite **146 passing, 868 assertions**.

## Follow-ups

- `--with-media` — sync R2 objects to local disk so pulled prod images render.
- Optionally upgrade the local Postgres *server* to 17 (matches prod, silences
  the `transaction_timeout` restore notice).
- **Security:** the prod DB password was shared in chat while setting this up —
  rotate it (already on the Cloud owner-tasks list).
