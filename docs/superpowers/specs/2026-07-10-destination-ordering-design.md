# Relevance-ordered destination listings — Design

**Date:** 2026-07-10
**Status:** Approved (folding into PR #25, before merge)

## Goal

Order the non-featured destination listings so the most relevant spots surface
first: continents in a fixed priority (Europe, then Africa, then the rest), and
guides within each continent ranked **windiest-first for the current month using
this year's reading**. A short site-voice note explains the ordering.

Rationale: more European visitors expected (continent priority); and
windsurfers care about where's firing *now*, so the order should track the
current month's conditions and re-rank automatically as months pass.

## Background (verified in code)

- `DestinationController@index` currently `orderBy('title')` and eager-loads
  `weatherRecords`. The frontend (`Destinations/Index.tsx`) groups by continent
  with lodash `groupBy` and renders `Object.entries(...)` in encounter order
  (effectively alphabetical-by-first-guide today — not deliberate).
- `weather_records` is unique per `(spot_guide_id, year, month)`, so there is at
  most **one** row per guide per year+month; `kts_wind` is `decimal:1`.
- `WeatherFetcher` pulls **3 years up to `now()`** and upserts monthly rows, so
  the **current year + current month is populated** once the weekly job (or a
  manual fetch) has run. Open-Meteo's archive lags ~5 days, so very early in a
  month the current-month row may be partial or briefly absent — handled by the
  no-data fallback below. No job change is needed; the ranking reads current
  data per request.

## Decisions

- **Continent priority (frontend):** fixed list
  `['europe', 'africa', 'asia', 'north-america', 'south-america', 'oceania']`.
  Grouped continent sections are sorted by this index; any continent not listed
  sorts after, alphabetically. Pure presentation ordering → lives beside
  `CONTINENT_LABELS`.
- **Within-continent ranking (backend):** rank guides by their `kts_wind` for
  **`now()->year` + `now()->month`**, descending (windiest first). No averaging
  — it's the single current-year/current-month record. Because the frontend
  groups the already-ranked array (grouping is order-stable), each continent's
  guides stay windiest-first.
- **No data → last:** a guide with no current-year+month record sorts after all
  guides that have one. Ties (equal wind, or both missing) break alphabetically
  by title, so the order is always deterministic.
- **Auto-refresh:** `now()->month`/`now()->year` are read per request, so the
  order re-ranks as the month/year turns and as the weekly fetch refreshes the
  data — no stored ordering, no extra job.
- **Explanatory note:** a short muted line near the editorial intro, in site
  voice, stating the order is by this year's wind for the current month.

Rejected: long-term average across all years (owner wants *this year's* reading);
a stored/precomputed order column (needless — per-request ranking is cheap and
always current).

## Scope & Components

### 1. `app/Http/Controllers/DestinationController.php`
Replace `->orderBy('title')` + the subsequent mapping with a wind ranking.
After loading `$spotGuides` (published, with `country`, `thumbnailMedia`,
`weatherRecords`):
```php
$year = now()->year;
$month = now()->month;

// Windiest-first for the current month, using THIS YEAR's reading. Guides with
// no current-year/current-month record sort last; ties break by title. Read
// per request so the order re-ranks as the month turns / data refreshes.
$windThisMonth = fn (SpotGuide $guide) =>
    optional($guide->weatherRecords->first(
        fn ($record) => $record->year === $year && $record->month === $month
    ))->kts_wind;

$spotGuides = $spotGuides->sort(function (SpotGuide $a, SpotGuide $b) use ($windThisMonth) {
    $windA = $windThisMonth($a);
    $windB = $windThisMonth($b);
    if ($windA === null && $windB === null) return strcmp($a->title, $b->title);
    if ($windA === null) return 1;   // no-data sorts last
    if ($windB === null) return -1;
    return ((float) $windB <=> (float) $windA) ?: strcmp($a->title, $b->title); // desc
})->values();
```
The `$spotGuidesData` / `$weatherData` mappings then run over this reordered
collection (unchanged otherwise). Featured hero query + all other props
unchanged.

### 2. `resources/js/Pages/Destinations/Index.tsx`
- Add `const CONTINENT_ORDER = ['europe','africa','asia','north-america','south-america','oceania']`.
- Sort the grouped entries by that index before rendering:
```tsx
const orderedContinents = Object.entries(groupedByContinent).sort(([a], [b]) => {
    const rank = (c: string) => { const i = CONTINENT_ORDER.indexOf(c); return i === -1 ? 999 : i }
    return rank(a) - rank(b) || a.localeCompare(b)
})
```
  Render `orderedContinents.map(...)` instead of `Object.entries(groupedByContinent).map(...)`. Within-continent order is already windiest-first from the backend.
- Add the note (muted, site voice) near the intro, e.g. beneath the intro
  description. Wording (final tone tuned in preview), naming this year + month:
  > *"Ordered by wind for {Month} {year} — we rank each region's spots on this year's readings, so wherever's firing now rises to the top."*
  The `{Month} {year}` computed client-side (`new Date()`).

## Error Handling / Edge Cases

- All guides missing the current-year+month row → every guide is "no data" → the
  order degrades gracefully to alphabetical (title tiebreak). No worse than
  before, no error.
- Continent absent from `CONTINENT_ORDER` → sorts last, alphabetically.
- Featured guide still appears in its continent grid (unchanged) — now in its
  wind-ranked position.

## Testing (TDD)

- **`DestinationControllerTest`:**
  - Two published guides; the one with a higher current-year/current-month
    `kts_wind` appears before the other in `spotGuides`.
  - A guide with a current-year/current-month record sorts ahead of a guide with
    none (no-data last).
  - (tie) equal wind → alphabetical by title.
  Use `now()->year` / `now()->month` when creating the `WeatherRecord`s.
- Continent ordering is trivial deterministic frontend logic — verified in the
  live preview (Europe section first, Africa second), not unit-tested.

## Out of Scope

- Featured hero, map, weather charts (unchanged).
- Sub-projects B (list-content blocks) and C (quick-nav).
- Configurable continent priority / per-user ordering.

## Delivery

Fold into PR #25 (before merge): implement inline with TDD for the controller
ranking; verify continent order + note in the live preview; extend the #25
history doc + SITREP to cover the ordering. No migration, no job change.
