---
title: Pre-launch security hardening (rate limits, coordinate validation, dependency bumps)
tags: [security, rate-limiting, dependencies, validation]
status: stable
completed: 2026-07-09
commits: [8928f5d, 737da32, e9ddf04, 90baa13, 9bc6011, e8bece1, e6428e6]
pr: 15
---

# Pre-launch security hardening

## What shipped

The launch-blocking findings from the 2026-07-09 security audit:

- **Rate limiting** — three per-IP named limiters in `AppServiceProvider::boot()`
  (`RateLimiter::for(...)`), attached via `throttle:{name}` middleware:
  - `search` 60/min → `GET /api/search`
  - `weather-api` 30/min → `POST /api/live-weather`, `GET /api/weather-data`,
    `GET /api/weather-data/{spotGuide}`
  - `contact` 5/min → `POST /contact`
  Laravel 12 ships no default API throttle, so before this every public endpoint
  was unbounded — notably `/api/live-weather` proxies OpenWeatherMap with our key,
  and `/contact` writes a row + sends mail per hit.
- **Coordinate validation** — `/api/live-weather` now validates
  `lat between:-90,90` / `lon between:-180,180` (was unbounded `numeric`);
  out-of-range → 422 before any outbound call.
- **Dependency bumps (within-major)** — cleared all high/medium `composer audit`
  advisories: Filament RichEditor XSS (CVE-2026-55409) + temp-upload
  (CVE-2026-48500) via Filament 3.3.49→3.3.54; guzzle 7.10→7.14; and the
  high/medium advisories in laravel/framework (signed-URL), symfony/mime
  (email-header injection), symfony/http-kernel (SSRF), and
  spatie/laravel-medialibrary 11.21→11.23 (file-upload bypass). `npm audit fix`
  → 0 vulnerabilities.
- **Production-config checklist** — `.env.example` annotations + a `docs/TODO.md`
  checklist for the deploy-time env (`APP_ENV=production`, `APP_DEBUG=false`,
  `SESSION_SECURE_COOKIE=true`, HTTPS, supervised worker). Docs only — the repo
  can't set the host's environment.

Spec: `docs/superpowers/specs/2026-07-09-security-hardening-design.md`.
Plan: `docs/superpowers/plans/2026-07-09-security-hardening.md`.

## Findings worth keeping

- **Surgical vs broad dependency bump.** A surgical bump (only Filament + guzzle)
  was tried first but left **4 HIGH advisories** open in laravel/symfony/spatie —
  packages this app uses for mail (contact form), outbound HTTP (weather proxy),
  and all media. Because `laravel/framework` is coupled to the `symfony/*` family,
  clearing those highs is effectively the broad within-major update. Chosen after
  surfacing the trade-off. A scripted scan confirmed **zero major-version jumps**
  (laravel stays 12.x, symfony 7.x, filament 3.x, spatie 11.x).
- **Filament sub-packages move as a set** (`self.version`), and guzzle needs its
  `promises`/`psr7` sub-deps listed — a partial `composer update filament/filament`
  fails; use `composer update "filament/*" …` or `-W`.
- **Testing rate limits:** the test env uses `CACHE_STORE=array` (per `phpunit.xml`),
  so throttle counters reset per test — no cross-test leakage. The 429 test sends
  5 passthrough requests then asserts the 6th is blocked; the api routes assert
  middleware attachment (via `gatherMiddleware()`) rather than firing 30–60
  requests each.
- **Search box UX is safe under 60/min** — `SearchPanel.tsx` debounces at 250ms,
  min 2 chars, and aborts in-flight requests, so sustained typing stays well under
  the limit.

## Test plan

TDD; all external I/O faked (`Http::fake`). Suite went 85 → **94 passing
(505 assertions)**; `npm run build` green; `composer audit` clear of all
high/medium; `npm audit` 0 vulnerabilities.

## Follow-ups / residual

- **Manual Filament admin smoke-check is PENDING** — the dependency bump has admin
  UI surface (Filament patch line, no major jump), but the automated run has no
  admin login. Do this as part of the pre-launch/deploy check.
- **4 low-severity, dev-only advisories remain** — symfony/dom-crawler +
  symfony/yaml (XXE/ReDoS/Billion-Laughs); dev-only (not shipped), no fix published
  in the 7.4.x constraint window. A future `composer update` picks them up.
- **Still open in `docs/TODO.md` (not this branch):** strong production admin
  password, `User::canAccessPanel()` owner-only, optional 2FA; the `->afterCommit()`
  weather-dispatch hardening; the soft-delete/slug reuse fix.
