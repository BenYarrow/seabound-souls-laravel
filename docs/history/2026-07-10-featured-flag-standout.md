---
title: Featured flag + standout (blogs & spot guides) — sub-project A
tags: [featured, blogs, spot-guides, filament, inertia, react, hero, content-curation]
status: stable
completed: 2026-07-10
commits: [14b13cc, 15e934e, d21c304, 94ea7ea, cca9817, d4a2920, 009bccf, 2295540]
pr: 25
---

# Featured flag + standout

## Why

The blog index showed a "featured" post that the owner never chose — it was just
`blogs.data[0]` (the newest post of the current page). The owner wanted a
deliberate, in-app "Featured" choice for **both** blogs and spot guides, each
shown as a standout hero on its listing page. Spot guides had no hero at all.

This is **sub-project A** of three from one brainstorm (A: featured flag —
this; **B: list-content builder blocks** — finish `list_content_blogs` /
`list_content_spot_guides`, which also fixes a live Filament crash; **C:
spot-guide quick-nav** — sticky content-aware smooth-scroll nav). B and C are
still to do — see `docs/TODO.md`.

## What shipped

- **`is_featured`** boolean on `blogs` + `spot_guides` (additive migration,
  default false; fillable + boolean cast; factories default false).
- **`HasSingleFeatured` trait** (`app/Models/Concerns/`) — a `saved` hook that,
  when a row is saved featured, clears `is_featured` on every *other* row of
  that model via a query-builder `update()` (no model events → no recursion).
  One featured item per type, enforced for Filament, seeders, and tinker alike.
  Coexists with `SpotGuide::booted()`.
- **Filament**: a `Toggle` on each resource's General tab + a `ToggleColumn` in
  each list (quick-toggle also routes through the trait, since the column's
  `update()` fires `saved`).
- **`FeaturedHero`** (`resources/js/Components/Common/`) — one shared standout
  card (image + eyebrow + title + optional description + optional meta line +
  CTA), consumed by both listing pages. The old inline blog hero was refactored
  onto it (visually identical).
- **Blog index**: `BlogController@index` selects the published `is_featured`
  blog (or `null` — **no fallback**), excludes it from the paginated grid, and
  passes it as `featured`; the hero renders on page 1 only.
- **Destinations**: `DestinationController@index` passes `featuredSpotGuide`
  (published featured guide or `null`); a hero renders between the editorial
  intro and the map. The guide **stays** in its continent grid (spotlight, not
  a filter) — deliberately unlike the blog (whose grid is chronological, so it
  excludes the hero).

Design decision (owner): **no fallback.** If nothing is flagged, the prop is
`null` and no hero renders — a hero is always a deliberate choice.

## Verification

- `php artisan test` → 119 passed (716 assertions): model enforcement
  (`SingleFeaturedTest`), Filament admin path (`FeaturedToggleTest`), and both
  controllers' passed / excluded-or-kept / null(+unpublished) cases.
- `npm run build` green.
- Live preview: destinations hero (`LE MORNE / MAURITIUS`) renders between intro
  and map with the guide still in its Africa grid (verified via the
  accessibility tree — headless screenshots paint black on this page because of
  the Mapbox WebGL canvas); blog hero renders after the fix below; single-
  featured demo confirmed then dev data restored.

## Findings worth keeping

- **The Laravel paginator serialises FLAT** — `current_page` / `last_page` /
  `links` are all top-level; there is **no `meta` wrapper** (that only appears
  with API Resource collections). Gating the hero on `blogs.meta.current_page`
  threw at runtime and crashed the whole blog page (`blogs.meta` undefined).
  The type-check missed it (`meta` was typed `any`) and controller tests missed
  it (they assert props, not the React render) — the **live visual pass** caught
  it. Fixed to `blogs.current_page` (009bccf). While there, the identical
  pre-existing bug in the pagination guard (`blogs.meta?.last_page`, silently
  always-false → pagination never rendered) was fixed to `blogs.last_page`
  (2295540). Lesson: a passing controller test + green type-check does not prove
  a page renders; drive the page.
- Single-owner soft-delete edge (out of scope): restoring a trashed featured row
  won't re-enforce single (`restored` ≠ `saved`). Acceptable for now.

## Follow-ups

- **Sub-project B** — list-content builder blocks (`list_content_blogs` /
  `list_content_spot_guides`): currently broken stubs (`->relationship('','title')`
  crashes Filament) and unrendered on the frontend. Finish them (hand-pick
  entries + "view all" link, render via `FeaturedGrid`). See `docs/TODO.md`.
- **Sub-project C** — spot-guide quick-nav (sticky, content-aware, smooth-scroll).
- Optional: match the single-spot `chartColors` trio to the destinations palette
  (carried over from PR #24).
