---
title: Spot-guide weather section — light theme (mirror destinations)
tags: [spot-guide, weather, charts, light-theme, recharts, frontend]
status: stable
completed: 2026-07-10
commits: [3d3a079]
pr: 28
---

# Spot-guide weather section light theme

## Why

The destinations weather section was switched to a calm light treatment in #24
(+ pale-teal refinement in #25), but the **spot-guide** page's weather section
(`SpotGuideStatistics`) was never migrated — it was still the pre-#24 dark clone
(`bg-secondary`, white-on-dark charts). The owner asked to mirror the destinations
change here so the two match.

## What shipped

`resources/js/Components/SpotGuide/SpotGuideStatistics.tsx` — mirrored the
destinations weather light theme (frontend-only, single file):

- Section `bg-secondary` → `bg-primary-lightest` (pale teal, matching destinations).
- Filter bar `bg-primary` → `bg-white` (`border-y border-secondary/10`); label/icon
  → `text-primary`; reset button + divider → dark-on-light; `react-select` styles
  → the light variant (white control, dark text, teal focus/selected).
- Chart cards `bg-secondary/60 border-white/[0.07]` → `bg-white border-black/10`;
  headings/subtitles white → `text-secondary` / `text-secondary/50`.
- Chart chrome flipped dark-on-light: `AXIS_TICK`/`AXIS_LINE` → `rgba(0,0,0,…)`,
  `CartesianGrid` stroke `rgba(0,0,0,0.08)`, `YAxis` label fill `rgba(0,0,0,0.5)`,
  tooltip cursor `rgba(0,0,0,0.04)`.
- Tooltips → white cards, dark header, per-series coloured rows (extracted the bar
  colours to `GUST_COLOUR`/`WIND_COLOUR`/`TEMP_COLOUR` consts so the tooltip
  figures match their bars). Disclaimer note → dark-on-light.
- Bar fills unchanged (dark-teal gust / teal wind / orange temp — all read well on
  white).

## Verification

- `npm run build` green.
- Live preview (`/destinations/le-morne`, computed styles + screenshot): section
  `rgb(219,235,232)` (= `bg-primary-lightest`), heading `rgb(39,38,38)`
  (`text-secondary`), filter bar + both chart cards white; bars/axes read cleanly
  on white. Matches the destinations weather section.
- Frontend-only; PHP suite unaffected.

## Notes

- Screenshots render fine on this section (no Mapbox canvas here), unlike the map
  pages — see [[project-preview-verification-limits]].
