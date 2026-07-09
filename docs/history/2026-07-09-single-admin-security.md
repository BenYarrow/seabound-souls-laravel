---
title: Single-admin security hardening (env-driven password + owner-only panel)
tags: [security, filament, auth, seeder, pre-launch]
status: stable
completed: 2026-07-09
commits: [20dee90, 6503546, 5fb70d4, a6f1c6c]
pr: 17
---

# Single-admin security hardening

## What shipped

The two remaining pre-launch items from the "Single-admin security" list, so the
production admin password lives in the host environment (never git) and the
Filament panel is explicitly gated to the owner.

- **`config/admin.php`** — the single owner account's `email` + `password`,
  sourced from `ADMIN_EMAIL` / `ADMIN_PASSWORD` env vars with dev-default
  fallbacks (`seabound.souls@outlook.com` / `password`). This is the **only**
  file permitted to read those env vars; everything else reads
  `config('admin.*')`.
- **Owner-only panel gate** — `User implements FilamentUser` with
  `canAccessPanel()` returning `$this->email === config('admin.email')`. Even if
  a second user row ever existed, only the owner email reaches `/admin`.
- **`AdminUserSeeder`** — `updateOrCreate` keyed on email (replacing the old
  inline `firstOrCreate` in `DatabaseSeeder`), so re-seeding after an
  `ADMIN_PASSWORD` change **rotates** the password instead of duplicating the
  user. Runnable standalone on deploy: `php artisan db:seed --class=AdminUserSeeder`.
- **Docs** — `.env.example` admin block + deploy note; `CLAUDE.md` auth note;
  `docs/TODO.md` pruned (strong-password + `canAccessPanel` items removed; 2FA
  and the production-env checklist remain open).

The panel already exposed only `->login()` (no registration, no password reset),
which is intentional and unchanged — a single-owner site needs no self-service
recovery.

## Findings worth keeping

- **Never read `env()` at a call site — only in `config/*.php`.** `canAccessPanel`
  or a seeder calling `env('ADMIN_EMAIL')` directly would return `null` once
  `php artisan config:cache` runs in production (a common Filament footgun). The
  `config/admin.php` indirection is what makes it `config:cache`-safe.
- **`updateOrCreate`, not `firstOrCreate`,** is the difference between a password
  that can be rotated (change the env var, re-run the seeder) and one frozen at
  first creation. The rotation test proves it: seed → change config password →
  re-seed → exactly one user + `Hash::check` passes for the new secret.
- **The gate email and the seeded email cannot diverge** — both derive from the
  single `config('admin.email')` value, so the account that gets created is
  exactly the account the gate admits. (Changing the owner *email* later orphans
  the old row, which is harmless — the gate locks it out.)
- **Filament Livewire component tests bypass HTTP middleware,** so the existing
  `tests/Feature/Filament/*` suite stayed green even after the gate was added —
  the gate fires on real `/admin` HTTP requests, not in `Livewire::test(...)`.
  The tests were still moved to a shared `actingAsOwner()` TestCase helper (acts
  as a user with `config('admin.email')`) so they authenticate as a
  panel-eligible user and stay correct if Filament ever tightens its test helpers.

## Test plan

TDD; all on in-memory SQLite. Suite **96 → 100 passing (516 assertions)**.

- `canAccessPanel` allows the owner email, denies any other (both asserted).
- `AdminUserSeeder` seeds the owner from config; re-seeding with a changed
  password rotates it without duplicating (single row + new-password hash).
- Full existing suite green under the new gate via the `actingAsOwner()` helper.

Spec: `docs/superpowers/specs/2026-07-09-single-admin-security-design.md`.
Plan: `docs/superpowers/plans/2026-07-09-single-admin-security.md`.

## Follow-ups / residual

- **Deploy step (owner, Laravel Cloud — not in this PR):** set `ADMIN_EMAIL` +
  `ADMIN_PASSWORD` env vars, then run `php artisan db:seed --class=AdminUserSeeder`.
  **Gotcha:** setting `ADMIN_EMAIL` without running the seeder means no row
  matches the gate → the owner is locked out until the seeder runs.
- **2FA remains deferred** — Filament 3.3 has no built-in 2FA; a plugin + TOTP
  enrolment is its own later branch (still open in `docs/TODO.md`).
- Remaining pre-launch: the production-environment checklist (`APP_DEBUG=false`,
  `SESSION_SECURE_COOKIE=true`, HTTPS, supervised worker) and real transactional
  mail (Project B go-live).
