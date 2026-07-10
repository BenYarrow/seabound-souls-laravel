# Destinations Light Theme + Generated Palette — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the `/destinations` map and Wind & Weather charts read as one calm, light editorial surface, and replace the finite hardcoded chart palette with a procedurally generated muted one.

**Architecture:** Four independent slices — (1) stand up Vitest and replace the palette helper with a golden-angle HSL generator (the only logic change, TDD'd); (2) switch the Mapbox globe to a light style; (3) flip the weather section + both comparison charts to dark-on-light; (4) re-theme the filter bar to light. Slices 2–4 are presentation-only and verified visually in the live preview.

**Tech Stack:** React 19 + TypeScript, Inertia, Recharts, react-select, `react-map-gl/mapbox` (Mapbox GL), Tailwind v3, Vite 7, Vitest (new), Node 22.

## Global Constraints

- **Node 22 for all npm/vite/vitest commands.** The shell default is Node v14 which cannot run Vite/Vitest. Prefix every node command with: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null &&`. The system `node` is NOT the project version.
- **Scope: destinations page only.** Do NOT touch the single-spot `chartColors` trio in `colours.ts` or any spot-guide-page chart. No dark-mode token layer work.
- **No raw palette-specific utilities that won't theme later** is the standing project rule, but this project has no dark-mode token layer yet and is light-only in practice — match the existing Tailwind approach used elsewhere on the page (semantic brand classes: `bg-cream`, `text-secondary`, `text-primary`, `bg-primary`, `primary-lighter`, etc.).
- **`getSpotGuideColours(titles: string[]): Record<string, string>` keeps its exact signature** — downstream (`Destinations/Index.tsx`, both charts) must not need changes for the palette swap.
- **Muted palette values are fixed:** golden angle `137.508°`, saturation `48%`, lightness `48%`, format `hsl(H, 48%, 48%)` with H to one decimal.
- **PHP suite must still pass** (`php artisan test`) — unaffected, but confirm before final.

---

### Task 1: Stand up Vitest + generated muted palette

**Files:**
- Create: `vitest.config.ts`
- Modify: `package.json` (add `vitest` devDependency + `test:js` script)
- Modify: `resources/js/Helpers/colours.ts`
- Test: `resources/js/Helpers/colours.test.ts`

**Interfaces:**
- Consumes: nothing.
- Produces: `getSpotGuideColours(titles: string[]): Record<string, string>` — unchanged signature; now returns `hsl(...)` strings. `chartColors` export stays exactly as-is.

- [ ] **Step 1: Install Vitest**

Run:
```bash
source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm install -D vitest
```
Expected: `vitest` added under `devDependencies` in `package.json`; no build errors.

- [ ] **Step 2: Add the standalone Vitest config**

Create `vitest.config.ts` (standalone — deliberately does NOT extend `vite.config.js`, so the Laravel Vite plugin isn't loaded during tests):
```ts
/**
 * Vitest config — the project's first JS test runner. Standalone (does not
 * merge vite.config.js) so the Laravel Vite plugin stays out of unit tests.
 * Node environment: current tests are pure functions with no DOM.
 */
import { defineConfig } from 'vitest/config'

export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
})
```

- [ ] **Step 3: Add the `test:js` script**

In `package.json`, add to `"scripts"` (alongside the existing `build` and `dev`):
```json
"test:js": "vitest run"
```

- [ ] **Step 4: Write the failing test**

Create `resources/js/Helpers/colours.test.ts`:
```ts
/**
 * Unit tests for the generated destination chart palette.
 * Uses a relative import (no @-alias) so the standalone Vitest config needs
 * no path resolver.
 */
import { describe, it, expect } from 'vitest'
import { getSpotGuideColours } from './colours'

/** Match `hsl(<number>, 48%, 48%)` — the fixed muted saturation/lightness. */
const HSL_MUTED = /^hsl\(\d+(\.\d+)?, 48%, 48%\)$/

describe('getSpotGuideColours', () => {
    it('returns one entry per title, keyed by title', () => {
        const titles = ['Tarifa', 'Karpathos', 'Dahab']
        const result = getSpotGuideColours(titles)
        expect(Object.keys(result)).toEqual(titles)
    })

    it('returns an empty object for no titles', () => {
        expect(getSpotGuideColours([])).toEqual({})
    })

    it('emits muted hsl(H, 48%, 48%) strings', () => {
        const result = getSpotGuideColours(['A', 'B', 'C'])
        Object.values(result).forEach((colour) => {
            expect(colour).toMatch(HSL_MUTED)
        })
    })

    it('assigns a distinct colour to each of 16 titles', () => {
        const titles = Array.from({ length: 16 }, (_, i) => `Spot ${i}`)
        const colours = Object.values(getSpotGuideColours(titles))
        expect(new Set(colours).size).toBe(16)
    })

    it('keeps colours distinct well beyond the old 16-colour ceiling (30 titles)', () => {
        const titles = Array.from({ length: 30 }, (_, i) => `Spot ${i}`)
        const colours = Object.values(getSpotGuideColours(titles))
        expect(new Set(colours).size).toBe(30)
    })

    it('is deterministic — same input yields identical output', () => {
        const titles = ['Tarifa', 'Karpathos', 'Dahab']
        expect(getSpotGuideColours(titles)).toEqual(getSpotGuideColours(titles))
    })

    it('maps a given index to the same hue regardless of list length', () => {
        // Golden-angle generation depends only on index, not total count.
        const short = getSpotGuideColours(['A', 'B'])
        const long = getSpotGuideColours(['A', 'B', 'C', 'D', 'E'])
        expect(short['A']).toBe(long['A'])
        expect(short['B']).toBe(long['B'])
    })
})
```

- [ ] **Step 5: Run the test to verify it fails**

Run:
```bash
source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run test:js
```
Expected: FAIL — the current `getSpotGuideColours` returns hex strings from `PALETTE` (e.g. `#8884d8`), so `HSL_MUTED` assertions fail; the 30-distinct test also fails (old impl wraps at 16).

- [ ] **Step 6: Replace the palette helper with the generator**

Replace the entire contents of `resources/js/Helpers/colours.ts` with:
```ts
/**
 * Chart colour helpers for the destinations weather visualisations.
 *
 * Single-spot charts use the fixed `chartColors` trio. Multi-destination
 * comparison charts use `getSpotGuideColours`, which GENERATES a colour per
 * destination via the golden angle — so the palette never runs out as new
 * spot guides are added, and every line stays visually distinct.
 */

/** Fixed colours for single-spot charts (wind / gust / temp). Out of scope
 *  for the destinations light-theme work — left unchanged. */
export const chartColors = {
    wind: '#8884d8',
    gust: '#82ca9d',
    temp: '#ffc658',
}

/**
 * Muted saturation / lightness, tuned so generated lines read as one calm
 * family on a light (cream) chart card — vivid enough to tell apart, soft
 * enough not to vibrate. Shared by every generated colour.
 */
const CHART_SATURATION = 48 // %
const CHART_LIGHTNESS = 48 // %

/**
 * The golden angle, in degrees. Spacing hues by it maximises separation
 * between consecutive indices, so each destination line is distinct for any
 * number of guides.
 */
const GOLDEN_ANGLE = 137.508

/**
 * Deterministic, distinct chart colour for the Nth destination. No fixed
 * palette to exhaust — index N always yields the same hue, independent of how
 * many destinations there are in total.
 * @param index - Zero-based position of the destination in the list.
 * @returns An `hsl(...)` colour string at the fixed muted saturation/lightness.
 */
const colourForIndex = (index: number): string => {
    const hue = (index * GOLDEN_ANGLE) % 360
    return `hsl(${Number(hue.toFixed(1))}, ${CHART_SATURATION}%, ${CHART_LIGHTNESS}%)`
}

/**
 * Map each destination title to a generated muted chart colour, assigned by
 * its position in the given (already display-ordered) list.
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

Note on the format: `Number(hue.toFixed(1))` drops a trailing `.0` (so `hsl(0, 48%, 48%)` for index 0), and the `HSL_MUTED` regex allows both integer and one-decimal hues.

- [ ] **Step 7: Run the test to verify it passes**

Run:
```bash
source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run test:js
```
Expected: PASS — all 7 tests green.

- [ ] **Step 8: Commit**

```bash
git add vitest.config.ts package.json package-lock.json resources/js/Helpers/colours.ts resources/js/Helpers/colours.test.ts
git commit -m "feat: generated muted chart palette + Vitest harness"
```

---

### Task 2: Light map style

**Files:**
- Modify: `resources/js/Components/Map/DestinationsMap.tsx:78-89`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Switch the map style and atmosphere to light**

In `resources/js/Components/Map/DestinationsMap.tsx`, change the `mapStyle` prop and the `onLoad` fog from dark to light. Replace:
```tsx
                mapStyle="mapbox://styles/mapbox/dark-v11"
                logoPosition="bottom-right"
                attributionControl={false}
                onLoad={(e) => {
                    e.target.setFog({
                        color: 'rgb(10, 20, 30)',
                        'high-color': 'rgb(10, 20, 30)',
                        'space-color': '#060c14',
                        'horizon-blend': 0.02,
                        'star-intensity': 0.15,
                    })
                }}
```
with:
```tsx
                mapStyle="mapbox://styles/mapbox/light-v11"
                logoPosition="bottom-right"
                attributionControl={false}
                onLoad={(e) => {
                    // Pale atmosphere so the globe reads light, matching the
                    // page — not the dark space the dark-v11 style implied.
                    e.target.setFog({
                        color: 'rgb(224, 236, 242)',
                        'high-color': 'rgb(205, 224, 236)',
                        'space-color': 'rgb(235, 241, 246)',
                        'horizon-blend': 0.06,
                        'star-intensity': 0,
                    })
                }}
```

Leave the teal markers, the deep-teal popup, and the dark "Reset view" chip unchanged — all read well on a light map.

- [ ] **Step 2: Verify in the live preview**

Ensure the dev server is running (`composer dev` with the Node 22 PATH, or `npm run dev`), then load `/destinations`. Confirm: the globe is pale/light, teal markers are clearly visible, clicking a marker still shows the teal popup with readable white text, and "Reset view" re-centres.

Verify via the preview tools (reload, screenshot). If markers are hard to see on the lighter land, note it — but `bg-primary` teal on `light-v11` has strong contrast and should be fine.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Map/DestinationsMap.tsx
git commit -m "feat: light Mapbox style for destinations map"
```

---

### Task 3: Light weather section + both comparison charts

**Files:**
- Modify: `resources/js/Pages/Destinations/Index.tsx:198-215` (section wrapper + header)
- Modify: `resources/js/Components/Destinations/AllDestinationsWindChart.tsx` (axis consts, card, header, toggle, radios, tooltip, disclaimer)
- Modify: `resources/js/Components/Destinations/AllDestinationsTempChart.tsx` (axis consts, card, header, tooltip)

**Interfaces:**
- Consumes: `getSpotGuideColours` output (unchanged shape) from Task 1.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Flip the section wrapper + header to light**

In `resources/js/Pages/Destinations/Index.tsx`, in the Weather data `<section>` (currently `className="bg-secondary"`), change the background and the header text. Replace:
```tsx
                <section className="bg-secondary">
                    {/* Section header */}
                    <div className="container mx-auto pt-16 lg:pt-20 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-12 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2
                                    className="font-display text-white leading-none tracking-wide"
                                    style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)' }}
                                >
                                    Wind & Weather Data
                                </h2>
                                <p className="text-white/35 text-sm mt-2">
                                    Historical monthly averages across all destinations
                                </p>
                            </div>
                        </div>
                    </div>
```
with:
```tsx
                <section className="bg-cream">
                    {/* Section header */}
                    <div className="container mx-auto pt-16 lg:pt-20 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-12 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2
                                    className="font-display text-secondary leading-none tracking-wide"
                                    style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)' }}
                                >
                                    Wind & Weather Data
                                </h2>
                                <p className="text-secondary/50 text-sm mt-2">
                                    Historical monthly averages across all destinations
                                </p>
                            </div>
                        </div>
                    </div>
```

- [ ] **Step 2: Retheme the wind chart to dark-on-light**

In `resources/js/Components/Destinations/AllDestinationsWindChart.tsx`:

(a) Change the axis constants (lines 27-28):
```tsx
const AXIS_TICK = { fill: 'rgba(0,0,0,0.6)', fontSize: 11 }
const AXIS_LINE = { stroke: 'rgba(0,0,0,0.15)' }
```

(b) Tooltip — replace the outer `<div>` and header block:
```tsx
            <div className="min-w-[10rem] bg-white border border-black/10 p-3 shadow-xl">
                <p className="text-primary text-xs uppercase tracking-wide border-b border-black/10 pb-2 mb-2 flex items-center justify-between gap-x-3">
                    {month} <span className="text-secondary/50">{activeYear}</span>
                </p>
```
(The per-destination `<li>` rows keep their inline `style={{ color: colours[location] }}` — the muted colours read fine on white.)

(c) Card wrapper — replace `<div className="bg-secondary/60 border border-white/20 p-6 lg:p-8 space-y-6">` with:
```tsx
        <div className="bg-white border border-black/10 p-6 lg:p-8 space-y-6">
```

(d) Header — replace the `<h3>`/`<p>` block:
```tsx
                    <h3 className="font-display text-secondary tracking-wide"
                        style={{ fontSize: 'clamp(1.4rem, 3vw, 2rem)' }}>
                        Wind Speed Averages
                    </h3>
                    <p className="text-secondary/50 text-xs mt-1">Monthly breakdown by spot · {activeYear}</p>
```

(e) Wind/Gust toggle — replace the label block's three coloured elements:
```tsx
                    <label className="inline-flex items-center cursor-pointer gap-2.5">
                        <span className={`text-xs uppercase tracking-wide ${!showAverageGustData ? 'text-secondary' : 'text-secondary/40'}`}>
                            Wind
                        </span>
                        <input
                            type="checkbox"
                            checked={showAverageGustData}
                            onChange={(e) => setShowAverageGustData(e.target.checked)}
                            className="sr-only peer"
                        />
                        <div className="relative w-10 h-5 bg-secondary/15 border border-secondary/20 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-secondary/20 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary peer-checked:border-primary" />
                        <span className={`text-xs uppercase tracking-wide ${showAverageGustData ? 'text-secondary' : 'text-secondary/40'}`}>
                            Gust
                        </span>
                    </label>
```

(f) Unit radios — replace the container + buttons:
```tsx
                    <div className="flex items-center gap-1 border border-secondary/15">
                        {unitOptions.map((unit) => {
                            const active = activeWindUnit === unit
                            return (
                                <button
                                    key={unit}
                                    onClick={() => setActiveWindUnit(unit)}
                                    className={`px-3 py-1.5 text-xs uppercase tracking-wide transition-colors duration-200 ${
                                        active
                                            ? 'bg-primary text-white'
                                            : 'text-secondary/50 hover:text-secondary'
                                    }`}
                                >
                                    {unit}
                                </button>
                            )
                        })}
                    </div>
```

(g) YAxis label fill — in the `<YAxis label={{ ... }}>`, change `fill: 'rgba(255,255,255,0.55)'` to `fill: 'rgba(0,0,0,0.5)'`.

(h) Disclaimer — replace the `<p>`:
```tsx
            <p className="text-secondary/50 text-xs leading-relaxed border-t border-black/10 pt-4">
                <strong className="text-secondary">Note:</strong> Wind data calculated from historical records via the{' '}
                <a href="https://open-meteo.com/" target="_blank" rel="noreferrer noopener" className="underline underline-offset-2 hover:text-primary transition-colors">
                    Open-Meteo API
                </a>
                . Long-term averages — actual conditions may vary.
            </p>
```

- [ ] **Step 3: Retheme the temp chart to dark-on-light**

In `resources/js/Components/Destinations/AllDestinationsTempChart.tsx`:

(a) Axis constants (lines 21-22):
```tsx
const AXIS_TICK = { fill: 'rgba(0,0,0,0.6)', fontSize: 11 }
const AXIS_LINE = { stroke: 'rgba(0,0,0,0.15)' }
```

(b) Tooltip outer div + header (mirror the wind chart):
```tsx
            <div className="min-w-[10rem] bg-white border border-black/10 p-3 shadow-xl">
                <p className="text-primary text-xs uppercase tracking-wide border-b border-black/10 pb-2 mb-2 flex items-center justify-between gap-x-3">
                    {month} <span className="text-secondary/50">{activeYear}</span>
                </p>
```

(c) Card wrapper — replace `<div className="bg-secondary/60 border border-white/20 p-6 lg:p-8 space-y-6">` with:
```tsx
        <div className="bg-white border border-black/10 p-6 lg:p-8 space-y-6">
```

(d) Header:
```tsx
                <h3 className="font-display text-secondary tracking-wide"
                    style={{ fontSize: 'clamp(1.4rem, 3vw, 2rem)' }}>
                    Temperature Trends
                </h3>
                <p className="text-secondary/50 text-xs mt-1">Annual averages by spot · {activeYear}</p>
```

(e) YAxis label fill — change `fill: 'rgba(255,255,255,0.55)'` to `fill: 'rgba(0,0,0,0.5)'`.

- [ ] **Step 4: Verify in the live preview**

Load `/destinations`. Confirm the whole Wind & Weather section is now cream with white chart cards; heading, axis ticks, gridlines, toggle, radios, and disclaimer are all legible dark-on-light; the muted lines are distinct; hovering shows a white tooltip with dark text and per-destination coloured rows. Check the wind/gust toggle and kts/mph/kph radios still switch. Screenshot at desktop; then use the preview resize to check mobile + tablet.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Destinations/Index.tsx resources/js/Components/Destinations/AllDestinationsWindChart.tsx resources/js/Components/Destinations/AllDestinationsTempChart.tsx
git commit -m "feat: light theme for destinations weather section and charts"
```

---

### Task 4: Light filter bar

**Files:**
- Modify: `resources/js/Components/Destinations/FilterDataset.tsx` (`selectStyles` object + bar container/label/divider/reset classes)

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Rewrite `selectStyles` for a light surface**

In `resources/js/Components/Destinations/FilterDataset.tsx`, replace the entire `selectStyles` object (lines 20-67) with:
```tsx
const selectStyles = {
    control: (base: any, state: any) => ({
        ...base,
        backgroundColor: 'white',
        borderColor: state.isFocused ? 'hsl(192 89% 25%)' : 'rgba(0,0,0,0.15)',
        borderRadius: 0,
        boxShadow: 'none',
        color: 'hsl(0 1% 15%)',
        minHeight: '2.75rem',
        '&:hover': { borderColor: 'rgba(0,0,0,0.35)' },
    }),
    singleValue: (base: any) => ({ ...base, color: 'hsl(0 1% 15%)', fontSize: '0.875rem' }),
    multiValue: (base: any) => ({
        ...base,
        backgroundColor: 'hsl(169 28% 89%)',
        borderRadius: 0,
    }),
    multiValueLabel: (base: any) => ({ ...base, color: 'hsl(192 89% 20%)', fontSize: '0.75rem' }),
    multiValueRemove: (base: any) => ({
        ...base,
        color: 'hsl(192 89% 30%)',
        ':hover': { backgroundColor: 'hsl(185 36% 70%)', color: 'hsl(192 89% 15%)' },
    }),
    placeholder: (base: any) => ({ ...base, color: 'rgba(0,0,0,0.4)', fontSize: '0.875rem' }),
    menu: (base: any) => ({
        ...base,
        backgroundColor: 'white',
        borderRadius: 0,
        border: '1px solid rgba(0,0,0,0.1)',
        boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
    }),
    option: (base: any, state: any) => ({
        ...base,
        backgroundColor: state.isSelected
            ? 'hsl(192 89% 25%)'
            : state.isFocused
                ? 'hsl(169 28% 89%)'
                : 'transparent',
        color: state.isSelected ? 'white' : 'hsl(0 1% 15%)',
        fontSize: '0.875rem',
        cursor: 'pointer',
    }),
    input: (base: any) => ({ ...base, color: 'hsl(0 1% 15%)' }),
    dropdownIndicator: (base: any) => ({ ...base, color: 'rgba(0,0,0,0.4)', padding: '0 8px' }),
    clearIndicator: (base: any) => ({ ...base, color: 'rgba(0,0,0,0.4)', padding: '0 8px' }),
    indicatorSeparator: (base: any) => ({ ...base, backgroundColor: 'rgba(0,0,0,0.15)' }),
    valueContainer: (base: any) => ({ ...base, padding: '2px 10px' }),
}
```

- [ ] **Step 2: Retheme the bar container, label, divider, and reset button**

Replace the outer container and its inner label/divider/reset (the `return (...)` block's wrapper, label, divider, and reset button). Change:
```tsx
        <div className="bg-primary border-y border-white/10">
```
to a soft pale-teal band:
```tsx
        <div className="bg-primary-lightest border-y border-secondary/10">
```
Change the label block:
```tsx
                    <div className="flex items-center gap-2.5 shrink-0">
                        <Icon icon={faSlidersH} customClasses="text-primary" size="size-4" />
                        <span className="text-primary text-xs uppercase tracking-[0.2em] font-medium">
                            Filter data
                        </span>
                    </div>
```
Change the divider:
```tsx
                    <div className="hidden lg:block w-px h-6 bg-secondary/15 shrink-0" />
```
Change the reset button:
```tsx
                    <button
                        className="shrink-0 flex items-center justify-center gap-2 px-4 py-2.5 border border-secondary/25 text-secondary/70 hover:text-secondary hover:border-secondary/50 text-xs uppercase tracking-wide transition-all duration-200"
                        onClick={onReset}
                    >
                        <Icon icon={faRotateLeft} size="size-3.5" />
                        Reset
                    </button>
```

- [ ] **Step 3: Verify in the live preview**

Load `/destinations`. Confirm the filter bar is a pale-teal band with dark label + teal icon; the year and destinations selects have white controls with dark text and readable menus; selected destination chips are pale-teal with dark teal text; the reset button reads dark-on-light and works. Check the whole section reads as one calm light surface with the charts. Screenshot desktop + mobile.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Destinations/FilterDataset.tsx
git commit -m "feat: light theme for destinations filter bar"
```

---

## Final verification (before PR)

- [ ] `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run test:js` — all palette tests pass.
- [ ] `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build` — production build succeeds (catches type / import-case errors the Linux/Cloud build would hit).
- [ ] `php artisan test` — PHP suite still green (unaffected, but confirm).
- [ ] Visual pass on `/destinations` in the preview at mobile / tablet / desktop: map light, section + charts + filter bar light and legible, muted lines distinct, tooltips readable. Screenshot for the PR.
