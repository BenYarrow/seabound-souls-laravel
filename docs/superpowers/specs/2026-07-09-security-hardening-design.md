# Pre-launch Security Hardening — Design

**Date:** 2026-07-09
**Status:** Approved (pending spec review)

## Goal

Close the launch-blocking findings from the 2026-07-09 security audit: patch
vulnerable dependencies, rate-limit public endpoints, range-validate the
live-weather coordinates, and document the production-config requirements that
can't be enforced from the repo.

## Background

Audit findings (recorded in `docs/TODO.md`):
- `composer audit`: **Filament forms RichEditor XSS** (CVE-2026-55409, high,
  affects ≤3.3.52 — installed **3.3.49**), Filament unauthenticated temp-file
  upload (CVE-2026-48500, fixed 3.3.53+), guzzle CRLF / proxy-downgrade
  advisories.
- No rate limiting on any public endpoint. `/contact` is spammable (each hit
  writes a row + sends mail); `POST /api/live-weather` proxies OpenWeatherMap
  **with our API key** and is unbounded → quota/cost abuse. Laravel 12 ships no
  default api throttle, and this app defines no rate limiters.
- `/api/live-weather` validates lat/lon as `numeric` but unbounded.
- `.env.example` defaults to `APP_DEBUG=true` / `APP_ENV=local`.

## Decisions

- **Rate limits (per IP, per minute):** search **60**, other `/api/*` **30**,
  `/contact` **5**. ("Balanced".)
- **Soft-delete/slug fix is out of scope** — its own branch later.
- Prod config is **environment**, not code — delivered as a documented checklist
  + annotated `.env.example`, not a commit that "fixes" prod.

## Scope & Components

### 1. Dependency bumps
- `composer update filament/filament guzzlehttp/guzzle guzzlehttp/psr7` — brings
  Filament to the latest **3.3.x** (patch line; closes both Filament CVEs) and
  guzzle to ≥7.12.1. No major-version jumps.
- `npm audit fix` — clears vite / shell-quote / qs (all dev-only; not shipped).
- **Verification:** full `php artisan test` suite green, `npm run build` green,
  and a manual admin smoke check (the Filament bump is the one with UI surface).
- No lockfile churn beyond these packages is expected; if `composer update`
  wants to move anything unexpected, stop and surface it.

### 2. Named rate limiters
- Define three named limiters in `App\Providers\AppServiceProvider::boot()` via
  `RateLimiter::for()`, each keyed by client IP, returning HTTP 429 when
  exceeded:
  - `search` → 60/min
  - `weather-api` → 30/min (the live-weather + weather-data endpoints)
  - `contact` → 5/min
- Attach via middleware in the route files:
  - `routes/api.php`: `throttle:search` on `GET /api/search`; `throttle:weather-api`
    on `POST /api/live-weather`, `GET /api/weather-data`, `GET /api/weather-data/{spotGuide}`.
  - `routes/web.php`: `throttle:contact` on `POST /contact`.
- Rationale for named (not inline `throttle:60,1`) limiters: one place to see/tune
  all limits, per-IP keying is explicit, and the limits are unit-testable via the
  attached middleware.

### 3. Coordinate range validation
- In `Api\LiveWeatherController::fetch`, tighten the rules:
  `lat => ['required','numeric','between:-90,90']`,
  `lon => ['required','numeric','between:-180,180']`. Out-of-range → 422.

### 4. Production-config checklist (docs only)
- Annotate `.env.example` near the app/session keys noting the required
  production values (`APP_ENV=production`, `APP_DEBUG=false`,
  `SESSION_SECURE_COOKIE=true`, HTTPS enforced).
- Add a "Production env" checklist to `docs/TODO.md` (or fold into the existing
  pre-launch section) so it's actioned at deploy. Explicitly **not** a code
  change — the repo cannot set the host's environment.

## Error Handling

- Throttled requests return Laravel's standard `429 Too Many Requests` (JSON for
  api routes, with `Retry-After`). No custom body needed.
- Out-of-range coordinates return `422` with validation messages (existing
  `$request->validate()` behaviour).
- The dependency bump is inert at runtime; risk is confined to the admin UI
  (Filament), covered by the smoke check.

## Testing (TDD)

All HTTP, mocked where needed; no live network.
1. `POST /contact` returns **429** on the 6th request within a minute (real
   end-to-end throttle proof; reCAPTCHA + mail already faked in existing tests).
2. Each rate-limited route **has its `throttle:{name}` middleware attached**
   (assert via the router's gathered middleware) — fast, and proves every
   endpoint is covered without firing 30–60 requests each.
3. The three named limiters are **registered** (`RateLimiter::limiter('search')`
   etc. resolve to a callable).
4. `POST /api/live-weather` with lat=200 / lon=500 → **422** (`Http::fake` so no
   outbound call); a valid in-range request still succeeds (mocked).
5. Full suite stays green after the dependency bump.

## Out of Scope
- Soft-delete/slug reuse fix (separate branch).
- `->afterCommit()` dispatch hardening (separate follow-up, already in TODO).
- 2FA, `canAccessPanel`, admin production password — tracked separately in the
  pre-launch section of `docs/TODO.md`; not this branch.
- Actually setting production env values (host/deploy concern).
