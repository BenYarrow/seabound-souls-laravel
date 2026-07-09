---
updated: 2026-07-09
reconcile: 4
---

# Situation Report — Seabound Souls (Laravel)

Personal project (not IFP). A Laravel 12 / Inertia / React / Filament rebuild of the original Next.js 15 + Sanity windsurfing destination guide, being polished at a relaxed pace toward launch and held to Ben's IFP working standard (TDD, dark mode, responsive, documented code). See `CLAUDE.md` → "Working standard".

## Right now
- **Test harness working + Vite-independent** (`RefreshDatabase`, `withoutVite()`, `SCOUT_DRIVER=null` with Scout tests on the `collection` engine; external HTTP + mail faked).
- **All public controllers fully tested + commented** (Blog, SpotGuide, Destinations, Search, Contact, Pages, Homepage) — suite at **45 tests, 323 assertions**.
- **Search & navigation UX shipped** — live search dropdown (`/api/search` + `SiteSearch` service), animated desktop search, staggered slide-down mobile menu.
- **Contact enquiries shipped** — submissions persist to `contact_enquiries`; Filament admin inbox (New→Handled + unread nav badge); email demoted to notification. Contact form fully working (reCAPTCHA v3 + mail render fixed). Suite **54 tests, 341 assertions**.
- **Filament custom theme** added — fixes custom-view Tailwind classes not compiling (MediaPicker layout). Admin dark mode is Filament's own (follows OS).
- **Image focal points** — per-image focal point (`media_library`) applied via `object-position` everywhere through one `<CoverImage>`; set by clicking the MediaPicker preview. Fixes mobile masthead cropping.
- **Weather fetch triggers** — shared `WeatherFetcher` service (one fetch path for the weekly command + both jobs); auto-fetch on spot-guide create (`FetchSpotWeatherJob`) with a "queued" toast; dashboard "Fetch all weather" button (`FetchAllWeatherJob` + in-app bell notification) with a status line (in-progress / last-updated); coordinates required + range-validated. Also: repeaters no longer force an empty row (`defaultItems(0)`), unused `timezone` field removed, "View site" link in the admin top bar. Needs a queue worker running. Suite **84 tests, 475 assertions**.
- **Security hardening** — per-IP rate limits (`search` 60/`weather-api` 30/`contact` 5 per min) on `/api/*` + `/contact`; lat/lon range-validation on `/api/live-weather`; within-major dependency bumps clearing all high/medium `composer audit` advisories (Filament XSS, guzzle, laravel/symfony/spatie); prod-config launch checklist. Suite **94 tests, 505 assertions**; `composer audit` clear of high/medium (4 low dev-only remain).
- **Local dev on PostgreSQL** — matches Laravel Cloud (serverless Postgres). Local DB `seabound_souls_dev` on Homebrew `postgresql@16` (`127.0.0.1:5432`, OS-user auth); prod will be `seabound_souls`. All prior SQLite content migrated across intact via a one-off `db:import-from-sqlite` command (FK-safe order, PKs preserved, sequences reset). Test suite stays on in-memory SQLite for speed (Postgres CI job is the parity mitigation). Suite **96 tests, 509 assertions**.
- **Local env on Herd** — app at `https://seaboundsouls.test`; mail captured in Herd's Mail tab (`MAIL_MAILER=smtp`, :2525).
- Public site renders end-to-end in dev (Laravel + Vite, Node 22).

## In flight
- **Local dev → PostgreSQL** — PR #16 open (folded reconcile rides in it). Pending: manual `/admin` visual smoke-check against Postgres (media picker thumbnails, weather widget) before deploy.

## Next action
1. Test the remaining **helper/API units** — `Api\WeatherDataController`, weather-data transforms, `LiveWeatherController` caching (its coordinate-validation path is now covered); then Filament smoke tests.
2. Standing pre-launch security: strong production admin password, `User::canAccessPanel()` owner-only, optional 2FA (see `docs/TODO.md`).
3. Standing tracks: CI pipeline (**against Postgres** — closes the engine-parity gap the SQLite test suite leaves), `.nvmrc`, husky/eslint-jsdoc, dark-mode token layer + responsive audit. Smaller follow-ups: `->afterCommit()` weather-dispatch hardening, soft-delete/slug reuse fix, remove the one-off SQLite→Postgres migration tooling once proven.
4. **Project B — go-live:** deploy Laravel (Postgres now matches the target host), point `seaboundsouls.co.uk` at it, real transactional email + DNS, `APP_DEBUG=false` + supervised queue worker.

## Roadmap
| Date | Work | PR | History doc |
|---|---|---|---|
| 2026-07-05 | Onboarding, test harness fix, blog test/comment slice | [#1](https://github.com/BenYarrow/seabound-souls-laravel/pull/1) | [2026-07-05-onboarding-test-harness-blog-slice](docs/history/2026-07-05-onboarding-test-harness-blog-slice.md) |
| 2026-07-05 | SpotGuide test/comment slice | [#3](https://github.com/BenYarrow/seabound-souls-laravel/pull/3) | [2026-07-05-spot-guide-slice](docs/history/2026-07-05-spot-guide-slice.md) |
| 2026-07-05 | Destinations test/comment slice | [#4](https://github.com/BenYarrow/seabound-souls-laravel/pull/4) | [2026-07-05-destinations-slice](docs/history/2026-07-05-destinations-slice.md) |
| 2026-07-05 | Search test/comment slice | [#5](https://github.com/BenYarrow/seabound-souls-laravel/pull/5) | [2026-07-05-search-slice](docs/history/2026-07-05-search-slice.md) |
| 2026-07-05 | Contact test/comment slice | [#6](https://github.com/BenYarrow/seabound-souls-laravel/pull/6) | [2026-07-05-contact-slice](docs/history/2026-07-05-contact-slice.md) |
| 2026-07-05 | Pages + Homepage slice; Vite-independent suite | [#7](https://github.com/BenYarrow/seabound-souls-laravel/pull/7) | [2026-07-05-pages-homepage-slice](docs/history/2026-07-05-pages-homepage-slice.md) |
| 2026-07-05 | Search & navigation UX (live dropdown, animations) | [#8](https://github.com/BenYarrow/seabound-souls-laravel/pull/8) | [2026-07-05-search-nav-ux](docs/history/2026-07-05-search-nav-ux.md) |
| 2026-07-05 | Contact enquiries (in-app inbox + notification) | [#9](https://github.com/BenYarrow/seabound-souls-laravel/pull/9) | [2026-07-05-contact-enquiries](docs/history/2026-07-05-contact-enquiries.md) |
| 2026-07-06 | Filament custom theme + MediaPicker layout fix | [#10](https://github.com/BenYarrow/seabound-souls-laravel/pull/10) | [2026-07-06-filament-media-picker-ui](docs/history/2026-07-06-filament-media-picker-ui.md) |
| 2026-07-06 | Image focal points | [#11](https://github.com/BenYarrow/seabound-souls-laravel/pull/11) | [2026-07-06-image-focal-points](docs/history/2026-07-06-image-focal-points.md) |
| 2026-07-06 | Content-manageable destinations masthead | [#12](https://github.com/BenYarrow/seabound-souls-laravel/pull/12) | [2026-07-06-destinations-page-masthead](docs/history/2026-07-06-destinations-page-masthead.md) |
| 2026-07-09 | Single-admin security captured as pre-launch task | [#13](https://github.com/BenYarrow/seabound-souls-laravel/pull/13) | _(docs-only; see `docs/TODO.md`)_ |
| 2026-07-09 | Weather fetch triggers (auto-on-create + dashboard button) | [#14](https://github.com/BenYarrow/seabound-souls-laravel/pull/14) | [2026-07-09-weather-fetch-triggers](docs/history/2026-07-09-weather-fetch-triggers.md) |
| 2026-07-09 | Security hardening (rate limits, coordinate validation, dependency bumps) | [#15](https://github.com/BenYarrow/seabound-souls-laravel/pull/15) | [2026-07-09-security-hardening](docs/history/2026-07-09-security-hardening.md) |
| 2026-07-09 | Local dev database switched from SQLite to PostgreSQL | [#16](https://github.com/BenYarrow/seabound-souls-laravel/pull/16) | [2026-07-09-postgres-local-dev](docs/history/2026-07-09-postgres-local-dev.md) |

## Baseline (pre-reconcile)
Initial Laravel rebuild + "Editorial Coastal Cinema" redesign of homepage, destinations, contact, spot guide, and search pages predate this first reconcile (commits `ded8a4e`..`5212534`). Design work on those pages is still WIP.
