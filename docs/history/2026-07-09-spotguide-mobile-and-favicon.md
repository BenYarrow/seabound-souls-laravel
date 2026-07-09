---
title: Spot-guide masthead mobile layout fix + branded favicon
tags: [frontend, responsive, spot-guide, favicon]
status: stable
completed: 2026-07-09
commits: [e98219f, a655ff2, e40efa3]
pr: 20
---

# Spot-guide mobile layout + favicon

## What shipped

Two post-launch polish items.

- **Spot-guide masthead on mobile.** `SpotOverview` and `LiveWeatherData` were
  passed as children of the fixed-height `StaticMasthead`, so on mobile (where
  they're normal flow, not absolutely positioned) they piled on top of the
  image — cramped and illegible. Fix: `Show.tsx` wraps the masthead + overview
  in a `relative` div. `LiveWeatherData` **stays a child** of the masthead so it
  floats over the image at every breakpoint (inset from the bottom-left corner);
  `SpotOverview` becomes a **sibling** — the desktop right sidebar is unchanged,
  and on mobile it drops below the image as a dark full-width band. The 5
  overview items are `flex-wrap`ped to sit 3-on-top / 2-centred-below, and the
  desktop expand-chevron is hidden on mobile. Desktop is visually unchanged.
- **Branded favicon.** The site shipped with an empty `favicon.ico` and no
  favicon `<link>`. Added the branded icon from the reference build.

## Findings worth keeping

- **The reference "favicon.ico" is actually a PNG** (magic `89504e47`). Next.js
  lets you name a PNG `favicon.ico`; a real `.ico` starts `00 00 01 00`. Serve it
  as `favicon.png` with `<link rel="icon" type="image/png" …>` — correct type,
  and it also sidesteps a **local Herd quirk**: Herd's global nginx config has
  `location = /favicon.ico { access_log off; log_not_found off; }` (and the same
  for `/robots.txt`), which 404s those exact root paths on *every* Herd site on
  the machine. A `/favicon.png` request doesn't match that block, so it serves.
  This is local-only; Laravel Cloud serves `public/` normally.
- **Weather widget unified to one card.** The component previously had separate
  desktop (`hidden lg:flex`) and mobile (`lg:hidden`) markup. Per the design
  ask ("keep the desktop spacing on mobile"), it's now a single floating card at
  all breakpoints (`bottom-6 left-6` → `lg:bottom-8 lg:left-8`) — simpler and
  renders once (no duplicated mount, no double weather-API call).
- **Responsive display-switching order.** The overview list uses base `flex
  flex-wrap` (mobile) → `md:grid md:grid-cols-5` (tablet) → `lg:flex
  lg:flex-col` (desktop sidebar). Because Tailwind orders base < md < lg in the
  stylesheet, each breakpoint's `display` cleanly overrides the previous.

## Test plan

Responsive CSS and a favicon are not unit-testable; the backend is untouched, so
the PHPUnit suite stays green (**100 passing, 516 assertions**). Verified by
serving locally: `/favicon.png` → 200 (PNG); the `<link>` renders in the page
head. Desktop masthead unchanged; mobile stacks image → weather card over image
→ overview band below.

Spec: `docs/superpowers/specs/2026-07-09-spotguide-mobile-and-favicon-design.md`.

## Follow-ups / residual

- A true multi-resolution `favicon.ico` (+ `apple-touch-icon` / web manifest)
  could be generated later; a single PNG favicon is sufficient for now.
