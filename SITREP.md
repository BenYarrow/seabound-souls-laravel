---
updated: 2026-07-05
reconcile: 1
---

# Situation Report — Seabound Souls (Laravel)

Personal project (not IFP). A Laravel 12 / Inertia / React / Filament rebuild of the original Next.js 15 + Sanity windsurfing destination guide, being polished at a relaxed pace toward launch and held to Ben's IFP working standard (TDD, dark mode, responsive, documented code). See `CLAUDE.md` → "Working standard".

## Right now
- **Test harness working + Vite-independent** (`RefreshDatabase`, `withoutVite()`, `SCOUT_DRIVER=null` with Scout tests on the `collection` engine; external HTTP + mail faked).
- **All public controllers fully tested + commented** (Blog, SpotGuide, Destinations, Search, Contact, Pages, Homepage) — suite at **45 tests, 323 assertions**.
- **Search & navigation UX shipped** — live search dropdown (`/api/search` + `SiteSearch` service), animated desktop search, staggered slide-down mobile menu.
- **Contact enquiries shipped** — submissions persist to `contact_enquiries`; Filament admin inbox (New→Handled + unread nav badge); email demoted to notification (suite now **53 tests, 339 assertions**).
- **Local env on Herd** — app at `https://seaboundsouls.test`; mail captured in Herd's Mail tab (`MAIL_MAILER=smtp`, :2525).
- Public site renders end-to-end in dev (Laravel + Vite, Node 22).

## In flight
- Nothing mid-implementation. Next slice not yet started.

## Next action
1. Test the remaining **helper/API units** — `LiveWeatherController` (cached external weather), `Api\WeatherDataController`, weather-data transforms.
2. Then Filament smoke tests.
3. Standing tracks: CI pipeline, `.nvmrc`, husky/eslint-jsdoc, dark-mode token layer + responsive audit, rate-limit `/api/search`, restrict Filament panel access.
4. **Project B — go-live:** deploy Laravel, point `seaboundsouls.co.uk` at it, real transactional email + DNS.

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

## Baseline (pre-reconcile)
Initial Laravel rebuild + "Editorial Coastal Cinema" redesign of homepage, destinations, contact, spot guide, and search pages predate this first reconcile (commits `ded8a4e`..`5212534`). Design work on those pages is still WIP.
