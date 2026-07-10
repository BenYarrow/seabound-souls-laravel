# Spot-guide quick-navigation — Design

**Date:** 2026-07-10
**Status:** Approved (pending spec review)

## Goal

Add a sticky in-page quick-nav to the spot-guide page: a slim bar that pins to
the top as you scroll through a guide, listing **only the sections that have
content**, each smooth-scrolling to its section, with the active section
highlighted as you scroll. On mobile it's a single horizontally-swipeable row of
chips. Must fit the existing site look (cream/white surfaces, `orange` accent,
`primary` active state, `font-display` uppercase-tracking).

Sub-project **C** of the featured/content brainstorm (A = #25, B = #26). This one
is frontend-only.

## Background (verified in code)

- `resources/js/Pages/SpotGuide/Show.tsx` renders the guide as a sequence of
  sections, each already guarded by a "has content" condition. In DOM order:
  Introduction, Gallery, Water Conditions, Wind Conditions, *(Content Builder —
  variable, no fixed name)*, When To Go, Weather Statistics, Where To Stay,
  Where To Eat, Windsurfing Spots, Explore The Area (map), Getting There,
  Lessons & Hire. **No section has an `id` anchor yet.**
- Section content guards (mirror these exactly in the helper):
  - Introduction — `introduction_text`
  - Gallery — `gallery.length > 0`
  - Water Conditions — `water_conditions?.content`
  - Wind Conditions — `wind_conditions?.content`
  - When To Go — `when_to_go`
  - Weather — `Object.keys(weather_records).length > 0`
  - Where To Stay — `where_to_stay_intro || stay_recommendations.length > 0`
  - Where To Eat — `where_to_eat_intro || eat_recommendations.length > 0`
  - Windsurfing Spots — `windsurfing_locations.length > 0`
  - Explore The Area — `latitude && longitude && (stay + eat + windsurfing).length > 0`
  - Getting There — `travelling_to?.content`
  - Lessons & Hire — `lessons_and_hire?.content`
- `NavBar` is `h-[5rem]` and, on the spot-guide page (not the homepage), is
  `position: relative` — it scrolls away with the page. So the quick-nav can pin
  at `top: 0` with **no navbar offset** (the navbar is gone by the time the bar
  reaches the top).
- All data the nav needs is already on the `spotGuide` prop — **no
  backend/controller/data change**.

## Decisions

- **Placement:** a sticky horizontal sub-bar (chosen over a side rail, which
  fights the full-width background-image bands). Rendered right after the
  masthead, before the Introduction section; `position: sticky; top: 0; z-40`.
- **Content-aware:** the bar lists only present sections; the free-form Content
  Builder blocks are **excluded** (no stable name/anchor). The bar renders only
  when **≥ 2** sections are present.
- **Section derivation is a pure helper** — `buildSpotGuideSections(spotGuide)`
  returns an ordered `{ id, label }[]` of present sections, so the logic is
  unit-testable and the component stays declarative.
- **Anchors:** each section in `Show.tsx` gets a matching `id` plus
  `scroll-mt-[3.5rem]` (≈ the bar height) so a smooth-scroll lands with the
  heading clear of the pinned bar.
- **Interaction:** click/tap a link → `scrollIntoView({ behavior: 'smooth',
  block: 'start' })` on the target `id`. Scroll-spy via `IntersectionObserver`
  highlights the active link. On mobile the chip row is `overflow-x-auto`; the
  active chip auto-scrolls into view (`inline: 'center'`), with a right-edge
  fade hinting more.
- **Styling:** white bar, thin `orange` left accent, links in
  uppercase/tracking `text-secondary`; active link `bg-primary text-white`.
  Matches the approved mockups. No frontend-design skill — reuse existing tokens.

## Scope & Components

### 1. `resources/js/Helpers/spotGuideSections.ts` (new, pure)
```ts
export interface SpotGuideSection { id: string; label: string }
/** Present-only, in DOM order; Content Builder excluded. */
export function buildSpotGuideSections(spotGuide: SpotGuideLike): SpotGuideSection[]
```
Implemented as a fixed ordered list of `{ id, label, present(spotGuide) }` rules
(mirroring the guards above), filtered to those whose `present` is truthy. Takes
a minimally-typed `SpotGuideLike` (the fields the guards read) so it's testable
with plain objects. The `explore-the-area` rule computes
`(stay_recommendations.length + eat_recommendations.length + windsurfing_locations.length) > 0`
alongside the lat/long check.

Section id ↔ label map (ids are the anchors added in Show.tsx):
`introduction`/Introduction, `gallery`/Gallery, `water-conditions`/Water Conditions,
`wind-conditions`/Wind Conditions, `when-to-go`/When To Go, `weather`/Weather,
`where-to-stay`/Where To Stay, `where-to-eat`/Where To Eat,
`windsurfing-spots`/Windsurfing Spots, `explore-the-area`/Explore The Area,
`getting-there`/Getting There, `lessons-and-hire`/Lessons & Hire.

### 2. `resources/js/Components/SpotGuide/SpotGuideNav.tsx` (new)
Props: `sections: SpotGuideSection[]`. Renders nothing if `sections.length < 2`.
- Sticky bar (`sticky top-0 z-40`), white, orange left accent, `overflow-x-auto`
  chip row; right-edge fade element.
- `activeId` state, set by an `IntersectionObserver` watching each
  `#${section.id}` (rootMargin tuned so a section counts as active a little
  below the pinned bar). Active chip: `bg-primary text-white`; others
  `text-secondary hover:text-primary`.
- Click handler: `document.getElementById(id)?.scrollIntoView({ behavior:'smooth', block:'start' })`.
- When `activeId` changes, scroll the active chip into the visible strip
  (`inline: 'center'`) so mobile keeps it on-screen.
- JSDoc on the component + the observer effect.

### 3. `resources/js/Pages/SpotGuide/Show.tsx` (modify)
- Import the helper + `SpotGuideNav`; compute
  `const sections = buildSpotGuideSections(spotGuide)` (memoised).
- Render `<SpotGuideNav sections={sections} />` immediately after the masthead
  `<div className="relative">…</div>`, before Introduction.
- Add the matching `id` + `scroll-mt-[3.5rem]` to each section wrapper. For the
  component-based sections (`Gallery`, `ContentWithBackgroundImage`,
  `SpotGuideStatistics`, `SpotGuideMap`), wrap the existing element in a
  `<div id="…" className="scroll-mt-[3.5rem]">` (don't modify those components).
  For the ones already using `<section>`, add `id` + the scroll-margin class.
- `mapLocations` already computed — reuse for the `explore-the-area` guard
  consistency (the helper recomputes from the same three arrays).

## Error Handling / Edge Cases

- Guide with < 2 sections (sparse draft) → nav renders nothing.
- A section id present in the nav but its anchor missing (shouldn't happen —
  helper and anchors are kept in lockstep) → click is a no-op (`?.`), no error.
- No JS / SSR: the bar is a plain list of `#anchor`-scrolling buttons; scroll-spy
  and smooth-scroll degrade gracefully (Inertia is client-rendered anyway).

## Testing (TDD)

- **Vitest — `spotGuideSections.test.ts`:**
  - a fully-populated guide → all 12 sections, in the exact DOM order;
  - present-only: a guide with just intro + when-to-go → `[introduction, when-to-go]`;
  - Content Builder present but nothing else nameable → not listed (excluded);
  - `explore-the-area` appears only with coords AND ≥1 stay/eat/windsurf location;
  - `where-to-stay` appears from either the intro OR a recommendation.
- **Live preview** (controller pass): on a populated guide, the bar pins on
  scroll, highlights the active section, smooth-scrolls on click with the heading
  clear of the bar; mobile chip row swipes and auto-centres the active chip.
  Verify desktop + mobile.

## Out of Scope

- Backend/controller/data changes (none needed).
- A nav entry per Content Builder block.
- Persisting scroll position / deep-linking to `#section` on load (could follow;
  the anchors make it trivial later).

## Delivery

Branch `feat/spot-guide-quicknav`; TDD (helper) + preview verification; folded
reconcile before merge; PR. Frontend-only, no migration.
