# Spot-Guide Quick-Nav Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A sticky, content-aware quick-nav on the spot-guide page: lists only the sections that have content, highlights the active one on scroll, and smooth-scrolls on click; a swipeable chip row on mobile.

**Architecture:** A pure helper derives the present sections from the `spotGuide` prop; a new `SpotGuideNav` component renders the sticky bar with scroll-spy + smooth-scroll; `SpotGuide/Show.tsx` gains matching `id` anchors and renders the nav. Frontend-only — no backend/data change.

**Tech Stack:** React 19 + TypeScript, Inertia, Tailwind v3, Vite 7 (Node 22), Vitest.

## Global Constraints

- **Frontend-only.** No controller/model/route/migration change. All data is already on the `spotGuide` prop.
- **Present-only, DOM order.** The nav lists only sections with content, in the page's DOM order; the free-form Content Builder blocks are **excluded**. The bar renders only when **≥ 2** sections are present.
- **Section ids ↔ helper labels stay in lockstep** (the anchors added to `Show.tsx` must match the ids the helper emits): `introduction`, `gallery`, `water-conditions`, `wind-conditions`, `when-to-go`, `weather`, `where-to-stay`, `where-to-eat`, `windsurfing-spots`, `explore-the-area`, `getting-there`, `lessons-and-hire`.
- **Sticky at `top: 0`** — on the spot-guide page the NavBar is `position: relative` (scrolls away), so no navbar offset; sections carry `scroll-mt-14` (3.5rem) so a smooth-scroll clears the pinned bar.
- **Site tokens only** — white bar, `orange` accent, `primary` active chip, `text-secondary`, uppercase `tracking-[0.15em]`. No new colour utilities.
- **Node 22** for any npm/vite/vitest command: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null &&`.

---

### Task 1: Section-derivation helper (pure, TDD)

**Files:**
- Create: `resources/js/Helpers/spotGuideSections.ts`
- Test: `resources/js/Helpers/spotGuideSections.test.ts`

**Interfaces:**
- Produces: `interface SpotGuideSection { id: string; label: string }` and
  `buildSpotGuideSections(spotGuide: SpotGuideLike): SpotGuideSection[]` — present-only, DOM order. Consumed by Tasks 2 & 3.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Helpers/spotGuideSections.test.ts`:
```ts
import { describe, it, expect } from 'vitest'
import { buildSpotGuideSections } from './spotGuideSections'

/** A guide with every section populated. */
const fullGuide = () => ({
    introduction_text: '<p>hi</p>',
    gallery: [{}],
    water_conditions: { content: '<p>w</p>' },
    wind_conditions: { content: '<p>w</p>' },
    when_to_go: '<p>go</p>',
    weather_records: { '2026': [{}] },
    where_to_stay_intro: '<p>stay</p>',
    stay_recommendations: [{}],
    where_to_eat_intro: '<p>eat</p>',
    eat_recommendations: [{}],
    windsurfing_locations: [{}],
    latitude: 1, longitude: 2,
    travelling_to: { content: '<p>t</p>' },
    lessons_and_hire: { content: '<p>l</p>' },
})

/** A guide with nothing populated. */
const emptyGuide = () => ({
    introduction_text: null, gallery: [], water_conditions: null, wind_conditions: null,
    when_to_go: null, weather_records: {}, where_to_stay_intro: null, stay_recommendations: [],
    where_to_eat_intro: null, eat_recommendations: [], windsurfing_locations: [],
    latitude: null, longitude: null, travelling_to: null, lessons_and_hire: null,
})

describe('buildSpotGuideSections', () => {
    it('returns all sections, in DOM order, for a fully-populated guide', () => {
        expect(buildSpotGuideSections(fullGuide()).map((s) => s.id)).toEqual([
            'introduction', 'gallery', 'water-conditions', 'wind-conditions', 'when-to-go',
            'weather', 'where-to-stay', 'where-to-eat', 'windsurfing-spots',
            'explore-the-area', 'getting-there', 'lessons-and-hire',
        ])
    })

    it('returns only present sections', () => {
        const g = { ...emptyGuide(), introduction_text: '<p>hi</p>', when_to_go: '<p>go</p>' }
        expect(buildSpotGuideSections(g).map((s) => s.id)).toEqual(['introduction', 'when-to-go'])
    })

    it('excludes content-builder blocks (nothing nameable → empty)', () => {
        // content_blocks is not a field the helper reads, so a guide with only
        // content blocks yields no nav sections.
        expect(buildSpotGuideSections(emptyGuide())).toEqual([])
    })

    it('shows explore-the-area only with coords AND at least one mappable location', () => {
        const withCoordsNoLocs = { ...emptyGuide(), latitude: 1, longitude: 2 }
        expect(buildSpotGuideSections(withCoordsNoLocs).map((s) => s.id)).not.toContain('explore-the-area')

        const withCoordsAndSpot = { ...emptyGuide(), latitude: 1, longitude: 2, windsurfing_locations: [{}] }
        expect(buildSpotGuideSections(withCoordsAndSpot).map((s) => s.id)).toContain('explore-the-area')

        const locsNoCoords = { ...emptyGuide(), stay_recommendations: [{}] }
        expect(buildSpotGuideSections(locsNoCoords).map((s) => s.id)).not.toContain('explore-the-area')
    })

    it('shows where-to-stay from either the intro OR a recommendation', () => {
        const introOnly = { ...emptyGuide(), where_to_stay_intro: '<p>x</p>' }
        expect(buildSpotGuideSections(introOnly).map((s) => s.id)).toContain('where-to-stay')

        const recOnly = { ...emptyGuide(), stay_recommendations: [{}] }
        expect(buildSpotGuideSections(recOnly).map((s) => s.id)).toContain('where-to-stay')
    })

    it('provides human labels', () => {
        const g = { ...emptyGuide(), travelling_to: { content: '<p>t</p>' } }
        expect(buildSpotGuideSections(g)).toEqual([{ id: 'getting-there', label: 'Getting There' }])
    })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run test:js`
Expected: FAIL — `spotGuideSections.ts` / `buildSpotGuideSections` doesn't exist yet.

- [ ] **Step 3: Implement the helper**

Create `resources/js/Helpers/spotGuideSections.ts`:
```ts
// Derives the spot-guide quick-nav's section list from the spotGuide prop:
// present-only, in the same order the sections render on the page. The free-form
// Content Builder blocks have no fixed name/anchor, so they are intentionally
// not represented here. Kept a pure function so it's unit-testable and the nav
// component stays declarative. The ids returned MUST match the anchor ids added
// to SpotGuide/Show.tsx.

/** One entry in the quick-nav. `id` is the target anchor; `label` is the chip text. */
export interface SpotGuideSection {
    id: string
    label: string
}

/** The subset of spot-guide fields the section guards read. */
interface SpotGuideLike {
    introduction_text: string | null
    gallery: unknown[]
    water_conditions: { content?: string } | null
    wind_conditions: { content?: string } | null
    when_to_go: string | null
    weather_records: Record<string, unknown>
    where_to_stay_intro: string | null
    stay_recommendations: unknown[]
    where_to_eat_intro: string | null
    eat_recommendations: unknown[]
    windsurfing_locations: unknown[]
    latitude: number | null
    longitude: number | null
    travelling_to: { content?: string } | null
    lessons_and_hire: { content?: string } | null
}

/**
 * Ordered section rules. Each mirrors the exact "has content" guard used in
 * SpotGuide/Show.tsx, in DOM order (Content Builder omitted).
 */
const SECTION_RULES: { id: string; label: string; present: (guide: SpotGuideLike) => boolean }[] = [
    { id: 'introduction', label: 'Introduction', present: (g) => !!g.introduction_text },
    { id: 'gallery', label: 'Gallery', present: (g) => g.gallery.length > 0 },
    { id: 'water-conditions', label: 'Water Conditions', present: (g) => !!g.water_conditions?.content },
    { id: 'wind-conditions', label: 'Wind Conditions', present: (g) => !!g.wind_conditions?.content },
    { id: 'when-to-go', label: 'When To Go', present: (g) => !!g.when_to_go },
    { id: 'weather', label: 'Weather', present: (g) => Object.keys(g.weather_records).length > 0 },
    { id: 'where-to-stay', label: 'Where To Stay', present: (g) => !!g.where_to_stay_intro || g.stay_recommendations.length > 0 },
    { id: 'where-to-eat', label: 'Where To Eat', present: (g) => !!g.where_to_eat_intro || g.eat_recommendations.length > 0 },
    { id: 'windsurfing-spots', label: 'Windsurfing Spots', present: (g) => g.windsurfing_locations.length > 0 },
    {
        id: 'explore-the-area',
        label: 'Explore The Area',
        // Mirrors Show.tsx: needs coords AND at least one mappable location.
        present: (g) => !!(g.latitude && g.longitude)
            && (g.stay_recommendations.length + g.eat_recommendations.length + g.windsurfing_locations.length) > 0,
    },
    { id: 'getting-there', label: 'Getting There', present: (g) => !!g.travelling_to?.content },
    { id: 'lessons-and-hire', label: 'Lessons & Hire', present: (g) => !!g.lessons_and_hire?.content },
]

/**
 * Build the present-only, DOM-ordered quick-nav section list for a spot guide.
 * @param spotGuide - The guide (only the guard fields are read).
 * @returns Sections that have content, each `{ id, label }`.
 */
export function buildSpotGuideSections(spotGuide: SpotGuideLike): SpotGuideSection[] {
    return SECTION_RULES
        .filter((rule) => rule.present(spotGuide))
        .map(({ id, label }) => ({ id, label }))
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run test:js`
Expected: PASS (all `spotGuideSections` cases; the existing `colours` tests stay green).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Helpers/spotGuideSections.ts resources/js/Helpers/spotGuideSections.test.ts
git commit -m "feat: pure helper deriving present spot-guide nav sections"
```

---

### Task 2: SpotGuideNav component

**Files:**
- Create: `resources/js/Components/SpotGuide/SpotGuideNav.tsx`

**Interfaces:**
- Consumes: `SpotGuideSection[]` (Task 1).
- Produces: `<SpotGuideNav sections={…} />` (default export). Consumed by Task 3.

- [ ] **Step 1: Create the component**

Create `resources/js/Components/SpotGuide/SpotGuideNav.tsx`:
```tsx
// Sticky, content-aware quick-nav for the spot-guide page. Renders a horizontal
// bar of section links that pins to the top as you scroll the guide; the active
// section (via IntersectionObserver) is highlighted, clicking smooth-scrolls to
// it, and on mobile the row scrolls horizontally with the active chip auto-
// centred. Renders nothing for fewer than 2 sections. Frontend-only; targets are
// the #id anchors on the sections in SpotGuide/Show.tsx.

import { useEffect, useRef, useState } from 'react'
import type { SpotGuideSection } from '@/Helpers/spotGuideSections'

interface Props {
    /** Present sections in display order (from buildSpotGuideSections). */
    sections: SpotGuideSection[]
}

/**
 * Render the sticky spot-guide quick-nav.
 * @param props - See {@link Props}.
 */
const SpotGuideNav = ({ sections }: Props) => {
    const [activeId, setActiveId] = useState<string | null>(sections[0]?.id ?? null)
    const barRef = useRef<HTMLDivElement | null>(null)

    // Scroll-spy: the top-most intersecting section (just below the pinned bar)
    // is the active one. rootMargin trims the sticky-bar band off the top and
    // most of the lower viewport so the "current" section is the one at the top.
    useEffect(() => {
        if (sections.length < 2) return
        const observer = new IntersectionObserver(
            (entries) => {
                const topMost = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0]
                if (topMost) setActiveId(topMost.target.id)
            },
            { rootMargin: '-56px 0px -55% 0px', threshold: 0 }
        )
        sections.forEach((section) => {
            const el = document.getElementById(section.id)
            if (el) observer.observe(el)
        })
        return () => observer.disconnect()
    }, [sections])

    // Keep the active chip visible in the horizontally-scrolling strip (mobile).
    useEffect(() => {
        if (!activeId || !barRef.current) return
        const chip = barRef.current.querySelector<HTMLElement>(`[data-section="${activeId}"]`)
        chip?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
    }, [activeId])

    if (sections.length < 2) return null

    /** Smooth-scroll to a section; scroll-mt on the target clears the pinned bar. */
    const goTo = (id: string) => {
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    return (
        <div className="sticky top-0 z-40 bg-white border-b border-secondary/10">
            <div className="relative container mx-auto">
                <div className="flex items-stretch">
                    <div className="w-1 bg-orange shrink-0 my-2 rounded-full" />
                    <div
                        ref={barRef}
                        className="flex gap-1 items-center overflow-x-auto py-2 pl-3 [&::-webkit-scrollbar]:hidden"
                        style={{ scrollbarWidth: 'none' }}
                    >
                        {sections.map((section) => {
                            const active = section.id === activeId
                            return (
                                <button
                                    key={section.id}
                                    data-section={section.id}
                                    onClick={() => goTo(section.id)}
                                    className={`shrink-0 text-[11px] uppercase tracking-[0.15em] px-3 py-1.5 rounded-sm transition-colors duration-200 ${
                                        active ? 'bg-primary text-white' : 'text-secondary/70 hover:text-secondary'
                                    }`}
                                >
                                    {section.label}
                                </button>
                            )
                        })}
                    </div>
                </div>
                {/* Right-edge fade hints there are more chips to swipe (mobile). */}
                <div className="pointer-events-none absolute right-0 top-0 bottom-0 w-10 bg-gradient-to-r from-transparent to-white md:hidden" />
            </div>
        </div>
    )
}

export default SpotGuideNav
```

- [ ] **Step 2: Build to verify no type/JSX errors**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Expected: build succeeds. (It's not wired into a page until Task 3; live behaviour is verified there.)

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/SpotGuide/SpotGuideNav.tsx
git commit -m "feat: SpotGuideNav sticky quick-nav component (scroll-spy + smooth-scroll)"
```

---

### Task 3: Wire the nav + anchors into the spot-guide page

**Files:**
- Modify: `resources/js/Pages/SpotGuide/Show.tsx`

**Interfaces:**
- Consumes: `buildSpotGuideSections` (Task 1) + `SpotGuideNav` (Task 2).

- [ ] **Step 1: Import the helper + component and compute sections**

In `resources/js/Pages/SpotGuide/Show.tsx`, add imports near the other `@/` imports:
```tsx
import SpotGuideNav from '@/Components/SpotGuide/SpotGuideNav'
import { buildSpotGuideSections } from '@/Helpers/spotGuideSections'
```
Inside `const Show = ({ spotGuide, meta }) => {`, after the `mapLocations` memo, add:
```tsx
    /* Present sections for the sticky quick-nav (see buildSpotGuideSections). */
    const navSections = useMemo(() => buildSpotGuideSections(spotGuide), [spotGuide])
```

- [ ] **Step 2: Render the nav after the masthead**

Immediately after the masthead block (the `<div className="relative"> … </div>` that closes at line ~181, containing `StaticMasthead` + `SpotOverview`) and before the Introduction section, add:
```tsx
            {/* ── Sticky quick-nav ── */}
            <SpotGuideNav sections={navSections} />
```

- [ ] **Step 3: Add the anchor id + scroll-mt to every section**

Apply these exact edits (each adds `id` + `scroll-mt-14` so a smooth-scroll clears the pinned bar). For `<section>` sections, add to the element; for component sections, wrap in an `id`'d `<div>`.

Introduction (line ~184):
```tsx
                <section id="introduction" className="bg-cream scroll-mt-14">
```
Gallery (line ~195-198) — wrap:
```tsx
            {spotGuide.gallery.length > 0 && (
                <div id="gallery" className="scroll-mt-14">
                    <Gallery images={spotGuide.gallery} />
                </div>
            )}
```
Water Conditions (line ~200-208) — wrap:
```tsx
            {spotGuide.water_conditions?.content && (
                <div id="water-conditions" className="scroll-mt-14">
                    <ContentWithBackgroundImage
                        backgroundImageUrl={spotGuide.water_conditions_bg}
                        content={spotGuide.water_conditions.content}
                        textRight={spotGuide.water_conditions.text_right}
                        title="Water Conditions"
                    />
                </div>
            )}
```
Wind Conditions (line ~210-218) — wrap the same way with `id="wind-conditions"`.
When To Go (line ~224):
```tsx
                <section id="when-to-go" className="bg-white scroll-mt-14">
```
Weather Statistics (line ~236-239) — wrap:
```tsx
            {Object.keys(spotGuide.weather_records).length > 0 && (
                <div id="weather" className="scroll-mt-14">
                    <SpotGuideStatistics weatherRecords={spotGuide.weather_records} />
                </div>
            )}
```
Where To Stay (line ~243):
```tsx
                <section id="where-to-stay" className="bg-cream scroll-mt-14">
```
Where To Eat (line ~263):
```tsx
                <section id="where-to-eat" className="bg-white scroll-mt-14">
```
Windsurfing Spots (line ~283):
```tsx
                <section id="windsurfing-spots" className="bg-cream scroll-mt-14">
```
Explore The Area / map (line ~295):
```tsx
                <section id="explore-the-area" className="bg-secondary scroll-mt-14">
```
Getting There (line ~322-329) — wrap:
```tsx
            {spotGuide.travelling_to?.content && (
                <div id="getting-there" className="scroll-mt-14">
                    <ContentWithBackgroundImage
                        backgroundImageUrl={spotGuide.travelling_to_bg}
                        content={spotGuide.travelling_to.content}
                        textRight={spotGuide.travelling_to.text_right}
                        title="Getting There"
                    />
                </div>
            )}
```
Lessons & Hire (line ~332-339) — wrap the same way with `id="lessons-and-hire"`.

(Leave the Content Builder block at line ~220-221 untouched and un-anchored — it's intentionally not in the nav.)

- [ ] **Step 4: Build**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/SpotGuide/Show.tsx
git commit -m "feat: anchor spot-guide sections + render sticky quick-nav"
```

---

## Final verification (before PR)

- [ ] `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run test:js` — helper tests green (+ existing JS tests).
- [ ] `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build` — production build succeeds.
- [ ] `php artisan test` — PHP suite still green (unaffected, confirm).
- [ ] Live preview on a populated spot guide (e.g. `/destinations/le-morne`): the bar pins to the top once you scroll past the masthead; the active chip tracks the section in view; clicking a chip smooth-scrolls with the heading clear of the bar; only present sections appear (no Content Builder entry). Resize to mobile: the chip row scrolls horizontally, the active chip stays centred, and the right-edge fade shows. Confirm the bar reads in the site style (white / orange accent / primary active). Screenshot desktop + mobile for the PR.
