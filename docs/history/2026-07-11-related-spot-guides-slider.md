---
title: Related spot guides slider
tags: [spot-guides, frontend, swiper, weather-ranking, inertia]
status: stable
completed: 2026-07-11
commits: [1bcd259, d9d0b4b, d385c27, bdf5d82, 3b74bc2]
pr: 29
---

# Related spot guides slider

A closing "explore more" section at the bottom of every spot-guide page: a
full-bleed, one-slide-per-view slider of *other* guides, so a visitor reading
one destination discovers nearby ones. Ports the original Next.js
`RelatedSpotGuides` concept onto Laravel/Inertia/Swiper.

## What shipped

**Relation cascade (controller).** `SpotGuideController@show` now builds a
`related_spot_guides` prop by cascading:

1. other **published** guides in the **same country** (excluding the current
   guide + drafts); if any, use those;
2. else other published guides in the **same continent**; if any, use those;
3. else nothing — the section is hidden.

`relation` (`'country'` | `'continent'` | `null`) and a humanised `label`
(country name, or `europe → Europe`, `north-america → North America`) drive the
heading. A guide with no `country_id` short-circuits straight to the hidden
case with no query.

**Ordering** mirrors the destinations index exactly: the **featured** guide
leads (the single-featured invariant means at most one), then the rest by
**gustiest this month** — current-year, current-month `kts_gust` descending,
no-current-reading last, ties by title. Implemented as
`sortByGustiestThisMonth(...)->sortByDesc('is_featured')->values()`, relying on
Laravel's stable sort so the featured graft preserves gust order among the rest.

**Shared sorter.** The gustiest-this-month comparator, previously inline in
`DestinationController`, was extracted to a reusable
`SpotGuide::sortByGustiestThisMonth(Collection $guides): Collection` and
`DestinationController` refactored to call it — one tested source of truth for
both pages. Behaviour on the destinations page is unchanged (its existing
regression tests still pass).

**The slider (`RelatedSpotGuides.tsx`).** A Swiper carousel, **always one slide
per view**, each slide a full-bleed image (`h-[440px] md:h-[520px]`,
`rounded-2xl`) over a dark bottom gradient, with the Knewave title, a
line-clamped intro snippet, up to two translucent `spot_overview` badges
(wind conditions + best direction) and an "Explore →" affordance overlaid
bottom-left. The whole card is an Inertia `<Link>`; the prev/next arrows and
pagination dots sit outside the link so they never trigger navigation. Round
pagination dots use scoped styles in `app.css` (`#related-spot-guides
.related-bullet[-active]`), mirroring the gallery bullet pattern.

## Design decisions worth keeping

- **Card layout chosen by mockup.** Started as a responsive multi-peek "richer
  card"; the owner asked for one-slide-per-view using the full width, was shown
  two mocked options (split-hero vs full-bleed overlay in the real site
  palette), and picked the **full-bleed overlay** (Option B). The spec was
  updated to match; the plan's original Task 3 code block is therefore stale by
  design (kept as a record).
- **Controls hidden when `guides.length <= 1`.** With a single sibling there is
  nothing to page to — chevrons/dots would mislead. The Swiper `modules` array
  is *also* gated on `guides.length > 1`, which suppresses Swiper's
  "can't find navigation element" console warnings in that (common) case.
  Verified in-browser: `/destinations/vassiliki` → "More Spots in Greece",
  one Karpathos card, zero arrows/dots, clean console.
- **Per-card country label dropped.** The section heading already names the
  country/continent, so a per-card country tag was redundant; removed after the
  owner flagged it. The now-unused `country` field was then cleaned out of the
  payload and TS types (no test depended on it).

## Test plan

- **Unit** (`tests/Unit/SpotGuideTest.php`): `sortByGustiestThisMonth` orders by
  current gust desc, puts no-current-data last, breaks ties by title.
- **Feature** (`tests/Feature/SpotGuideControllerTest.php`): same-country
  inclusion; current-guide + draft exclusion; continent fallback; the fully
  empty (single-guide) case; featured-first-then-gustiest ordering; card
  payload carries snippet + overview.
- Suite: **133 passing, 853 assertions.** Frontend has no component-test harness
  (Vitest covers helpers/charts only), so the component was verified by
  inspection + in-browser DOM check.

## Follow-ups

- None blocking. The `country` field is gone from the related payload; the
  eager-load was trimmed to `['thumbnailMedia', 'weatherRecords']`.
- Separately raised by Ben during this work (own effort, not this PR): a
  `php artisan` command to pull the production Postgres DB down to local for
  dev/debug — captured in `docs/TODO.md` (needs a design pass: prod credential
  source, read-only-on-prod guarantee, confirm-before-overwrite).
