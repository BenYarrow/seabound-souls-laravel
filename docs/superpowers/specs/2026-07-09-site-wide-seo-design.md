# Site-wide editable SEO (title / description / keywords / OG image) — Design

**Date:** 2026-07-09
**Status:** Approved (pending spec review)

## Goal

Give every public page fully in-app-editable SEO — title, description,
keywords, and OG image — and fix two live bugs: keywords never render, and the
listing pages' titles double the `| Seabound Souls` suffix.

## Background

Current state (all verified in code):

- **Layout** (`resources/js/Layouts/Layout.tsx`) renders `<title>`,
  `<meta name="description">`, `<meta property="og:image">` — but **no
  `<meta name="keywords">`**.
- **`app.tsx`** globally appends `| Seabound Souls` to every title via Inertia's
  `title` callback.
- **Listing/system controllers** (`BlogController@index`, `DestinationController@index`,
  `ContactController@index`, `SearchController@index`) hardcode their meta,
  including a literal `… | Seabound Souls` in the title → **doubled suffix**
  ("Blog | Seabound Souls | Seabound Souls"). They read no SEO fields.
- **Detail controllers** pass SEO from per-record `seo_*` fields, but
  inconsistently: `SpotGuide@show` passes title/description/keywords/og;
  `Blog@show` and `Page@show` pass title/description/og but **no keywords**;
  `Homepage` passes title/description but **no keywords, no og**.
- **`Page` model** already has `seo_title`, `seo_description`, `seo_keywords`
  (cast `array`), `og_image_media_id`; **`PageResource`** already edits all four
  (SEO tab: `TextInput` title, `Textarea` description, `TagsInput` keywords,
  `MediaPicker` og). Blog & SpotGuide resources have the same SEO tab.
- **Page records already exist** for `home`, `search`, `blog`, `destinations`
  (+ `about-us`); `HomepageController` already reads its SEO from the `home`
  Page. Only a **`contact`** Page record is missing.

## Decisions

- **Reuse the existing `Page` records** as the SEO source for the listing/system
  pages — the pattern `HomepageController` already uses. No new model, no new
  Filament resource; SEO is edited in the existing **Pages** admin (SEO tab).
  Rejected: a dedicated `SeoSetting` model + resource — it would duplicate what
  `Page` already provides.
- **Keywords** are passed from controllers as the `seo_keywords` **array**; the
  Layout renders them comma-joined into one `<meta name="keywords">`.
- **Titles carry no manual suffix** anywhere — the global `app.tsx` callback is
  the single source of the `| Seabound Souls` suffix. Fixes the doubling.
- Every page provides a **fallback** for each SEO field, so meta is never empty
  even before the owner fills the admin fields.

## Scope & Components

### 1. `resources/js/Layouts/Layout.tsx`
Add a `keywords?: string[]` prop and render, inside `<Head>`:
```tsx
{keywords && keywords.length > 0 && (
    <meta name="keywords" content={keywords.join(', ')} />
)}
```
(Keeps the existing conditional `title` / `description` / `og:image` tags.)

### 2. Listing/system controllers read their Page's SEO
`BlogController@index`, `DestinationController@index`, `ContactController@index`,
`SearchController@index`: look up the Page by its fixed slug (`blog`,
`destinations`, `contact`, `search`), and build meta from it with fallbacks:
```php
$page = Page::where('slug', 'blog')->first();
'meta' => [
    'title'       => $page?->seo_title ?: 'Blog',              // no manual suffix
    'description' => $page?->seo_description ?: 'Windsurfing tips, guides and destination insights.',
    'keywords'    => $page?->seo_keywords ?? [],
    'og_image'    => $page?->ogImageMedia?->getUrl() ?: '',
],
```
(Each page keeps its own sensible fallback string. `SearchController` keeps its
dynamic `Search: {query}` title when a query is present, still suffix-free.)

### 3. Detail controllers — complete the meta
- `BlogController@show`, `PageController@show`: add
  `'keywords' => $model->seo_keywords ?? []` (they already pass og).
- `HomepageController`: add `'keywords' => $page?->seo_keywords ?? []` and
  `'og_image' => $page?->ogImageMedia?->getUrl() ?: ''`, and change the fallback
  title from `'Seabound Souls - Windsurfing Destination Guide'` to
  `'Windsurfing Destination Guide'` so the global suffix yields
  `Windsurfing Destination Guide | Seabound Souls` (brand once, not
  `Seabound Souls … | Seabound Souls`). Fallback description unchanged.
- `SpotGuideController@show`: already complete; unchanged.

### 4. Inertia pages (`.tsx`) — thread keywords + og everywhere
Pass `keywords={meta.keywords}` (and `ogImage={meta.og_image}` where missing)
into `<Layout>` on: `Blog/Index`, `Blog/Show`, `Destinations/Index`, `Contact`,
`Search`, `Homepage`, `Page/Show`, `SpotGuide/Show`. (`title` / `description`
are already threaded.)

### 5. Seed the `contact` Page
Add a `contact` Page (template `standard`, published) via a seeder/data step so
the admin has a row to edit for contact-page SEO. Its content isn't rendered
(ContactController owns the view) — it's an SEO holder, same as the other
system Pages.

## Error Handling / Edge Cases

- Missing Page record or empty SEO field → the per-page fallback string/array is
  used, so meta is always populated.
- Empty `seo_keywords` (`[]`) → no `<meta name="keywords">` rendered (the Layout
  guards on length). No empty tag.
- `og_image` empty → no `og:image` tag (existing Layout guard).

## Testing (TDD)

Controller/feature tests (PHPUnit, in-memory SQLite; media/URL faked where
needed):
- Each listing controller's `meta.title` has **no** `| Seabound Souls` suffix
  (the global JS callback adds it), pulls `seo_*` from the Page record when set,
  and falls back when the Page is absent/blank.
- `meta.keywords` is passed as an array from every controller that should carry
  it; `Blog@show` / `Page@show` / `Homepage` now include it.
- A `Page` with `seo_keywords` set surfaces them in the controller's `meta`.
- Layout render: `<meta name="keywords">` appears when keywords are passed and is
  absent when empty (a lightweight assertion; the controller tests carry the
  bulk).

## Out of Scope

- A separate SEO settings model/UI (reusing `Page` instead).
- Per-page canonical URLs, `og:type`, Twitter cards, structured data — could
  follow later; this ships the four fields the user asked for.
- Rendering the system Pages' `content_blocks` (they remain SEO holders).

## Delivery

Branch `feat/site-wide-seo`; TDD; folded reconcile before merge; PR. After merge,
the owner fills SEO for each page in the Pages admin (a `contact` Page now
exists to edit). No env/infra changes.
