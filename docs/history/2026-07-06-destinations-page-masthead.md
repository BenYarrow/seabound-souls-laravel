---
title: Content-manageable destinations masthead
tags: [destinations, page, masthead, admin]
status: stable
completed: 2026-07-06
commits: [68d2ed6]
pr: 12
---

# Content-manageable destinations masthead

The `/destinations` masthead was the odd index out: blog/search/home each pull their masthead from a dedicated `Page` record (slug `blog`/`search`/`home`), but destinations improvised — the front end just used the first spot guide's thumbnail, with nothing editable in the admin.

## What shipped
- `DestinationController@index` now looks up a published `Page` with slug **`destinations`** and passes its static masthead as a focal-bearing `imagePayload()` object, mirroring the other index controllers.
- `Destinations/Index.tsx` prefers `static_masthead`, falling back to the first guide's thumbnail — so **behaviour is unchanged until an editor creates a "destinations" page**. The masthead is focal-aware via `<CoverImage>` like everywhere else.

## How to use it
Create a Page in the admin (`/admin/pages`) with slug `destinations`, publish it, and set its Static Masthead image (+ focal point). The explicit `/destinations` route takes precedence over the `{slug}` catch-all, so the page record is purely a content container — it's never rendered as a standalone page.

## Content builder hidden for this template
The destinations page's content builder would never render (the layout is bespoke — map + continent groups + charts — driven by `DestinationController`, which reads only the masthead). To avoid a misleading dead field, `PageResource` now **hides the Content Builder tab when `template = 'destinations'`** (the `template` Select is `->live()` to drive it). Masthead-only, as intended. *(Blog/search index pages have the same inert-builder situation and could be hidden the same way later — left as-is for now since only destinations was in scope.)*

## Test plan
`php artisan test` → 67 passed, 428 assertions. `DestinationControllerTest`: masthead from the destinations Page's media (with focal) when present; `static_masthead` null (→ front-end fallback) when absent. `PageResourceContentBuilderTest`: Content Builder hidden for the `destinations` template, shown for `standard`. Browser-confirmed `/destinations` still renders via the first-guide fallback with no page.
