---
updated: 2026-07-05
reconcile: 1
---

# Situation Report — Seabound Souls (Laravel)

Personal project (not IFP). A Laravel 12 / Inertia / React / Filament rebuild of the original Next.js 15 + Sanity windsurfing destination guide, being polished at a relaxed pace toward launch and held to Ben's IFP working standard (TDD, dark mode, responsive, documented code). See `CLAUDE.md` → "Working standard".

## Right now
- **Test harness working** (`RefreshDatabase` + `SCOUT_DRIVER=null`; Scout tests opt into the `collection` engine; external HTTP + mail faked).
- **Blog + SpotGuide + Destinations + Search + Contact slices fully tested + commented** — suite at **34 tests, 249 assertions**.
- Public site renders end-to-end in dev (Laravel `:8000` + Vite `:5173`, Node 22).

## In flight
- Nothing mid-implementation. Next slice not yet started.

## Next action
1. Finish the public-controller sweep — **Pages (catch-all) + Homepage next**.
2. Then helpers (`LiveWeatherController` caching, weather-data transforms), Filament last.
2. Then helpers (weather-data transforms, `LiveWeatherController` caching), Filament last.
3. Separate tracks when wanted: `.nvmrc`, husky/eslint-jsdoc enforcement, dark-mode token layer + responsive audit.

## Roadmap
| Date | Work | PR | History doc |
|---|---|---|---|
| 2026-07-05 | Onboarding, test harness fix, blog test/comment slice | [#1](https://github.com/BenYarrow/seabound-souls-laravel/pull/1) | [2026-07-05-onboarding-test-harness-blog-slice](docs/history/2026-07-05-onboarding-test-harness-blog-slice.md) |
| 2026-07-05 | SpotGuide test/comment slice | [#3](https://github.com/BenYarrow/seabound-souls-laravel/pull/3) | [2026-07-05-spot-guide-slice](docs/history/2026-07-05-spot-guide-slice.md) |
| 2026-07-05 | Destinations test/comment slice | [#4](https://github.com/BenYarrow/seabound-souls-laravel/pull/4) | [2026-07-05-destinations-slice](docs/history/2026-07-05-destinations-slice.md) |
| 2026-07-05 | Search test/comment slice | [#5](https://github.com/BenYarrow/seabound-souls-laravel/pull/5) | [2026-07-05-search-slice](docs/history/2026-07-05-search-slice.md) |
| 2026-07-05 | Contact test/comment slice | [#6](https://github.com/BenYarrow/seabound-souls-laravel/pull/6) | [2026-07-05-contact-slice](docs/history/2026-07-05-contact-slice.md) |

## Baseline (pre-reconcile)
Initial Laravel rebuild + "Editorial Coastal Cinema" redesign of homepage, destinations, contact, spot guide, and search pages predate this first reconcile (commits `ded8a4e`..`5212534`). Design work on those pages is still WIP.
