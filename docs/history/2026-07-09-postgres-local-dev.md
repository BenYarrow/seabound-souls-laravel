---
title: Local dev database switched from SQLite to PostgreSQL
tags: [database, postgres, dev-environment, migration, laravel-cloud]
status: stable
completed: 2026-07-09
commits: [0033bd6, 2a86d74, 61dc4e7, 97f8472, 6e38ef1, ad3e629]
pr: 16
---

# Local dev → PostgreSQL

## What shipped

Local development now runs on **PostgreSQL** instead of SQLite, matching the
intended Laravel Cloud production engine (serverless Postgres). All existing
local content was migrated across — nothing lost.

- **`sqlite_legacy` connection** (`config/database.php`) — a read-only,
  fixed-path (`database_path('database.sqlite')`, not env-driven) source
  connection the migration reads from. Kept in place so the migration stays
  re-runnable; a TODO tracks removing it (plus the command and the stale
  `.sqlite` file) once the migration is proven no longer needed.
- **`db:import-from-sqlite` command** (`app/Console/Commands/ImportFromSqlite.php`)
  — copies content tables from `sqlite_legacy` into the default (`pgsql`)
  connection in FK-safe order, **preserving primary keys** (FKs reference them)
  and **resetting each Postgres id sequence** afterward. Two guards: refuses to
  run unless the default connection is `pgsql`, and refuses if any target table
  already holds rows (cannot clobber a populated DB).
- **Docs** — `.env.example` now shows the `pgsql` block (with the local `_dev`
  naming convention documented and a note that `DB_USERNAME` is machine-specific);
  `CLAUDE.md` gained a session-start "Database" prerequisite and a Database-rule
  line; `docs/TODO.md` CI item refined to run against Postgres, plus a cleanup
  follow-up.

The actual connection switch lives in the local (gitignored) `.env`
(`DB_CONNECTION=pgsql`, `DB_DATABASE=seabound_souls_dev`, `DB_USERNAME=benyarrow`)
— **not committed**. The PR carries only config, the command, and docs.

**Naming:** local DB is `seabound_souls_dev`; production is `seabound_souls`.

## Findings worth keeping

- **SQLite → Postgres, the only special-case column type is boolean.** SQLite
  stores booleans as 0/1 integers; a Postgres `boolean` column rejects an
  integer bind. The command discovers boolean columns generically via
  `information_schema.columns` (`data_type = 'boolean'`, schema `public`) and
  casts them to real PHP bools before insert — no hardcoded list. JSON columns
  (stored as valid JSON text in SQLite) bind cleanly into PG `json`; timestamps,
  nullable decimals, and `deleted_at` all copy as-is because the PG schema was
  built from the same migrations (symmetric columns, so `(array) $row` insert
  has no missing/extra keys).
- **`setval`'s `is_called` flag must be a real SQL boolean literal, not a bound
  parameter.** Laravel's `prepareBindings()` coerces a bound PHP `bool` to an
  integer (`1`/`0`); Postgres happens to coerce that back for `setval`, but it's
  a fragile accident. Fix: inline `true`/`false` into the SQL string (safe — a
  controlled internal value, never user input). Empty table →
  `setval(seq, 1, false)` so next id = 1; non-empty → `setval(seq, max(id), true)`
  so next id = max+1. A `max($value, 1)` floor avoids `setval(…, 0)` (rejected).
- **`$table->id()` on Postgres emits `bigserial`** in Laravel 12, so
  `pg_get_serial_sequence(table, 'id')` resolves the backing sequence. (Would
  return NULL — and break `setval` — only if a future migration switched to
  `GENERATED ALWAYS AS IDENTITY`; not the case today.)
- **Postgres is already on the machine.** Homebrew `postgresql@16` runs on
  `127.0.0.1:5432`, superuser is the OS user `benyarrow` (trust auth, empty
  password) — the same shared instance as the other local projects. `pdo_pgsql`
  is loaded in Herd's PHP. No install was needed.
- **Test suite deliberately stays on in-memory SQLite** (`phpunit.xml`
  unchanged) for a fast TDD loop. The dev/prod engine-parity gap this leaves is
  closed by a **Postgres CI job** (refined TODO), not by slowing every local run.

## Test plan (verify-by-execution)

The cross-engine data *copy* can't be unit-tested in the SQLite-only harness
(no PG target, nothing to mock) — an approved TDD exception. The command's
**guard** IS unit-tested (refuses to run without `pgsql`). Everything else was
verified at runtime after switching `.env`:

- **Row-count parity — exact:** users 1, countries 6, media_library 14, media
  14, spot_guides 3, blogs 1, pages 5, recommendations 5, windsurfing_locations
  0, weather_records 119.
- **Suite:** 96 passing (still on SQLite).
- **App vs Postgres:** homepage, a spot guide, `/destinations` (Recharts
  wind/temp charts read `weather_records` + JSON — proves those survived),
  `/blog`, `/contact` all 200; `/admin` 302 (login redirect, expected).
- **Queue write-path:** dispatch inserts into the `jobs` table on PG; the
  dashboard widget's `payload LIKE '%…Job%'` query returns correctly.
- **Sequence sanity:** a fresh `Country` insert got id 7 (max was 6) — no
  duplicate-PK collision. (Test job cleared afterward so no worker fires the
  live weather API.)

Spec: `docs/superpowers/specs/2026-07-09-postgres-local-dev-design.md`.
Plan: `docs/superpowers/plans/2026-07-09-postgres-local-dev.md`.

## Follow-ups / residual

- **Admin visual smoke-check still owner-side** — the automated pass has no
  `/admin` login, so confirm in-browser against Postgres that the media picker
  shows thumbnails and the weather widget renders (page-level 200s already
  proven).
- **Remove the one-off migration tooling once proven** — the `sqlite_legacy`
  connection, the `db:import-from-sqlite` command, and the stale
  `database/database.sqlite` file (tracked in `docs/TODO.md`).
- **Postgres CI job** — run the suite against Postgres in CI to close the
  engine-parity gap the SQLite test suite leaves (folded into the CI TODO item).
