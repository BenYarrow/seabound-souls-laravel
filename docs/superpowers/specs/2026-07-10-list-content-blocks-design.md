# List-content builder blocks (+ homepage → content-managed) — Design

**Date:** 2026-07-10
**Status:** Approved (pending spec review)

## Goal

Make the two content-builder blocks `list_content_blogs` and
`list_content_spot_guides` actually work — they're currently broken stubs that
crash Filament and render nothing — so the owner can hand-pick blogs / spot
guides into a curated grid with a "View all" link, on any page's content
builder. Then remove the homepage's hardcoded featured-spots / latest-blogs
grids so the homepage becomes content-managed via these blocks.

This is sub-project **B** of the featured/content brainstorm (A shipped in #25;
C — spot-guide quick-nav — is still to do). It also **folds in** a small change
carried over from #25: rank destinations by **gusts**, not wind.

## Background (verified in code)

- **The crash:** `ContentBuilderBlocks::blocks()` defines both blocks with
  `Select::make('customBlogEntries')->multiple()->relationship('', 'title')` —
  an **empty relationship name** → Filament's `RelationshipJoiner` gets `null`
  → `Argument #1 ($relationship) must be of type …Relation, null given`. They're
  also **not rendered**: `ContentBuilder.tsx` has no `case` for either type.
- **Content-block flow:** blocks live in JSON `content_blocks` on `Page` /
  `Blog` / `SpotGuide`. The `ResolvesContentBlockMedia` trait resolves media IDs
  → focal `imagePayload()` objects in one batched query, emitting `{key}_image`
  / `{key}_images`. It's called by **all four** content-rendering controllers
  (Homepage, Page@show, Blog@show, SpotGuide@show) — so extending it needs no
  controller edits.
- **`FeaturedGrid`** (the homepage's listing component) takes
  `{title, entries:[{id,title,slug,thumbnail,subtitle?}], linkHref, linkLabel,
  linkScreenReaderLabel?, backgroundColour?, buildHref}` and returns `null` when
  `entries` is empty.
- **Homepage:** `HomepageController@index` computes `featuredSpotGuides` (6
  latest) + `recentBlogs` (3 latest); `Homepage.tsx` renders two hardcoded
  `<FeaturedGrid>` sections from them. It already renders the home Page's
  `content_blocks` via `<ContentBuilder>`. The home Page (template `homepage`)
  is edited in the **Pages** admin, whose content builder uses the same
  `ContentBuilderBlocks::blocks()` — so list blocks are addable there.
- **Destinations ordering (from #25):** ranks by `kts_wind` for the current
  year+month; the intro note reads "Ordered by wind…". Three
  `DestinationControllerTest` cases assert the wind ranking.

## Decisions

### Entry picker (Filament)
Replace the broken relationship Select with a plain **options** Select storing an
array of IDs into the block JSON:
```php
Select::make('customBlogEntries')
    ->label('Blog Posts')
    ->multiple()
    ->options(fn () => \App\Models\Blog::orderBy('title')->pluck('title', 'id'))
    ->searchable()
    ->preload(),
```
and the spot-guides equivalent against `SpotGuide`. **Picker lists all** records
(drafts included) so an item can be lined up before it's live; the public render
shows only the currently-published subset (see resolution). Selection order is
the display order.

Every text/label the block shows is editable:
- `blockTitle` (existing) — the section **heading** (e.g. "Latest Blogs").
- `viewAllUrl` — the "view all" link **target** (`TextInput`, optional).
- `viewAllLabel` — the "view all" link **text** (`TextInput`, optional, default
  `'View all'`).

Keep the existing background-colour select.

### Server-side resolution (extend `ResolvesContentBlockMedia`)
After the media pass, resolve picked IDs in one batched query per type,
**published-only, in authored order** (drafts dropped):
- `list_content_blogs` → `data.customBlogEntries` (int[]) →
  `data.customBlogEntries_resolved = [{id, title, slug, thumbnail}]`
  (thumbnail = `thumbnailMedia?->imagePayload()`).
- `list_content_spot_guides` → `data.customSpotGuideEntries` (int[]) →
  `data.customSpotGuideEntries_resolved = [{id, title, slug, thumbnail, subtitle}]`
  (subtitle = country name).

Order is preserved by mapping over the stored ID array and keeping only IDs
present in the published result set. Naming mirrors the existing `_image` /
`_images` convention (`_resolved`). Zero controller changes (all four already
call the trait).

### Frontend render (`ContentBuilder.tsx`)
Add two `case`s, each rendering `FeaturedGrid`:
```tsx
case 'list_content_blogs':
  return <FeaturedGrid key={index}
    title={block.data.blockTitle || 'From the blog'}
    entries={block.data.customBlogEntries_resolved ?? []}
    linkHref={block.data.viewAllUrl || '/blog'}
    linkLabel={block.data.viewAllLabel || 'View all'}
    backgroundColour={block.data.backgroundColour}
    buildHref={(entry) => `/blog/${entry.slug}`} />
case 'list_content_spot_guides':
  return <FeaturedGrid key={index}
    title={block.data.blockTitle || 'Destinations'}
    entries={block.data.customSpotGuideEntries_resolved ?? []}
    linkHref={block.data.viewAllUrl || '/destinations'}
    linkLabel={block.data.viewAllLabel || 'View all'}
    backgroundColour={block.data.backgroundColour}
    buildHref={(entry) => `/destinations/${entry.slug}`} />
```
`FeaturedGrid` returns `null` on empty entries, so an all-draft (or empty) block
renders nothing.

### Homepage → content-managed
- `HomepageController@index`: remove the `$featuredSpotGuides` and `$recentBlogs`
  queries and their Inertia props. Keep the masthead-slider + infographic-stats
  logic and the `content_blocks` rendering.
- `Homepage.tsx`: remove the two hardcoded `<FeaturedGrid>` sections and the
  now-unused `featuredSpotGuides` / `recentBlogs` props + `FeaturedGrid` import.
  The page keeps its masthead + `<ContentBuilder>`.
- Consequence (owner, post-deploy): the homepage shows no featured/blog grids
  until the owner adds the list blocks to the **home Page** content builder. The
  block components are the same ones now available there.

### Gusts fold-in (bundled from #25)
- `DestinationController@index`: rank by `kts_gust` (was `kts_wind`) for the
  current year+month; no-data-last + title tiebreak unchanged.
- `Destinations/Index.tsx`: the note reads "Ordered by **gusts** for {Month
  Year} — …".
- The three ordering tests set `kts_gust` instead of `kts_wind`.

## Error Handling / Edge Cases

- Block with no picks / all-draft picks → resolved array empty → `FeaturedGrid`
  renders nothing (no error).
- A picked entry later unpublished → dropped from the resolved set automatically.
- Stored IDs referencing deleted records → skipped (not in the published query).
- `viewAllUrl` blank → falls back to `/blog` or `/destinations`.

## Testing (TDD)

- **Filament (crash fixed):** a `Livewire` test saving a `Page` whose
  `content_blocks` contains a `list_content_spot_guides` block with a picked
  guide → `assertHasNoFormErrors()` + the block persists the selected ID.
  (Before: the empty-relationship Select threw on form render.) A blog-block
  case likewise.
- **Resolution (via `PageController@show`):** a published Page with a
  `list_content_spot_guides` block referencing `[publishedGuide, draftGuide]` →
  the Inertia `page.content_blocks[n].data.customSpotGuideEntries_resolved`
  contains **only** the published guide, in authored order, with `slug` +
  focal `thumbnail` + `subtitle`. Same shape test for blogs. Add a case: order
  is preserved (pick B then A → resolved is [B, A]).
- **Gusts ordering:** update the three `DestinationControllerTest` cases to set
  `kts_gust`; assert windiest(gustiest)-first, no-data last, other-period
  ignored.
- **Homepage:** remove the two now-obsolete assertions
  (`…six_published_spot_guides`, `…three_recent_published_blogs`); keep the
  no-home-page and infographic-stats tests. Optionally assert the props are
  absent.
- Frontend rendering of the blocks: verified in the live preview by adding a
  real block to a page and viewing it (no unit test — presentational).

## Out of Scope

- Sub-project C (spot-guide quick-nav).
- Re-creating the old homepage sections as blocks (owner content task,
  post-deploy).
- Pagination / "load more" inside a list block (a curated fixed set).

## Delivery

Branch `feat/list-content-blocks`; TDD; subagent-driven build; folded reconcile
before merge; PR. No migration. The gusts change touches the (merged) #25
ordering — bundled here per the owner's call.
