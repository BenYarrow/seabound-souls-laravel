---
title: List-content builder blocks + homepage content-managed + gusts ranking — sub-project B
tags: [content-builder, filament, inertia, react, homepage, featured-grid, destinations, gusts]
status: stable
completed: 2026-07-10
commits: [a1fe9f5, 721d83f, 772cb59, b15a4bc, 0b89ed6, 27593af, d52cb3c, f541a8a, 5c40a5d]
pr: 26
---

# List-content blocks + homepage content-managed

## Why

The two content-builder blocks `list_content_blogs` / `list_content_spot_guides`
were **broken stubs**: their entry picker was `Select::make(...)->relationship('', 'title')`
with an empty relationship name, which crashed Filament
(`RelationshipJoiner::prepareQueryForNoConstraints(): …null given`) the moment a
block was added, and neither block was rendered on the front end. The owner
wanted to hand-pick blogs / spot guides into a curated grid with a "View all"
link — and to make the **homepage** content-managed via these blocks instead of
its hardcoded "6 latest / 3 latest" grids.

Sub-project **B** of the featured/content brainstorm (A = #25; C = spot-guide
quick-nav, still to do). Bundled in a small change carried from #25: rank
destinations by **gusts**, not wind.

## What shipped

- **Filament pickers fixed** (`ContentBuilderBlocks.php`): the crash-causing
  relationship Select → a plain options Select storing an ID array
  (`->options(fn () => Model::orderBy('title')->pluck('title','id'))`, lists all
  records incl. drafts). Added editable `viewAllUrl` + `viewAllLabel` alongside
  the existing `blockTitle` + background-colour.
- **Server resolution** (`ResolvesContentBlockMedia` trait): in the same batched
  pass that resolves media, resolve the picked IDs → **published-only** card
  entries in **authored order** (drafts / deleted dropped), emitting
  `customBlogEntries_resolved = [{id,title,slug,thumbnail}]` and
  `customSpotGuideEntries_resolved = [{…,subtitle:country}]`. All four content
  controllers already call the trait, so **no controller changes**.
- **Front-end render** (`ContentBuilder.tsx`): two new cases render the existing
  `FeaturedGrid` (heading = `blockTitle`, link = `viewAllUrl`/`viewAllLabel`,
  entries = the resolved array). `FeaturedGrid` renders nothing on empty.
- **Homepage content-managed**: removed the hardcoded `featuredSpotGuides` /
  `recentBlogs` queries + props + `<FeaturedGrid>` sections from
  `HomepageController` + `Homepage.tsx` (and their two obsolete tests). The
  homepage's featured/blog grids now come from list blocks on the home Page's
  content builder (edited in the Pages admin, template `homepage`).
- **Gusts** (`DestinationController@index`): rank by `kts_gust` (not `kts_wind`)
  for the current year+month; the intro note reads "Ordered by gusts…"; the
  three ordering tests updated.

## Verification

- `php artisan test` → 124 passed (764 assertions): crash-fix (`ListContentBlockTest`),
  resolution published-only + authored order (`PageControllerTest`), homepage
  (2 obsolete removed, 2 kept), gusts ordering (deterministic).
- `npm run build` green.
- Live preview: the home Page's **existing** list blocks (added earlier, unable
  to render before) now resolve + render — "Latest Spot Guides" (Karpathos, Le
  Morne) + "Blog test", each with a "View all" button; old hardcoded grids gone;
  `/destinations` note reads "Ordered by gusts for July 2026 …".

## Findings worth keeping

- **The crash was an empty relationship name.** A content-builder Select that
  stores IDs into JSON must use `->options(...)`, never `->relationship('', …)`.
- **Filament stores multi-select IDs as strings** (`"1"`). The trait normalises
  via `array_map('intval', …)` for the `whereIn` and `$map->get((int) $id)` for
  the mapback — string→int lookups hit because PHP integer-string array keys
  coerce. Verified end-to-end in the preview (a mismatch would silently render
  an empty grid).
- **Order preservation** = map over the stored ID array, not the query result
  (a re-order test guards it).

## Follow-ups

- **Sub-project C** — spot-guide quick-nav (sticky, content-aware smooth-scroll).
- Optional: a test locking the empty/all-draft-picks contract ("resolved is `[]`
  → renders nothing"), since the homepage's default state now relies on that
  guard (behaviour is safe by construction today).
- Optional: cache the picker `->options()` query if the content library grows
  large (uncached per admin form render).

## Owner / deploy note

After this deploys, the homepage shows **no** featured/blog grids until the
list blocks are present on the home Page (in this repo's dev DB they already
are). On Laravel Cloud the owner adds them via Pages → home → Content Builder.
No migration.
