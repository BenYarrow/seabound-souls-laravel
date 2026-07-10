# Featured flag + standout (blogs & spot guides) — Design

**Date:** 2026-07-10
**Status:** Approved (pending spec review)

## Goal

Let the site owner deliberately choose, in-app, a single **featured** blog post and
a single **featured** spot guide, each of which is shown as a standout hero on
its listing page. Replace today's implicit "the newest item is the hero"
behaviour with an explicit, owner-controlled decision.

This is sub-project **A** of three (A: featured flag + standout — this doc;
B: list-content builder blocks; C: spot-guide quick-nav). B and C are separate
specs.

## Background (verified in code)

- **No `is_featured` column** exists on any table.
- **Blog index** (`resources/js/Pages/Blog/Index.tsx:42`) currently derives its
  hero as `const [featured, ...rest] = blogs.data` — i.e. the first post of the
  *current page*. `BlogController@index` just paginates published posts
  newest-first (12/page); it passes no explicit featured item.
- **Destinations page** (`resources/js/Pages/Destinations/Index.tsx`) has **no
  hero** — its layout is: masthead → editorial intro → map → continent-grouped
  grids → weather. `DestinationController@index` passes `spotGuides` grouped by
  continent client-side.
- **Homepage** shows 6-latest guides + 3-latest blogs via `FeaturedGrid`
  (unchanged by this work — out of scope here).
- Both models expose a `published()` scope and are edited via Filament
  resources with a General tab (`BlogResource`, `SpotGuideResource`).

## Decisions

- **`is_featured` boolean** (default `false`) on `blogs` and `spot_guides`.
- **Single featured per type, enforced at the model layer.** Saving a row with
  `is_featured = true` clears `is_featured` on every *other* row of that model.
  "Featured" is a single global pick per type.
- **No fallback.** Featured is *only* ever the flagged item. If nothing is
  flagged, the listing shows **no hero** — a hero is always a deliberate choice.
  (This removes the current "newest post is the hero" behaviour on the blog
  index entirely.)
- **Placement:** featured blog → the blog index hero (as today, but flag-driven);
  featured spot guide → a **new** hero on the `/destinations` page, between the
  editorial intro and the map, mirroring the blog hero.
- **Grid behaviour differs by page, deliberately:**
  - Blog index grid **excludes** the featured post (its list is chronological;
    the hero replaces its slot). Hero shows on **page 1 only**.
  - Destinations continent grids stay the **complete directory** — the featured
    guide still appears in its continent. The hero is a spotlight, not a filter.
- **Public hero still requires `published`.** The featured pick is global, but
  the controllers query `published()->where('is_featured', true)`, so an
  unpublished featured item yields `null` (no hero) rather than leaking a draft.
- **Shared `FeaturedHero` component** used by both the blog index and the new
  destinations hero, so both stand out identically (DRY). The blog hero is
  refactored onto it; verified visually unchanged.

Rejected: fallback-to-latest (user wants featured to be an explicit decision);
enforcing single-featured only in Filament (model-level is more robust).

## Scope & Components

### 1. Migration
`database/migrations/xxxx_add_is_featured_to_blogs_and_spot_guides.php` — add
`boolean('is_featured')->default(false)` to both `blogs` and `spot_guides`. No
index (tables are small). Model `$fillable` / casts updated to include
`is_featured` (cast `boolean`). Factories default `is_featured => false`.

### 2. Single-featured enforcement (models)
On `Blog` and `SpotGuide`, a booted `saved` hook: when the saved row has
`is_featured === true`, run
`static::where('id', '!=', $model->id)->where('is_featured', true)->update(['is_featured' => false])`.
Query-builder `update()` bypasses model events, so no recursion. A shared trait
(`App\Models\Concerns\HasSingleFeatured`) avoids duplicating the hook across the
two models.

### 3. Filament
- `Toggle::make('is_featured')->label('Featured')` on the General tab of
  `BlogResource` and `SpotGuideResource`.
- `ToggleColumn::make('is_featured')->label('Featured')` in each resource's
  table (quick feature/un-feature from the list; still routes through the model
  save hook, so single-featured holds).

### 4. Blog index
- `BlogController@index`: query
  `$featured = Blog::published()->where('is_featured', true)->with('thumbnailMedia')->first()`
  (may be `null`), projected to the card shape. Exclude it from the paginated
  grid: `Blog::published()->when($featured, fn ($q) => $q->whereKeyNot($featured->id))->latest('published_at')->paginate(12)`.
  Pass `featured` as its own prop.
- `Blog/Index.tsx`: drop `const [featured, ...rest] = blogs.data`. Render
  `<FeaturedHero>` from the `featured` prop **only when** `featured` is set
  **and** `blogs.meta.current_page === 1`; render `blogs.data` as the full grid.

### 5. Destinations index
- `DestinationController@index`: add
  `$featuredSpotGuide = SpotGuide::published()->where('is_featured', true)->with(['country', 'thumbnailMedia'])->first()`,
  projected (id, title, slug, country name, focal thumbnail); pass as
  `featuredSpotGuide` prop (may be `null`).
- `Destinations/Index.tsx`: render `<FeaturedHero>` between the editorial intro
  and the map when `featuredSpotGuide` is set. Continent grids unchanged.

### 6. Shared `FeaturedHero` component
`resources/js/Components/Common/FeaturedHero.tsx` — props: focal `image`,
`eyebrow`, `title`, `subtitle`, `href`. Uses `CoverImage`; styled to the site's
light editorial look (matches the existing blog hero). Consumed by both listing
pages.

## Error Handling / Edge Cases

- No featured item flagged → controller prop is `null` → no hero renders.
- Featured item unpublished → `published()` filter yields `null` → no hero.
- Featured blog on page ≥ 2 → hero suppressed (page-1-only), grid already
  excludes it, so it never appears.
- Featuring a second item → the first is auto-un-featured (single-featured hook).

## Testing (TDD)

PHPUnit (in-memory SQLite; media faked where needed):
- **Model** (`Blog`, `SpotGuide`): featuring a second row un-features the first;
  featuring via `ToggleColumn`-style update path also enforces it (save hook).
- **BlogController@index:** passes the flagged featured blog as `featured`;
  excludes it from `blogs.data`; `featured` is `null` when none flagged (no
  fallback) and when the only featured blog is unpublished.
- **DestinationController@index:** passes the flagged featured guide as
  `featuredSpotGuide`; `null` when none flagged / unpublished; the guide still
  appears in `spotGuides` (not excluded).
- Frontend `FeaturedHero` is presentational — no Vitest unit test; verify live
  on the blog index + `/destinations` at mobile/tablet/desktop, and confirm the
  refactored blog hero is visually unchanged.

## Out of Scope

- Homepage featured integration (the homepage keeps its 6-latest / 3-latest
  grids; a featured standout there was considered and deferred).
- List-content builder blocks (sub-project B) and spot-guide quick-nav (C).
- Multiple-featured / "featured" filter UI.

## Delivery

Branch `feat/featured-flag-standout`; TDD; folded reconcile before merge; PR.
Migration is additive (new nullable-defaulted column) — safe on Cloud; run
`php artisan migrate` on deploy.
