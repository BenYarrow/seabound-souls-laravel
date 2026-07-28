# Sailable-days ranking — destinations page redesign

**Date:** 2026-07-28
**Status:** Design approved, pending spec review
**Area:** `/destinations` page, weather data pipeline

---

## Problem

The destinations page shows historical weather as **monthly averages** (average wind, average temperature). Feedback: a monthly average tells a windsurfer very little. What actually helps trip planning is **"how many sailable days does this spot get?"** — and "sailable" is personal: a foiler is happy at 15 kts, a speed sailor wants 35 kts.

So we want a **custom ranking**: the user sets a minimum wind speed, and the page ranks spots by how many sailable days they typically get — reordering the actual destination cards, not just drawing a chart. The existing monthly-average charts stay (the data is still worth showing), repurposed as a guide for *which month* to consider.

## What a "sailable day" means

A day counts as sailable, for a chosen minimum `X`, if **at least 2 hours within the 9am–7pm sailing window blew at or above `X`** (sustained wind, not gusts).

- **Why 2 hours:** two solid hours guarantees a proper session; it's a fixed rule, not a user knob.
- **Why sustained wind, not gusts:** gust-based counting would reward squally days that aren't actually sailable. Sustained wind captures "consistently blowing."
- **Key insight:** "≥2 hours ≥ X" is exactly equivalent to **"the 2nd-windiest hour of the day ≥ X."** So a single stored number per day — the 2nd-highest sailing-window hourly wind — answers the sailable test for *any* minimum the user picks, with no recomputation.

## Ranking model (approved: month-based, coverage-normalised rate)

- The rank axis is **month**, not year — "how many sailable days in August" is a real planning question; "across the whole year" is not.
- We hold ~3 years of data on a **rolling** window, so a month's boundary years are only partially covered (e.g. fetched 2026-07-28, we hold ~4 days of July 2023, full Julys for 2024–2025, and ~28 days of July 2026 — only ~2.1 full-month-equivalents). Dividing a pooled qualifying-day count by the number of distinct years would therefore undercount by nearly half — and the **current month (the default selection) is always a partial boundary**. Instead we compute a **coverage-normalised rate**: the share of *held* days in that month that qualify, scaled up to the month's calendar length →
  `typicalDays = (qualifyingCount ÷ heldDayCount) × daysInMonth`
  where `qualifyingCount` = pooled daily values ≥ the minimum, `heldDayCount` = the number of pooled daily values, and `daysInMonth` = `[31,28,31,30,31,30,31,31,30,31,30,31][month-1]` (February fixed at 28 — the sub-day error from leap years is immaterial to a climatological estimate). This yields a typical/climatological figure (e.g. "Vassiliki — ≈19 days ≥ 20 kts in August") that is robust to partial boundary months.
- **Rank key:** the coverage-normalised typical sailable days in the selected month, descending.
- **Tie-break:** higher sailable-day count in the spot's *peak* month, then alphabetical by title — deterministic, so shared links reproduce an identical order.
- **Zero-day spots stay visible** at the bottom, labelled "0 days ≥ X kts", so cranking the minimum to 50 kts shows an honest "nowhere qualifies" rather than an empty page.
- The **Spots multiselect** narrows the field (empty = all spots); ranking and charts operate on the selected set.

---

## Architecture (approved: Approach 1 — client-side compute)

All threshold-dependent counting and ranking happen **in the browser**, so dragging the minimum reorders the page instantly with no round-trips. The server ships pre-shaped data once; filter state lives in the URL for shareable links.

### 1. Data layer

**New table `spot_sailable_days`** — one row per spot per day (days inside the fetch window only):

| Column | Type | Notes |
|---|---|---|
| `id` | bigint auto-increment | |
| `spot_guide_id` | foreignId → `spot_guides`, `cascadeOnDelete` | |
| `date` | date | the day |
| `year` | unsignedSmallInteger | denormalised for grouping |
| `month` | unsignedTinyInteger | denormalised for grouping |
| `qualifying_wind_kts` | decimal(5,1) | the 2nd-windiest 9am–7pm hour that day (sustained wind, kts) |
| `created_at` / `updated_at` | timestamps | |
| unique | `(spot_guide_id, date)` | |

Model: `App\Models\SailableDay` (belongsTo `SpotGuide`), header-commented per project convention. `SpotGuide` gains `hasMany(SailableDay::class)`.

The monthly `weather_records` table and model are **unchanged** — this is a new parallel daily layer, not a migration of the existing one.

### 2. Population — extend `WeatherFetcher`

`app/Services/WeatherFetcher.php` already fetches **hourly** Open-Meteo archive data and buckets it into per-day, 9am–7pm sailing-window groups (it currently averages those away into monthly figures). We extend the same pass:

- For each day's sailing-window hours, take sustained wind (`wind_speed_10m`), sort descending, take the 2nd value → `qualifying_wind_kts`.
- Upsert one `spot_sailable_days` row per day, keyed on `(spot_guide_id, date)`.
- The existing monthly `weather_records` computation is untouched and runs in the same pass.

**Backfill:** no new operational path. The daily rows populate the next time weather is fetched — via the existing `weather:fetch` command, the auto-fetch-on-create job (`FetchSpotWeatherJob`), or the admin "Fetch all" widget, all of which flow through `WeatherFetcher`. After deploy, run "Fetch all" once to fill history for existing spots.

### 3. Controller & payload — `DestinationController@index`

Ships three props (replacing today's per-year `weatherData` prop):

1. **`spots[]`** — existing card data (id, title, slug, continent, country, thumbnail). Unchanged.

2. **`sailableDays[title]`** — ranking fuel. Per spot, per month (1–12):
   ```
   { values: number[] }  // daily qualifying_wind_kts, pooled across all held years
   ```
   The browser counts `values ≥ X_kts`, divides by `values.length` (the held-day count), and scales by the month's calendar length → the coverage-normalised typical sailable days that month (see the Ranking model above). Rate-then-scale is robust both to spots with fewer years of history and to partial boundary months. Keyed by title to match chart series labels (consistent with existing convention).

3. **`climate[title]`** — the kept wind/temp charts, averaged across years server-side into one typical-year 12-month array: `{ month, avgTemp, ktsWind, ktsGust, mphWind, mphGust, kphWind, kphGust }`, built from `weather_records` with a simple `AVG` grouped by month.

Server does: the cross-year `AVG` for `climate`, and the by-month pooling of daily values for `sailableDays` (no per-year division — the browser does the rate normalisation). Browser does: all threshold counting and ranking. No new API endpoint; no per-keystroke round-trips.

### 4. Frontend — `resources/js/Pages/Destinations/Index.tsx`

**Filter bar** (new component, top of the page, above the guides), left-to-right:

| Control | Type | Default |
|---|---|---|
| **Month** | single select, Jan–Dec | current month |
| **Group by** | Continent / Country / Global | Continent |
| **Spots** | multiselect (empty = all) | all — **label reads "All destinations"** when empty, never blank |
| **Unit** | kts / mph / kph | kts |
| **Minimum** | dropdown, steps of 5, range tracks unit (kts 5–50, mph 5–55, kph 10–95) | 20 kts |

**Switching units:** when the user changes the unit, the current minimum is converted into the new unit and snapped to the nearest 5-step option (e.g. 20 kts → ~23 mph → snaps to 25 mph), so the intended wind strength is roughly preserved rather than reset.

**URL sync:** every change is instant (client-side) and mirrored to the query string via `window.history.replaceState` — **no server request**, since all the ranking data is already in the browser — e.g. `/destinations?month=8&min=20&unit=kts&group=continent&spots=vassiliki,tarifa`. **Spots are serialised as slugs** (not titles, which contain spaces/commas that would break the comma-join); the page resolves slugs → titles for ranking and silently drops any unknown/stale slug in a shared link. Initial state is read from the URL on load (falling back to defaults), so a shared link reproduces the exact view. The minimum is stored/serialised in the user's chosen unit and converted to kts for comparison.

**`useSailableRanking` hook** — pure function `(sailableDays, spots, month, minKts) → { spot, avgDaysThisMonth, daysPerMonth[12] }[]`, sorted by `avgDaysThisMonth` desc with peak-month → alphabetical tie-break. Trivially unit-testable.

**Ranking universe:** the ranking (and therefore every card layout) is built from **all published spots**, not only those that have fetched weather data. A spot with no `spot_sailable_days` rows simply ranks 0 and sinks to the bottom — it never silently disappears from the grid (this is the "zero-day spots stay visible" rule applied to dataless spots too). The `climate` map (which only has entries for spots with weather records) drives the chart series/colours, but must **not** seed the card ranking.

**Three layouts**, all consuming the ranked array:
- **Global** — one flat grid in rank order; each card shows its "≈19 days ≥ 20 kts in August" figure and a continent tag.
- **Continent / Country** — grouped headings; spots rank-sorted within each; headings ordered by their best (top-ranked) spot.

Each card gains a small **"≈N sailable days"** stat for the selected month + minimum, so the ranking is legible on the card itself.

### 5. Visualisations (bottom of page)

- **New "Sailable days per month" chart** (hero): 12-month line chart, one series per selected spot, y = typical (coverage-normalised) sailable days, reacting live to minimum/unit/spots, with the **selected month marked** (emphasis dot / reference line). Line (not bars) for consistency with the existing comparison charts and readability with many spots.
- **Kept wind & temperature charts:** the existing line charts, now drawn as the **typical-year** 12-month curve (averaged across years), selected month marked. The **unit** selector moves up to the shared filter bar (it now governs the whole page); the wind chart keeps its own **avg-wind / gust** toggle (chart-specific). These curves guide the "which month?" choice.

---

## Testing (TDD throughout)

**PHP (PHPUnit, SQLite):**
- `WeatherFetcher`: given mocked Open-Meteo hourly data, a day's `qualifying_wind_kts` equals the 2nd-highest 9am–7pm sustained-wind hour; monthly `weather_records` still produced unchanged in the same pass.
- `DestinationController@index`: `sailableDays` pooling by month (values only, no per-year division server-side); `climate` cross-year averaging; both keyed by title.
- All external HTTP (Open-Meteo) mocked.

**JS (Vitest, Node 22):**
- `sailableDaysInMonth` / `rankSpots`: threshold counting, the coverage-normalised rate formula (`qualifyingCount ÷ heldDayCount × daysInMonth`), rank order, peak-month → alphabetical tie-break, zero-day and dataless spots sink to the bottom but stay in the list.
- Minimum unit→kts conversion and unit-switch snapping (kts/mph/kph, including kph 10–95 in steps of 5).
- URL serialise/deserialise round-trip restores an identical view, with **spots carried as slugs**.

**Standard project checks before done:** `php artisan test` green; `npm run test:js` green; both light and dark themes and mobile/tablet/desktop breakpoints verified on the redesigned page; no banned raw colour utilities.

---

## Out of scope (YAGNI)

- Configurable "N hours" (fixed at 2).
- Gust-based sailable counting.
- Per-specific-year ranking (dropped the year axis deliberately).
- Threshold-bucket precomputation (Approach 3 — defeats the custom-minimum requirement).
- A dedicated ranking API endpoint (client-side compute makes it unnecessary).

## Operational notes

- Requires one "Fetch all" run post-deploy to backfill `spot_sailable_days` for existing spots.
- Payload for `sailableDays` is ~120 KB across all spots — acceptable, shipped once per page load.
- Tests run on SQLite but dev/prod are Postgres; the `AVG`-grouped-by-month and pooling queries should be smoke-tested on real Postgres (JSON/aggregate divergence risk per project notes).
