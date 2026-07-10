---
title: Destinations map & visualisations — light theme + generated chart palette
tags: [frontend, destinations, charts, mapbox, recharts, palette, vitest, design]
status: stable
completed: 2026-07-10
commits: [8106ca3, a096d50, 0cd03f2, 97f93fa, 1b59d05, 629a06f, 5284702]
pr: 24
---

# Destinations light theme + generated chart palette

## Why

User feedback (Ben's wife) on the `/destinations` page: the dark Mapbox globe
and the near-black Wind & Weather charts felt "a little… aggressive" against the
otherwise light, cream, editorial site. Separately, the chart line colours were
a **fixed 16-colour palette** ported from the original React build — every 17th
destination silently reused colour #1, so it would "run out" as guides are added.

Both were addressed together: soften the two dark sections to a calm light
treatment, and replace the finite palette with a generated one.

## What shipped

**1. Generated muted chart palette + first JS test runner.**
`resources/js/Helpers/colours.ts` — the fixed `PALETTE` array is gone.
`getSpotGuideColours(titles)` now generates a colour per destination via the
**golden angle** (137.508°) at a fixed muted saturation/lightness (48% / 48%):
`hsl((index * 137.508) % 360, 48%, 48%)`. Distinct per line (hues maximally
separated), calm as a family (shared S/L), and it never runs out — index N maps
to the same hue regardless of total count. Signature unchanged, so the two chart
components and `Destinations/Index.tsx` needed no change. The single-spot
`chartColors` trio (wind/gust/temp) is untouched (out of scope).

This is the project's **first JavaScript test runner**: Vitest was stood up
(`vitest` dev dep, a **standalone** `vitest.config.ts` that deliberately does
not merge `vite.config.js` so the Laravel Vite plugin stays out of unit tests,
and a `test:js` npm script). The helper was TDD'd — `colours.test.ts` (7 tests:
keyed-by-title, empty input, muted-hsl format, distinct at 16 **and** 30 to prove
the old ceiling is gone, determinism, index-stability independent of list length).

**2. Light map.** `DestinationsMap.tsx` — `dark-v11` → `light-v11`, and the dark
space fog replaced with a pale atmosphere (`star-intensity: 0`). Teal wind
markers, the deep-teal popup, and the dark "Reset view" chip were kept — all
read well on light.

**3. Light weather section + both charts.** The Wind & Weather section and the
two Recharts comparison charts flipped from `bg-secondary` (near-black) /
white-on-dark to dark-on-light: axis ticks/lines/gridlines to dark alpha, YAxis
label fill, card backgrounds to white, headers/subtitles/disclaimer, the
wind/gust toggle, the kts/mph/kph radios, and the custom tooltip (white card,
dark text) — keeping each tooltip row's per-destination colour swatch.

**4. Light filter bar.** `FilterDataset.tsx` — the `react-select` `selectStyles`
and the bar/label/divider/reset classes re-themed for a light surface.

**5. Section separation (post-verification refinement).** During the live
visual pass the weather section (then `bg-cream`) blended into the cream
continent-listing directly above it. Fixed by giving the weather zone a distinct
pale-teal tone (`bg-primary-lightest`) and flipping the filter bar to `bg-white`
so it reads as a control strip on that tone.

## Verification

- `npm run test:js` — 7/7 (TDD RED confirmed first).
- `php artisan test` — 108/108, 634 assertions (unaffected).
- `npm run build` — green.
- Live preview (localhost:8000 via `php artisan serve`), desktop + mobile:
  pale light globe with visible teal markers; separated pale-teal weather zone;
  white filter strip + white chart cards; legible dark-on-light axes; muted,
  distinct lines; readable tooltips.

## Findings worth keeping

- **A "sea-toned only" palette is a trap for comparison charts** — narrow-hue
  colours look cohesive but become indistinguishable across many lines. The
  answer is full hue spread (distinguishable) at a shared muted S/L (calm).
- **Golden-angle format edge:** `Number(hue.toFixed(1))` yields `hsl(0, 48%, 48%)`
  for index 0 (no trailing `.0`); the test regex accepts integer or one-decimal
  hues.
- **Vitest standalone config** is intentional — merging `vite.config.js` would
  pull in `laravel-vite-plugin` during unit tests.
- Node 22 is required for `test:js` / `build` (shell default v14 fails).

## Follow-ups

- Match the single-spot `chartColors` trio (wind/gust/temp on spot-guide pages)
  to the new muted family, for one consistent chart look (deferred; separate).
- Optional: give the filter-bar select controls a hint of fill so they lift off
  the now-white bar (purely aesthetic; verified legible as-is).
- Standing: this is the project's first JS test — future frontend logic should
  be TDD'd against Vitest too. A dark-mode token layer (separate track) would
  eventually subsume these hardcoded light utilities.
