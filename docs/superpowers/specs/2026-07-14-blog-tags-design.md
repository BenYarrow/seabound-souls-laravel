# Blog Tags — Design Spec

**Date:** 2026-07-14
**Status:** Approved (design), pending implementation plan
**Branch:** `feature/blog-tags`

## Problem

The blog has no way to categorise posts. We want to tag blog posts so readers can
browse by topic and — the primary driver — so each topic becomes a **crawlable,
rankable URL** for SEO. A client-side filter creates no new indexable pages; a
dedicated tag page per topic does, and cross-links posts internally.

## Decisions (from brainstorm)

1. **Blogs only.** Tags apply to `Blog` only — not spot guides or pages. Simple
   `tags` table + `blog_tag` pivot, not a polymorphic `taggables` table. (If spot-
   guide tagging is ever wanted, that's a small additive change later.)
2. **Custom `Tag` model, not `spatie/laravel-tags`.** The Spatie package is
   polymorphic (the case we ruled out), encourages on-the-fly tag creation (tag
   sprawl, the opposite of a curated set), and needs a separate Filament plugin. A
   small dedicated model fits the "add a tag, then assign it" curated-vocabulary
   flow with less machinery.
3. **Crawlable tag pages are the filter.** No separate JS filter. A tag chip is a
   link to `/blog/tags/{slug}`. One mechanism serves both UX and SEO.
4. **Tags surface on both** the blog index (a tag bar) and individual posts (chips).
   Maximises internal linking, which is what makes the tag pages rank.
5. **Rich tag pages.** Each tag has an optional intro `description` plus optional
   `seo_title` / `seo_description`, so a tag page is a genuine topic hub, not thin
   content. Blank fields fall back to auto-generated defaults.

Tags live only on blogs, and blogs are owner-only (contributors author spot
guides, not blog posts), so there are **no contributor-permission questions** —
tags are entirely the owner's curated vocabulary.

## Data model

### `tags` table (new migration)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Display name, e.g. "Wave Sailing" |
| `slug` | string | URL slug, auto-filled from name; unique among non-trashed rows |
| `description` | text, nullable | Intro paragraph rendered on the tag page |
| `seo_title` | string, nullable | Overrides auto-generated `<title>` |
| `seo_description` | string, nullable | Overrides auto-generated meta description |
| `sort_order` | integer, default 0 | Orders the tag bar |
| `timestamps` | | |
| `deleted_at` | | soft deletes |

- **Slug uniqueness:** a **partial unique index** `WHERE deleted_at IS NULL`
  (Postgres), matching the SpotGuide soft-delete-slug fix (2026-07-13) so a
  soft-deleted tag's slug can be reused. Validation is scoped to non-trashed rows.

### `blog_tag` pivot table (new migration)
| Column | Type | Notes |
|---|---|---|
| `blog_id` | FK → blogs, cascade on delete | |
| `tag_id` | FK → tags, cascade on delete | |
| unique(`blog_id`, `tag_id`) | | no duplicate assignments |

### `Tag` model
- `use HasFactory, SoftDeletes;`
- `belongsToMany(Blog::class)`.
- `publishedBlogs()` — the relation constrained to `is_published = true`.
- `scopeWithPublishedPosts($query)` — tags that have ≥1 published (non-trashed)
  blog. **Used by every public surface** (tag bar, chips, tag page existence,
  sitemap) so empty / draft-only tags never appear.
- Slug auto-fill from `name` on save when slug is blank (mirror the existing
  slug convention used elsewhere in the app).

### `Blog` model
- Add `tags(): BelongsToMany` → `belongsToMany(Tag::class)`.

## Admin (Filament)

### New `TagResource` (`/admin/blog-tags`)
- Label "Blog Tags" (nav + breadcrumbs), owner-only via a new `TagPolicy`
  (same owner-gate pattern as the other owner-only resources).
- Form: `name`, `slug` (auto from name, editable), `sort_order`, and a collapsed
  "SEO & intro" section for `description` / `seo_title` / `seo_description`.
- Table: name, slug, post count, sort order; reorderable by `sort_order`.
- Standard create/edit/delete (soft-delete).

### Blog form
- A "Tags" section with a `CheckboxList` (or multi-select) of existing tags —
  assign from the curated list. **No on-the-fly tag creation** (curated vocabulary).
- Persists to the `blog_tag` pivot.

## Public frontend

### Route + controller + page
- `GET /blog/tags/{slug}` → `TagController@show` → renders `Blog/Tag.tsx`.
  - **Route ordering:** `/blog/tags/{slug}` (two segments) can't collide with the
    existing `/blog/{slug}` show route (one segment), so order between them doesn't
    matter. Declare it among the other `/blog/*` routes, which already sit before
    the catch-all `/{slug}` — so it's never swallowed by the catch-all.
  - Resolve the tag by slug among **tags with published posts**; `404` on unknown
    slug or a tag with no published posts (avoids thin/empty indexable pages).
  - Payload: tag `name`, `description`, and a paginated list (12/page, newest
    first by `published_at`) of the tag's published posts, projected to the same
    card shape the blog index uses (`id`, `title`, `slug`, `published_at`,
    `thumbnail` via `imagePayload()`, `seo_description`).
  - `meta`: `seo_title` / `seo_description` when set, else auto-generated
    (`"Posts tagged {name} — Seabound Souls"` / a sensible default description).

### `Blog/Tag.tsx`
- Layout mirrors `Blog/Index.tsx`: `StaticMasthead` with the tag name as title,
  the tag's `description` as intro copy (when present), then the paginated post
  grid. Reuses existing card components. No featured hero.

### Blog index (`/blog`, `BlogController@index`)
- Add a **tag bar** near the top: all `withPublishedPosts` tags ordered by
  `sort_order`, each a link to its tag page. Add `tags` to the Inertia payload.

### Blog post (`/blog/{slug}`, `BlogController@show`)
- Add **tag chips** under the post meta, each linking to its tag page. Add the
  post's tags (name + slug) to the payload.

- New UI (tag bar, chips, tag page) follows the app's existing styling and the
  semantic-token approach so it works in dark mode. Verify both themes and all
  breakpoints before merge.

## SEO

- Per-tag meta as above (overrides → auto-generated fallback).
- **Sitemap:** `App\Support\SitemapBuilder::build()` gains a block adding every
  `withPublishedPosts` tag URL (`/blog/tags/{slug}`), priority ~0.6, `lastmod`
  from that tag's newest published post's `updated_at`. Empty / draft-only tags
  are excluded (they 404, so must not be in the sitemap). This is what actually
  gets the pages crawled.

## Testing (TDD — write tests first)

- **Model:** tag↔blog relation; slug auto-generates from name; `withPublishedPosts`
  scope includes a tag with a published post, excludes a draft-only tag and a
  tag whose only post is soft-deleted.
- **`TagController@show`:** renders only published posts; `404`s on unknown slug
  and on a tag with no published posts; pagination works (13th post → page 2).
- **Blog index:** `tags` payload lists only `withPublishedPosts` tags in
  `sort_order`.
- **Blog show:** payload includes the post's tags (name + slug).
- **Sitemap:** includes a qualifying tag URL; excludes an empty / draft-only tag.
- **Filament:** assigning tags on the blog form persists to the pivot;
  `TagResource` is owner-only (a contributor is blocked) — smoke level.

All mocks/no-network per project convention; suite runs on SQLite but the
partial-unique-index migration is Postgres-specific — smoke-test the migration
and slug-reuse on the real Postgres dev DB (SQLite/Postgres divergence lesson).

## Out of scope (deliberate — all additive later)

- No client-side JS filter (tag pages are the filter).
- No tagging of spot guides or pages.
- No Scout/search integration for tags.
- No featured hero on tag pages.
- No tag-based "related posts" block on individual posts (chips only for now).

## Follow-ups after merge

- If tag pages prove valuable, consider a "related posts by shared tag" block on
  the post page.
- On custom-domain launch the sitemap URLs adapt automatically (dynamic route);
  no tag-specific deploy step.

---

## Addendum (2026-07-14, approved mid-implementation) — tag hub, per-tag images, gradient fallback

After the core feature was built and reviewed, three additions were approved and folded into the same branch:

1. **`/blog/tags` hub page** — a browse-all-topics page and crawlable SEO hub listing every tag with published posts as cards (card → its tag page). Routed before `/blog/{slug}` so the literal path isn't read as a blog slug; added to the sitemap; always resolves (empty state) so the bare path never 404s. (A *deleted* tag's own page still correctly 404s — the hub does not change that.)
2. **Optional images per tag** — `thumbnail_media_id` (hub card) and `static_masthead_media_id` (tag-page hero), nullable FKs to `media_library`, set via admin MediaPickers.
3. **Designed gradient fallback** — a shared `TagMasthead` component renders the real masthead image when present, else an on-brand deep ocean-teal gradient hero (layered radial glows + subtle wave SVG, pure CSS/SVG). The hub cards get a matching mini-gradient when a tag has no thumbnail. Applies to the hub and individual tag pages.

> **Superseded by a follow-on change (same day):** `TagMasthead` was folded into `StaticMasthead` so the gradient became the **site-wide** default masthead for any image-less page (not just tag pages), and `TagMasthead` was removed. Plain gradient fallbacks render at ~55vh; photos + the SpotGuide centred layout stay full-height. See [../../history/2026-07-14-blog-tags.md](../../history/2026-07-14-blog-tags.md).
