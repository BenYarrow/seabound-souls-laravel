---
updated: 2026-07-09
reconcile: 7
---

# Situation Report — Seabound Souls (Laravel)

Personal project (not IFP). A Laravel 12 / Inertia / React / Filament rebuild of the original Next.js 15 + Sanity windsurfing destination guide, being polished at a relaxed pace toward launch and held to Ben's IFP working standard (TDD, dark mode, responsive, documented code). See `CLAUDE.md` → "Working standard".

## Right now
- **🚀 LIVE on Laravel Cloud** — `https://seabound-souls-production-ewycw6.laravel.cloud`. Serverless Postgres 17 + public R2 object-storage bucket for media (`MEDIA_DISK=s3`); env-driven admin login seeded on deploy. All local content + 14 media files migrated up. First deploy needed two fixes: import-case (#18) and the `league/flysystem-aws-s3-v3` adapter (#19). See [2026-07-09-laravel-cloud-launch](docs/history/2026-07-09-laravel-cloud-launch.md). **Owner follow-ups:** rotate DB/bucket secrets; queue worker; custom domain + real email.
- **Site-wide editable SEO** — title/description/keywords/OG image are now editable per page via the Pages admin (system/listing pages read SEO from their `Page` record, like the homepage already did; a `contact` Page is seeded). The Layout renders `<meta name="keywords">` (was never output before) and the doubled `| Seabound Souls` title suffix is fixed (bare titles + one global suffix). Suite **107 tests, 625 assertions**.
- **Spot-guide mobile layout + favicon** — masthead overview drops below the image on mobile (flex-wrapped 3+2), live-weather stays a floating card over the image at all breakpoints; branded favicon served as PNG. Desktop unchanged.
- **Test harness working + Vite-independent** (`RefreshDatabase`, `withoutVite()`, `SCOUT_DRIVER=null` with Scout tests on the `collection` engine; external HTTP + mail faked).
- **All public controllers fully tested + commented** (Blog, SpotGuide, Destinations, Search, Contact, Pages, Homepage) — suite at **45 tests, 323 assertions**.
- **Search & navigation UX shipped** — live search dropdown (`/api/search` + `SiteSearch` service), animated desktop search, staggered slide-down mobile menu.
- **Contact enquiries shipped** — submissions persist to `contact_enquiries`; Filament admin inbox (New→Handled + unread nav badge); email demoted to notification. Contact form fully working (reCAPTCHA v3 + mail render fixed). Suite **54 tests, 341 assertions**.
- **Filament custom theme** added — fixes custom-view Tailwind classes not compiling (MediaPicker layout). Admin dark mode is Filament's own (follows OS).
- **Image focal points** — per-image focal point (`media_library`) applied via `object-position` everywhere through one `<CoverImage>`; set by clicking the MediaPicker preview. Fixes mobile masthead cropping.
- **Weather fetch triggers** — shared `WeatherFetcher` service (one fetch path for the weekly command + both jobs); auto-fetch on spot-guide create (`FetchSpotWeatherJob`) with a "queued" toast; dashboard "Fetch all weather" button (`FetchAllWeatherJob` + in-app bell notification) with a status line (in-progress / last-updated); coordinates required + range-validated. Also: repeaters no longer force an empty row (`defaultItems(0)`), unused `timezone` field removed, "View site" link in the admin top bar. Needs a queue worker running. Suite **84 tests, 475 assertions**.
- **Security hardening** — per-IP rate limits (`search` 60/`weather-api` 30/`contact` 5 per min) on `/api/*` + `/contact`; lat/lon range-validation on `/api/live-weather`; within-major dependency bumps clearing all high/medium `composer audit` advisories (Filament XSS, guzzle, laravel/symfony/spatie); prod-config launch checklist. Suite **94 tests, 505 assertions**; `composer audit` clear of high/medium (4 low dev-only remain).
- **Single-admin security hardened** — production admin password is env-driven (`ADMIN_EMAIL`/`ADMIN_PASSWORD` via `config/admin.php`, dev defaults preserved locally), seeded by `AdminUserSeeder` (`updateOrCreate` → re-seed to rotate). The Filament panel is gated to the owner email via `User::canAccessPanel()`. No registration / no password-reset (intentional). Suite **100 tests, 516 assertions**. Remaining pre-launch: 2FA (deferred) + production-env checklist.
- **Local dev on PostgreSQL** — matches Laravel Cloud (serverless Postgres). Local DB `seabound_souls_dev` on Homebrew `postgresql@16` (`127.0.0.1:5432`, OS-user auth); prod will be `seabound_souls`. All prior SQLite content migrated across intact via a one-off `db:import-from-sqlite` command (FK-safe order, PKs preserved, sequences reset). Test suite stays on in-memory SQLite for speed (Postgres CI job is the parity mitigation). Suite **96 tests, 509 assertions**.
- **Local env on Herd** — app at `https://seaboundsouls.test`; mail captured in Herd's Mail tab (`MAIL_MAILER=smtp`, :2525).
- Public site renders end-to-end in dev (Laravel + Vite, Node 22).

## In flight
- **Site-wide editable SEO** — PR #21 open (folded reconcile rides in it). After merge, the `contact` Page migration runs on deploy.
- **Windsurfing-spot repeater polish** — brainstormed/agreed, not yet started: drop the redundant `sort_order` number field (drag-reorder already works via `orderColumn`) and add an image MediaPicker to the windsurfing-locations repeater (model+card already support it).

## Next action
1. **Post-launch owner tasks:** rotate the production DB password + bucket access key (Cloud dashboard); decide the queue-worker approach (on-demand drain vs background process); custom domain (`seaboundsouls.co.uk`) + real transactional email/DNS.
2. Remaining pre-launch security: optional **2FA** (deferred — Filament plugin + TOTP). (Production-env basics `APP_ENV`/`APP_DEBUG=false`/`SESSION_SECURE_COOKIE` are set on Cloud.)
3. Standing tracks: CI pipeline (**against Postgres**, + a Linux `npm run build` to catch import-case bugs — see #18), `.nvmrc`, husky/eslint-jsdoc, dark-mode token layer + responsive audit. Smaller follow-ups: `->afterCommit()` weather-dispatch hardening, soft-delete/slug reuse fix, remove the one-off SQLite→Postgres migration tooling once proven.
4. Test the remaining **helper/API units** — `Api\WeatherDataController`, weather-data transforms, `LiveWeatherController` caching; then Filament smoke tests.

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
| 2026-07-09 | Single-admin security (env-driven password + owner-only panel) | [#17](https://github.com/BenYarrow/seabound-souls-laravel/pull/17) | [2026-07-09-single-admin-security](docs/history/2026-07-09-single-admin-security.md) |
| 2026-07-09 | **Launched on Laravel Cloud** (deploy + data/media migration) | [#18](https://github.com/BenYarrow/seabound-souls-laravel/pull/18), [#19](https://github.com/BenYarrow/seabound-souls-laravel/pull/19) | [2026-07-09-laravel-cloud-launch](docs/history/2026-07-09-laravel-cloud-launch.md) |
| 2026-07-09 | Spot-guide mobile masthead layout + branded favicon | [#20](https://github.com/BenYarrow/seabound-souls-laravel/pull/20) | [2026-07-09-spotguide-mobile-and-favicon](docs/history/2026-07-09-spotguide-mobile-and-favicon.md) |
| 2026-07-09 | Site-wide editable SEO (title/description/keywords/OG) | [#21](https://github.com/BenYarrow/seabound-souls-laravel/pull/21) | [2026-07-09-site-wide-seo](docs/history/2026-07-09-site-wide-seo.md) |

## Baseline (pre-reconcile)
Initial Laravel rebuild + "Editorial Coastal Cinema" redesign of homepage, destinations, contact, spot guide, and search pages predate this first reconcile (commits `ded8a4e`..`5212534`). Design work on those pages is still WIP.
