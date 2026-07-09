# Local dev → PostgreSQL (dev/prod parity) — Design

**Date:** 2026-07-09
**Status:** Approved (pending spec review)

## Goal

Switch local development from SQLite to PostgreSQL so the local database engine
matches the intended Laravel Cloud production engine (serverless Postgres),
closing the largest dev/prod gap before launch. Preserve all existing local
content in the move.

## Background

- Local dev currently runs on **SQLite** (`DB_CONNECTION=sqlite`, file at
  `database/database.sqlite`). Sessions, cache, and the jobs queue also live in
  that file (`SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION=database`).
- **Laravel Cloud does not host SQLite.** Its managed database options are
  serverless **Postgres** (flagship/default) or MySQL, plus a Valkey/Redis KV
  store. Production will therefore be Postgres.
- SQLite is loose about types, JSON columns, booleans and dates; Postgres is
  strict. A query that passes on SQLite can fail in production. Doing this now,
  pre-launch, catches those bugs while it is cheap.
- The local SQLite DB holds **real test content worth preserving**: 3 spot
  guides, 14 media_library images (+ 14 Spatie `media` rows), 119 weather
  records, 5 recommendations, 6 countries, 1 blog, 5 pages, 1 user.

### Machine state (already verified)

- PostgreSQL **16.13** running via Homebrew on `127.0.0.1:5432`; connectable as
  OS user `benyarrow` (superuser, local trust auth, empty password) — same setup
  as the other local project DBs on this machine.
- `pdo_pgsql` is loaded in Herd's PHP.
- `config/database.php` already defines a `pgsql` connection block (Laravel 12
  default). No `seabound_souls_dev` database exists yet.

## Decisions

- **Engine:** PostgreSQL (matches Laravel Cloud). Confirmed by user.
- **DB naming:** local **`seabound_souls_dev`**; production **`seabound_souls`**
  (Laravel Cloud provisions its own instance; `DB_DATABASE` there points at
  whatever the managed DB is called). Clean dev/prod split.
- **Data:** migrate all existing content across (approach A below) — nothing
  lost.
- **Session / Cache / Queue drivers stay on `database`** (now backed by
  Postgres). Queue must stay `database` for the weather worker; sessions/cache
  on the DB is fine. Redis/Valkey KV is a *future* option (out of scope — one
  change at a time).
- **Test suite stays on in-memory SQLite** (`phpunit.xml` unchanged) for the
  fast TDD loop; the dev/prod parity gap is closed by a future Postgres CI job
  (refined TODO), not by slowing every local test run.
- **TDD exception (explicitly approved):** the one-off cross-engine transfer
  command cannot be meaningfully unit-tested inside the in-memory-SQLite harness
  (no second engine to target, nothing external to mock). It is **verified by
  execution** — row-count parity + app rendering — per the test plan below.

## Approach A — one-off artisan transfer (chosen)

Rejected alternatives: **B `pgloader`** (infers its own schema types, can drift
from the migration-defined schema, extra tool); **C generate a seeder from
current data** (bulky for 119 weather rows + media metadata, not reusable).

## Scope & Components

### 1. Postgres database + local `.env` (not committed)

- Create the `seabound_souls_dev` database (owner `benyarrow`).
- Point local `.env` at it: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`,
  `DB_PORT=5432`, `DB_DATABASE=seabound_souls_dev`, `DB_USERNAME=benyarrow`,
  `DB_PASSWORD=` (empty). `.env` is gitignored — this switch is local-only.

### 2. Temporary legacy source connection (committed)

- Add a `sqlite_legacy` connection to `config/database.php` reading
  `database/database.sqlite` (read-only source for the transfer). It is inert in
  production and can be removed in a later cleanup once the migration is done and
  proven (noted as a follow-up, not deleted in this branch so the command stays
  runnable/re-runnable).

### 3. Schema build

- `php artisan migrate` against the empty `seabound_souls_dev` — schema comes
  from the existing migrations (authoritative, correct Postgres types).

### 4. `db:import-from-sqlite` command (committed)

- Copies content tables **in FK-safe order**, preserving primary keys:
  `users → countries → media_library → media → spot_guides → blogs → pages →
  recommendations → windsurfing_locations → weather_records`.
- After each table, **resets the Postgres ID sequence** to `MAX(id)+1` (via
  `setval(pg_get_serial_sequence(...), ...)`) — otherwise the next insert
  collides on a duplicate PK.
- **Not copied:** `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`,
  `failed_jobs`, `migrations`, `password_reset_tokens` — transient/regenerated.
- Media *files* on disk (`storage/app/public`) are untouched; `media` rows
  reference them by UUID and keep working after `storage:link`.
- **Guards:** refuses to run unless the default connection is `pgsql` and the
  target content tables are empty (cannot clobber a populated DB). Reads the
  source strictly from the `sqlite_legacy` connection.

### 5. Docs (committed)

- `.env.example` — document `pgsql` as the local default; illustrative name
  `seabound_souls` with a comment that the local convention is the `_dev` suffix.
- `CLAUDE.md` — session-start / Database sections: local dev is Postgres now;
  note "start Postgres (Homebrew service on :5432)" as a prerequisite alongside
  the Node-22 note; `_dev` DB name.
- `SITREP.md` — "right now" bullet + roadmap row.
- `docs/TODO.md` — refine the CI item to state the suite should run against
  Postgres in CI; add a follow-up to remove the `sqlite_legacy` connection once
  the migration is proven.

## Error Handling

- Transfer command aborts with a clear message if run on a non-`pgsql` default
  connection or against non-empty target tables.
- FK-safe ordering guarantees parents exist before children; a missing parent
  row surfaces as a normal FK violation (fail loud, not silent).
- Sequence reset is idempotent (`setval` to current max) — re-running after a
  truncate is safe.

## Testing (verification by execution — see TDD exception)

1. **Row-count parity:** each copied table has identical counts in SQLite vs
   Postgres (spot_guides 3, media_library 14, media 14, weather_records 119,
   recommendations 5, countries 6, blogs 1, pages 5, users 1).
2. **`php artisan test`** — full suite still green (on SQLite).
3. **In-browser against Postgres (Herd):** homepage; a spot guide page;
   `/destinations` (Recharts wind/temp charts read `weather_records` — proves
   data + JSON columns survived); `/admin` loads; media picker shows thumbnails;
   weather widget renders.
4. **Queue path:** dispatch one real weather fetch through the worker against
   Postgres; confirm it writes rows (proves the `payload LIKE` job query + write
   path on PG).
5. **Sequence sanity:** create + save a new record in `/admin`; no duplicate-PK
   error (proves sequences were reset).

## Out of Scope

- Moving sessions/cache/queue to Redis/Valkey KV (future).
- Moving the test suite off in-memory SQLite (future CI Postgres job instead).
- Removing the `sqlite_legacy` connection (follow-up once migration is proven).
- Any production/Laravel Cloud provisioning (separate go-live effort).

## Delivery

One branch `feat/postgres-local-dev`; folded reconcile before merge; PR; dance.
The `.env` switch is applied on the local machine and is **not** committed. The
PR carries `config/database.php` (both new connections), the
`db:import-from-sqlite` command, and the doc/`.env.example` updates.
