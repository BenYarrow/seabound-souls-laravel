# Destinations map & visualisations — light theme + generated palette — Design

**Date:** 2026-07-10
**Status:** Approved (pending spec review)

## Goal

Soften the two "aggressive" dark sections on the `/destinations` page — the
Mapbox globe and the Wind & Weather charts — so they read as one calm, light
editorial surface consistent with the rest of the site. Fold in the chart
line-colour work: replace the fixed 16-colour palette with a procedurally
generated muted palette that never runs out and reads well on a light card.

Origin: user feedback ("the dark colours here seem a little aggressive") on the
map + visualisations, plus a pre-existing concern that the hardcoded chart
palette will run out as more spot guides are added.

## Background (verified in code)

- **Map** (`resources/js/Components/Map/DestinationsMap.tsx`) uses
  `mapbox://styles/mapbox/dark-v11` with a dark custom fog
  (`color: rgb(10,20,30)`, `space-color: #060c14`). Teal wind markers
  (`bg-primary`), a deep-teal popup, and a small dark "Reset view" chip.
- **Weather section** (`resources/js/Pages/Destinations/Index.tsx`) wraps the
  whole `Wind & Weather` block in `bg-secondary` (near-black,
  `hsl(0 1% 15%)`). Heading text is white.
- **Charts** (`AllDestinationsWindChart.tsx`, `AllDestinationsTempChart.tsx`)
  sit on `bg-secondary/60` cards with white/opacity text; axis ticks/lines use
  `rgba(255,255,255,…)`; gridlines `rgba(255,255,255,0.12)`; the wind/gust
  toggle + unit radios are styled white-on-dark; the custom tooltip is a
  `bg-secondary/95` dark card with white text.
- **Filter bar** (`resources/js/Components/Destinations/FilterDataset.tsx`)
  is styled for the dark section.
- **Palette** (`resources/js/Helpers/colours.ts`): a fixed 16-colour `PALETTE`
  array; `getSpotGuideColours(titles)` assigns `PALETTE[i % PALETTE.length]`
  — so a 17th destination silently reuses colour #1. The colours are vivid /
  high-saturation, which vibrates on black.
- **Popup CSS** (`resources/css/app.css` lines ~27–43): `.destinations-map
  .mapboxgl-popup-content` is a deep-teal card (`hsl(192 89% 18%)`) with white
  text. This is on-brand and reads fine on a light map — **keep as-is**.
- The site has **no dark-mode token layer yet** (a separate pending
  follow-up); it is light-only in practice.

## Decisions

- **Map goes light.** `light-v11`, with a soft light atmosphere replacing the
  dark fog. Teal markers and teal popup stay (they read well on light). The
  dark "Reset view" chip stays — it's a small control, not a slab.
- **Weather section goes light.** Section background `bg-secondary` → `bg-cream`
  (matches the rest of the page); chart cards → white so they lift gently off
  the cream. All inside-out theming that assumed black flips to dark-on-light.
- **Chart palette becomes procedural + muted.** Replace the fixed `PALETTE`
  with a golden-angle HSL generator tuned to the approved muted full-spectrum
  tones (soft saturation, mid lightness). Full hue spread keeps every line
  distinguishable; the shared saturation/lightness keeps them a calm family on
  cream. `getSpotGuideColours(titles)` keeps its exact signature, so no
  downstream change.
- **Scope: destinations page only.** The single-spot charts on individual
  spot-guide pages use a separate `chartColors` trio (`wind`/`gust`/`temp`) —
  left unchanged here, flagged as a possible matching follow-up. No dark-mode
  token work.

Rejected: keeping the dark treatment but softening it (deep-teal bg + gentler
dark map + desaturated palette) — the user chose the fully-light direction so
the whole page reads as one light theme.

## Scope & Components

### 1. `resources/js/Helpers/colours.ts` — generated muted palette

Replace the fixed `PALETTE` array and rewrite `getSpotGuideColours` to generate
a colour per index using the golden angle, at fixed muted saturation/lightness:

```ts
/**
 * Muted saturation / lightness tuned to read as one calm family on a light
 * (cream) chart card — vivid enough to tell lines apart, soft enough not to
 * vibrate. Shared across every generated line colour.
 */
const CHART_SATURATION = 48 // %
const CHART_LIGHTNESS = 48 // %

/** The golden angle (deg). Spacing hues by it maximises separation between
 *  consecutive indices, so each line is distinct for any number of guides. */
const GOLDEN_ANGLE = 137.508

/**
 * Deterministic, distinct chart colour for the Nth destination. No fixed
 * palette to exhaust — works for any count, and index N always yields the
 * same hue.
 * @param index - Zero-based position of the destination.
 * @returns An `hsl(...)` colour string.
 */
const colourForIndex = (index: number): string => {
    const hue = (index * GOLDEN_ANGLE) % 360
    return `hsl(${hue.toFixed(1)}, ${CHART_SATURATION}%, ${CHART_LIGHTNESS}%)`
}

/**
 * Map each destination title to a generated muted chart colour, assigned by
 * its position in the given (already-sorted) list.
 * @param titles - Destination titles, in display order.
 * @returns A title→colour lookup.
 */
export const getSpotGuideColours = (titles: string[]): Record<string, string> => {
    const colours: Record<string, string> = {}
    titles.forEach((title, index) => {
        colours[title] = colourForIndex(index)
    })
    return colours
}
```

`chartColors` (the single-spot `wind`/`gust`/`temp` trio) is **unchanged** —
it's out of scope (used by spot-guide pages, not the destinations charts).

### 2. `DestinationsMap.tsx` — light map

- `mapStyle="mapbox://styles/mapbox/light-v11"`.
- Replace the dark `setFog(...)` with a light atmosphere (pale
  `color`/`high-color`, light `space-color`, `star-intensity: 0`), so the
  globe reads pale. (Exact values chosen at implementation and eyeballed in
  the preview; the requirement is "soft/light, not black".)
- Markers, popup markup, and reset chip unchanged.

### 3. `Destinations/Index.tsx` — light weather section

- Section wrapper `bg-secondary` → `bg-cream`.
- Section heading + subtitle: white → dark (`text-secondary`, muted greys),
  matching the continent-heading treatment already used higher on the page.

### 4. `AllDestinationsWindChart.tsx` + `AllDestinationsTempChart.tsx` — light charts

Flip every dark-assuming style to dark-on-light:
- Card: `bg-secondary/60 border-white/20` → white card, light hairline border.
- `AXIS_TICK` / `AXIS_LINE` and the `CartesianGrid` stroke: `rgba(255,255,255,…)`
  → dark alpha (`rgba(0,0,0,…)` equivalents) so ticks/grid read on white.
- YAxis `label` fill and header/subtitle/disclaimer text: white-alpha → dark.
- Wind/Gust toggle + unit radios: white-on-dark utility classes → dark-on-light
  (track/border greys, active state stays `bg-primary` teal + white text).
- Custom tooltip: `bg-secondary/95` white-text card → white card, dark text,
  keeping the per-destination colour swatch on each row (now the generated
  muted colours).

### 5. `FilterDataset.tsx` — light filter bar

Re-theme the bar + its `react-select` controls to sit on the light section
(consistent surfaces, borders, and text with the charts).

## Error Handling / Edge Cases

- `getSpotGuideColours([])` → `{}` (empty loop); no crash.
- Any number of destinations → a distinct hue each; index N is stable.
- Mapbox style/fog swap is presentation-only; markers/popups already guard on
  missing coordinates.

## Testing (TDD)

The repo currently has **no JavaScript test runner** (the whole suite is
PHPUnit). This change stands up **Vitest** as the project's first JS test
runner — a `vitest` dev dependency, a `vitest.config.ts` (node environment; no
DOM needed for a pure helper), and a `"test:js"` npm script — then TDDs the
palette helper against it.

- **Unit — `colours.ts`** (`resources/js/Helpers/colours.test.ts`):
  `getSpotGuideColours`
  - returns one entry per title, keyed by title;
  - every colour is a valid `hsl(...)` string at the fixed S/L;
  - colours are **distinct** across a realistic count (e.g. 16 and 30 titles);
  - assignment is **deterministic** (same input → same output) and index N maps
    to the same hue regardless of list length beyond it (golden-angle property).
- **Visual verification** (not automated): load `/destinations` in the preview,
  confirm the map is light, the weather section + charts are light with legible
  dark text and muted lines, at mobile / tablet / desktop. Screenshot before PR.

## Out of Scope

- The single-spot `chartColors` trio + spot-guide-page charts (possible
  follow-up to match).
- Dark-mode token layer (separate pending follow-up).
- Any data / controller / schema change — this is presentation + one pure
  helper only.

## Delivery

Branch `feat/destinations-light-theme`; stand up Vitest + TDD the palette
helper; folded reconcile before merge; PR. No env / infra changes (Vitest is a
dev-only tooling addition).
