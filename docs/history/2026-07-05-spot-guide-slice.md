---
title: SpotGuide test + comment slice
tags: [testing, spot-guide, models]
status: stable
completed: 2026-07-05
commits: [8c6a62e]
pr: 3
---

# SpotGuide test + comment slice

Second slice of the up-to-speed sweep, following the blog template (PR #1). Full test coverage + comment pass for the spot-guide (destination) surface — the richest public page.

## What shipped

### Tests (13 new; suite 21 passed, 122 assertions)
- **`SpotGuideControllerTest`** (6) — published render with country, 404 on draft, 404 on unknown slug, stay/eat recommendation split, windsurfing locations, weather records grouped by year + sorted by month with month names.
- **`SpotGuideTest`** (4) — `country_name` denormalisation hook, published scope, recommendation + location `sort_order` ordering.
- **`WeatherRecordTest`** (3) — `month_name` accessor (in/out of range), `forYear`/`forMonth` scopes.
- **`RecommendationTest`** (1) — stay/eat type scopes.

### Factories
Country, SpotGuide (+ `unpublished()`), Recommendation (+ `eat()`), WindsurfingLocation, WeatherRecord; the five models given `HasFactory`.

### Comments pass
Module headers + PHPDoc + why-comments on `SpotGuideController` and all five models, per the working standard.

## Findings worth keeping
- **`country_name` is denormalised on the spot_guides row** via a `saving` hook (re-resolved only when `country_id` is dirty) so Scout can match on country without a join. Covered by `SpotGuideTest::test_saving_denormalises_country_name_from_the_related_country`.
- **Weather records serialise as a year-keyed map**, each year sorted by month, with a `month_name` accessor supplying the label. The Inertia prop path is `spotGuide.weather_records.<year>.<index>.month`.
- **Scout disabled in tests** (`SCOUT_DRIVER=null`, from PR #1) means the `Searchable` models save without hitting an index — factories work cleanly.

## Test plan
`php artisan test` → 21 passed, 122 assertions. `/destinations/karpathos` renders (200).

## Follow-ups
See `docs/TODO.md`. Next slice: Destinations, then Search, Contact, Pages, Homepage.
