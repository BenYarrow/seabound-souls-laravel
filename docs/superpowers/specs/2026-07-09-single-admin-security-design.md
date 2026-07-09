# Single-admin security hardening — Design

**Date:** 2026-07-09
**Status:** Approved (pending spec review)

## Goal

Harden the single owner admin account before the Laravel Cloud launch: set the
production admin password from an environment variable (never the dev default,
never git), restrict the Filament panel to the owner email, and document the
deploy-time steps. Local development login is unchanged.

## Background

- The only authentication is the Filament admin at `/admin`. The panel calls
  `->login()` only — **no** `->registration()` and **no** `->passwordReset()`.
  The single exposed auth route is `admin/login`. There is no public user system
  and no email-based reset (and none is wanted — the owner controls the deploy).
- Two gaps remain (from the 2026-07-09 audit, tracked in `docs/TODO.md` →
  "Single-admin security"):
  1. `DatabaseSeeder` creates the user with the literal password `password`
     (via `firstOrCreate`) — must not be the production credential.
  2. `User` does not implement `FilamentUser`, so any authenticated user could
     reach `/admin`. Only one user exists today, but the gate should be explicit.
- No email server is required to set or rotate the admin password: reset-by-email
  is only for self-service recovery. The owner seeds/rotates the password
  directly from the host environment.

## Decisions

- **2FA is deferred** (approved) — Filament v3.3 has no built-in 2FA; a plugin +
  migration + enrolment flow is its own later branch. Strong unique password +
  owner-only `canAccessPanel` + HTTPS is sufficient for a single low-profile
  admin. Remains a sub-item in `docs/TODO.md`.
- **Config, not `env()` at call sites.** All reads go through `config('admin.*')`
  so the values survive `config:cache` in production. Calling `env()` inside
  `canAccessPanel()` or a runtime path would return `null` once config is cached
  — a known Filament footgun.
- **`updateOrCreate`, not `firstOrCreate`,** so re-seeding after an
  `ADMIN_PASSWORD` change actually rotates the password.
- Setting the real production env values is a **host/deploy concern** (Laravel
  Cloud dashboard), not a code change.

## Scope & Components

### 1. `config/admin.php` (new)

```php
return [
    // The single owner account. Unset locally → dev defaults (local login
    // unchanged); set in production (Laravel Cloud env vars) to real secrets.
    'email'    => env('ADMIN_EMAIL', 'seabound.souls@outlook.com'),
    'password' => env('ADMIN_PASSWORD', 'password'),
];
```

The only file permitted to read these `env()` keys. Everything else reads
`config('admin.email')` / `config('admin.password')`.

### 2. `User` implements `FilamentUser` (`app/Models/User.php`)

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    // …
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->email === config('admin.email');
    }
}
```

Restricts `/admin` to the owner email even if another user row ever exists.

### 3. `AdminUserSeeder` (new; called from `DatabaseSeeder`)

```php
User::updateOrCreate(
    ['email' => config('admin.email')],
    ['name' => 'Seabound Souls', 'password' => Hash::make(config('admin.password'))],
);
```

- Dedicated seeder so it can run standalone on deploy
  (`php artisan db:seed --class=AdminUserSeeder`) without also running
  `CountriesSeeder`.
- `DatabaseSeeder` calls `AdminUserSeeder` (replacing its inline
  `firstOrCreate`) so local `php artisan db:seed` still creates the owner.
- Keyed on email; rotation = change `ADMIN_PASSWORD`, re-run the seeder. (If the
  owner *email* itself changes, a new row is created and the old one is orphaned
  but locked out by `canAccessPanel` — acceptable for a single-admin site.)

### 4. Docs

- `.env.example` — an admin block documenting `ADMIN_EMAIL` / `ADMIN_PASSWORD`
  (commented out; leave unset locally; set both in production, then run the
  seeder; re-run to rotate).
- `CLAUDE.md` — a short note: auth is single-owner and env-driven; owner email
  gates the panel via `canAccessPanel`.
- `docs/TODO.md` — mark the strong-password + `canAccessPanel` items done
  (remove from the open list, per reconcile rules), keeping 2FA and the
  production-env checklist as the remaining open sub-items.

## Testing (TDD)

All fast, no external dependencies.

1. **`canAccessPanel` allows owner, denies others.** With `config(['admin.email'
   => 'owner@example.com'])`: a `User` with that email → `canAccessPanel()` true;
   a `User` with any other email → false.
2. **`AdminUserSeeder` creates the owner** with the configured email and a
   *hashed* password that verifies against `config('admin.password')`.
3. **Rotation:** seed once; change `config(['admin.password' => 'new-secret'])`;
   seed again → still exactly one matching user, and `Hash::check('new-secret',
   …)` passes (proves `updateOrCreate` updated rather than duplicated).
4. **Existing Filament suite stays green.** Add an `actingAsOwner()` helper to
   the base `TestCase` that creates a `User` with `config('admin.email')` and
   acts as them; replace the `actingAs(User::factory()->create())` calls in the
   `tests/Feature/Filament/` files so they authenticate as a panel-eligible user
   under the new gate.

## Out of Scope

- 2FA (deferred — own branch).
- Setting real production env values (Laravel Cloud dashboard; deploy-time).
- Any change to registration/reset (there is intentionally none).

## Delivery

Branch `feat/single-admin-security`; folded reconcile before merge; PR; dance.
`.env` is not committed. The PR carries `config/admin.php`, the `User` change,
`AdminUserSeeder`, the `DatabaseSeeder` change, the test helper + updated tests,
and docs.
