# Pull Production DB to Local — Design

**Date:** 2026-07-11
**Status:** Approved (design)

## Goal

A `php artisan` command that overwrites the local dev database with a fresh copy
of production, so local dev/debug runs against realistic content (prod has far
more than local — 7 spot guides vs 3, 75 media rows vs ~14). Read-only on
production; destructive only on the local database.

## Environment facts (probed 2026-07-11)

- Production DB is **Laravel Cloud / Neon serverless Postgres**, database name
  **`main`** (not `seabound_souls` — the old `project.md`/`CLAUDE.md` note is
  wrong and is corrected as part of this work). Reachable externally over SSL
  (`sslmode=require`); host/user/password authenticate fine.
- Production server is **PostgreSQL 17.10**. Local client tooling was
  `pg_dump` 16.13 — too old (pg_dump refuses to dump a newer server). Resolved
  by installing `postgresql@17` client tools locally.
- Local dev DB: `seabound_souls_dev` on Homebrew `postgresql@16` at
  `127.0.0.1:5432` (the `pgsql` default connection).

## Command

`php artisan db:pull-from-production {--force}`

### Connection

Production is a dedicated **`pgsql_prod`** connection in
`config/database.php`, fed by gitignored `PROD_DB_*` env vars:

```
PROD_DB_HOST, PROD_DB_PORT (5432), PROD_DB_DATABASE (main),
PROD_DB_USERNAME, PROD_DB_PASSWORD, PROD_DB_SSLMODE (require)
```

Real values live only in local `.env` (gitignored). `.env.example` gets empty
placeholders so the shape is documented and committed.

### Safety guards (all before any work)

1. **Local-only env:** abort unless `app()->environment('local')`. This means it
   can never run in production or CI (test env is `testing` → aborts).
2. **Destination must be local:** the target (`pgsql` default connection) host
   must be `127.0.0.1` / `localhost` / `::1`. Abort otherwise. The command can
   only ever overwrite the local DB.
3. **Prod config present:** abort with guidance if any `PROD_DB_*` is missing.
4. **Client version:** `pg_dump` major must be ≥ prod server major, else abort
   with an install hint. The command locates a new-enough `pg_dump` (PATH, then
   `/opt/homebrew/opt/postgresql@17/bin`), overridable via `PG_DUMP_PATH` /
   `PG_RESTORE_PATH`.
5. **Confirmation:** unless `--force`, show `source (prod host/db) → destination
   (local db)`, warn that the local DB will be **erased**, and require a yes.

### Flow

1. Run guards.
2. `pg_dump` production → temp custom-format file (`-Fc`), read-only on prod.
3. `pg_restore --clean --if-exists --no-owner --no-privileges` into the local
   `pgsql` database (drops existing objects first).
4. Delete the temp file. Report row counts (e.g. `spot_guides`, `blogs`,
   `media_library`) after restore.

### Media

Out of scope for v1 (DB only). Local `MEDIA_DISK` stays `public` (local disk),
so local test uploads keep working and stay isolated from prod. Pulled prod rows
reference R2 object paths that don't exist locally, so those images won't render
locally — acceptable for content/data work. A later `--with-media` flag can sync
the R2 objects down to local disk for full visual fidelity (needs read-only R2
credentials).

## Testing

Tests run on SQLite and must not shell out to `pg_dump` or touch the network, so
coverage targets the pure/guard logic:

- **Unit** (pure helpers): `pg_dump`-version-string → major int; server-version
  → major int; version-compatibility check; `isLocalHost()` for
  `127.0.0.1` / `localhost` / `::1` vs a remote host.
- **Feature** (`$this->artisan(...)`): aborts in a non-`local` environment;
  aborts when `PROD_DB_*` config is absent. (The happy path shells out to
  Postgres and is verified manually by running the real pull.)

Manual verification: run the real command against prod → local and confirm the
local row counts jump to match prod (7 spot guides, 75 media rows).

## Out of scope

- R2 media sync (`--with-media`) — follow-up.
- Any write path to production (the command never writes to prod).
- Pushing local → prod (explicitly not built).
