---
title: Spot-guide quick-navigation — sub-project C
tags: [spot-guide, navigation, sticky, scroll-spy, inertia, react, frontend]
status: stable
completed: 2026-07-10
commits: [e807b8a, 0bb45c0, 25a83ff, 3ba2145, dd4ffb3, 2cbdd25, eca8067, 4d5f969]
pr: 27
---

# Spot-guide quick-nav

## Why

Spot-guide pages are long (a dozen possible sections). Add a sticky in-page
quick-nav that lists only the sections a given guide actually has, highlights
the one you're on, and smooth-scrolls to any of them — a swipeable chip row on
mobile. Final piece of the featured/content brainstorm (A = #25, B = #26).
Frontend-only.

## What shipped

- **`resources/js/Helpers/spotGuideSections.ts`** (pure, Vitest-tested):
  `buildSpotGuideSections(spotGuide)` → ordered `{ id, label }[]` of present
  sections, in the page's DOM order, mirroring each section's exact "has
  content" guard from `Show.tsx`. The free-form Content Builder blocks are
  excluded (no stable name/anchor).
- **`resources/js/Components/SpotGuide/SpotGuideNav.tsx`**: a sticky bar
  (`sticky top-0 z-40`, white, orange left accent, `primary` active chip). On
  desktop the section chips sit in the bar; on mobile they're a single
  horizontally-scrollable row with the active chip auto-centred and a right-edge
  fade. Clicking a chip smooth-scrolls to the section. Renders nothing for < 2
  sections. `<nav aria-label>` landmark + `aria-current` on the active chip.
- **`resources/js/Pages/SpotGuide/Show.tsx`**: memoised `navSections`, renders
  `<SpotGuideNav>` after the masthead, and gives each of the 12 sections a
  matching `id` + `scroll-mt-14` (56px) so a smooth-scroll lands with the
  heading clear of the pinned bar. NavBar is `relative` on the spot-guide page
  (scrolls away), so the bar pins at `top:0` with no navbar offset.

## Findings worth keeping

- **The headless preview dispatches no scroll events, no IntersectionObserver
  callbacks, and paints black** — it skips the browser "update the rendering"
  step. Proven: an injected scroll listener logged 0 fires despite `scrollY`
  reaching 2500; an injected IO logged 0 fires. Consequence: **any scroll-driven
  behaviour (IO or scroll listener) is unverifiable in the preview**, and
  `AnimateInView` (which uses IO) won't reveal its content there either. So:
  - Scroll-spy was implemented with a **rAF-throttled passive `scroll`
    listener** (not IntersectionObserver): it recomputes the active section (the
    last one whose `top <= 72`, just under the bar) once per frame. IO would be
    marginally more elegant but is a silent-failure risk in non-painting
    contexts and can't be validated here.
  - What WAS verified in-preview (env-independent): content-aware section list
    (Le Morne → Weather / Where To Stay / Explore The Area only), chips↔anchors
    1:1, sticky pins to `top:0`, `scroll-mt` clears the heading (section lands at
    56px), a11y attributes present.
  - Smooth-scroll + active highlight are correct by inspection but were **not**
    exercised in the preview — eyeball on the real (Herd) site.
- **Anchor ids and helper ids must stay in lockstep** — a mismatch is a dead nav
  link. The helper's rule table is the single source; `Show.tsx` anchors mirror it.

## Follow-ups

- Optional: a light jsdom/DOM test of the active-on-scroll math (the helper's
  ordering is already unit-tested; the scroll→active mapping is inspection-only).
- Deep-linking to `#section` on load (the anchors make it trivial) — not built.

## Delivery

Frontend-only, no migration. This closes the three-part featured/content
brainstorm (A #25, B #26, C #27).
