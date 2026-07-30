---
title: Temperature note on cards + opt-in min-temp filter (destinations)
tags: [destinations, weather, frontend, ux]
status: stable
completed: 2026-07-30
commits: [dda7ca6, ce34b33]
pr: 42
---

# Destinations temperature note + opt-in min-temp filter

## Problem

The sailable-days ranking is wind-only, so a cold-but-windy spot can top a month
you'd never travel to — e.g. Karpathos ranked #2 in January (genuine winter
frontal wind, but ~11–16 °C). The obvious fix — a temperature gate — is *wrong*:
it would bury legitimate **cold-water spots whose season is winter** (Brouwersdam
/ North Sea / Baltic, sailed hard in rubber). **Temperature is a personal
preference, not a wind-quality signal**, so it must not be baked into the ranking.

## What shipped

Surface temperature as information + an opt-in filter, imposing no warmth
judgement by default:

- **Temp note on every card** — `≈ 18 windy days · 11°C`, the typical air temp
  for the selected month. Now Karpathos visibly reads 16 °C in a January where
  Dakhla is 20 °C, so the user sees *why* they might skip it.
- **Opt-in Min. temp filter** — `TEMP_OPTIONS = [0, 10, 15, 20, 25]` °C, default
  **0 = Any (off)**. When set, spots whose selected-month typical temp is below it
  (or unknown) are excluded from cards **and** charts. Default-off means
  cold-water spots are never penalised.
- URL-synced (`temp`, omitted when 0). Temp stays °C regardless of the wind unit.
- No new data/fetch — uses `avgTemp` already in the `climate` payload via a new
  `climateTempForMonth()` helper.

## Key files
- `resources/js/Helpers/climate.ts` — `climateTempForMonth(dataset, title, monthName)` → `avgTemp | null`.
- `resources/js/Helpers/destinationFilters.ts` — `minTemp` + `TEMP_OPTIONS`; URL parse/serialise (rejects off-grid values; omits `temp` when 0).
- `resources/js/Components/Destinations/DestinationFilterBar.tsx` — "Min. temp" Select inside the collapsible controls; appended to the mobile summary when active.
- `resources/js/Pages/Destinations/Index.tsx` — `visibleRanked` (temp-filtered view feeding cards + both chart sets), temp appended in `statFor`, empty-state message + charts-section gate.

## Verification
- JS **34** pass; `npm run build` clean.
- In-browser (localhost, January): cards show `≈ N windy days · N°C`; `temp=20` →
  4 cards all ≥20 °C, **Karpathos (16 °C) + Langebaan (18 °C) correctly dropped**;
  `temp=25` → only Le Morne (~27 °C) remains.
- Reviewed (spec + quality). One **Important** gap fixed (`ce34b33`): when the
  filter emptied the set, the cards showed the empty-state message but the
  "Wind & Weather Data" section still rendered a blank `SailableDaysChart` (it
  lacks the self-hide guard its sibling charts have) — now the whole section is
  gated on `!isTemperatureFilterEmpty`.

## Design note (why not a gate)
Recorded because the reasoning matters for future weather/ranking work:
temperature is surfaced and optionally filtered, **never gated**, so the ranking
stays objective (wind) and both audiences are served — the fly-away tourist
(sets a min temp) and the cold-water local (leaves it on Any). A **water-temp**
signal and a possible sustained/gust UI toggle remain open follow-ups
(`docs/TODO.md`).
