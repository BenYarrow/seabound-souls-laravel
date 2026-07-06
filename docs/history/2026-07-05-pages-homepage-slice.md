---
title: Pages + Homepage test slice; Vite-independent test suite
tags: [testing, pages, homepage, harness]
status: stable
completed: 2026-07-05
commits: [01b61ea]
pr: 7
---

# Pages + Homepage test slice (+ Vite-independent suite)

Sixth slice — completes the public-controller sweep. Both remaining controllers plus a harness robustness fix.

## What shipped
- **`PageControllerTest`** (3): published render, 404 on draft, 404 on unknown slug.
- **`HomepageControllerTest`** (4): renders without a home Page record (meta defaults), featured guides capped at 6, recent blogs capped at 3, infographic blocks enriched with live published stats.
- Comments: module headers + PHPDoc on `PageController` and `HomepageController`.

## Findings worth keeping
- **The suite was silently depending on the Vite dev server.** Inertia responses render the root Blade view, which invokes `@vite`. While `npm run dev` was running, `public/hot` existed and Laravel used the dev-server URL — no manifest lookup, tests passed. The moment Vite was stopped, `public/hot` disappeared and every Inertia-rendering test 500'd with *"Unable to locate file in Vite manifest: resources/css/app.css"*. **Fix:** `TestCase::setUp()` now calls `$this->withoutVite()`, stubbing Vite so the whole suite is independent of dev-server / build state. This is the kind of fragility a real CI run (no dev server) would have exposed immediately — worth a CI pipeline as a follow-up.
- **Homepage infographic enrichment** only runs its extra count queries when the content builder actually contains an `infographic` block — verified by `test_index_enriches_infographic_blocks_with_published_stats`.

## Test plan
`php artisan test` → 41 passed, 313 assertions. `/` renders (200).

## Follow-ups
See `docs/TODO.md`. Public-controller sweep is now complete. Remaining: helper units (`LiveWeatherController` caching, weather-data transforms), Filament smoke tests, and the standing tooling/frontend tracks (`.nvmrc`, husky/JSDoc, dark mode, responsive, CI).
