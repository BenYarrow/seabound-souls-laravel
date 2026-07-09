---
title: Production launch on Laravel Cloud (deploy + data/media migration)
tags: [deployment, laravel-cloud, postgres, object-storage, launch, operations]
status: stable
completed: 2026-07-09
commits: [db92171, d333bd3]
pr: [18, 19]
---

# Laravel Cloud launch

The site went live at `https://seabound-souls-production-ewycw6.laravel.cloud`.
This is an operations retrospective — most of the work was dashboard config and
one-off data migration, not code. Two small code PRs were needed along the way.

## What shipped

- **First deploy** from `main` to a Laravel Cloud project (Starter plan, EU West
  / London), serving via their app cluster with scale-to-zero.
- **Managed database** — Laravel Serverless Postgres 17 (Neon-backed, ¼ compute
  unit, scale-to-zero after 300s, 7-day PITR). Attaching it auto-injects
  `DB_CONNECTION=pgsql` + host/user/pass/db.
- **Object storage** — a public Laravel Object Storage bucket (Cloudflare R2) for
  media, disk name `s3`; `MEDIA_DISK=s3` + `AWS_URL` set so uploads and existing
  media serve from the bucket.
- **Content + media migrated up** from local: all content tables copied into
  Cloud Postgres (parity: 6 countries, 3 spot guides, 1 blog, 5 pages, 5
  recommendations, 119 weather records), and the 14 media files pushed to R2
  with their `media.disk` rewritten to `s3`.

### Code changes required (two PRs)

- **#18 — helpers import case fix** (`db92171`). The first build failed at `vite
  build` (`ENOENT` on `@/helpers/colours`). Root cause: the dir is git-tracked as
  `resources/js/Helpers/` (capital H) but four imports used `@/helpers/`
  (lowercase). macOS is case-insensitive so it passed locally; Linux/Cloud is
  case-sensitive. Aligned all imports to `@/Helpers/`.
- **#19 — add `league/flysystem-aws-s3-v3`** (`d333bd3`). Attaching the bucket
  failed the deploy ("missing the [league/flysystem-aws-s3-v3] package") — the
  S3 filesystem driver's adapter isn't installed by default. Added it (+ AWS SDK).

## Findings worth keeping

- **Case-sensitivity is the classic "works locally, fails on Cloud" trap.** A
  Linux CI `npm run build` (or `eslint-plugin-import` case-sensitive resolution)
  would catch it before deploy — tracked in `docs/TODO.md`.
- **Deploy commands need `--force`.** `php artisan migrate --force` **and**
  `php artisan db:seed --class=AdminUserSeeder --force` — without `--force` the
  seeder hits the "APPLICATION IN PRODUCTION" confirmation and cancels
  non-interactively.
- **The media disk was already env-driven.** Spatie's `config/media-library.php`
  isn't published, so it reads its vendor default `disk_name => env('MEDIA_DISK',
  'public')`. No code change was needed to route media to S3 — just the env var.
  Media URLs come from `getFirstMediaUrl()` (disk-aware) with no hardcoded
  `/storage/`, so migrated rows with `disk='s3'` serve from R2 automatically.
- **Cross-engine data copy gotchas.** `pg_dump | psql` with `-v ON_ERROR_STOP=1`
  silently bailed on pg_dump 16's new `\restrict` meta-command; running plain
  worked. Load content tables in FK-safe order (data-only, excluding `users` so
  the seeded admin is untouched); `pg_dump --data-only` includes `setval()` so
  sequences land correct. R2 addressing used `use_path_style_endpoint=false`.
- **Cost shape.** Starter is $5/mo *usage credit*, not a fee on top; Postgres +
  compute scale to zero, so a low-traffic launch largely stays within the credit.

## Follow-ups / residual

- **Rotate the production DB password + bucket access key** — they were pasted
  into a working session while migrating. (Owner action, Cloud dashboard.)
- **Queue worker** — weather-fetch jobs need a worker; none runs by default on
  the plan. For now drain on demand via the Commands tab
  (`php artisan queue:work --stop-when-empty`); a background process is the
  automatic (but always-awake) option.
- **Remove the one-off SQLite→Postgres migration tooling** once fully retired
  (already tracked): `sqlite_legacy` connection, `db:import-from-sqlite`, the
  stale `database/database.sqlite`.
- **Custom domain + real transactional email** (Project B go-live remainder).
