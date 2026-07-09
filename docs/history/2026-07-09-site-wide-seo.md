---
title: Site-wide editable SEO (title / description / keywords / OG image)
tags: [seo, inertia, filament, meta-tags]
status: stable
completed: 2026-07-09
commits: [bc85a80, f82249d, b13e527, 1a6edc3, ff5df6f, f82c006]
pr: 21
---

# Site-wide editable SEO

## What shipped

Every public page's SEO — title, description, keywords, OG image — is now
editable in-app, and two live bugs are fixed.

- **Listing/system pages read SEO from their `Page` record.** Blog index,
  Destinations index, Contact, and Search build their meta from the matching
  `Page` (slugs `blog`/`destinations`/`contact`/`search`) — the pattern the
  homepage already used — with sensible hardcoded fallbacks. A migration seeds a
  `contact` Page (the only one missing) so it's editable in the Pages admin.
- **Detail pages completed.** Blog show + Page show now emit `keywords`; the
  homepage emits `keywords` + `og_image`. (SpotGuide show was already complete.)
- **Keywords now render.** `Layout.tsx` gained a `<meta name="keywords">`
  (guarded on non-empty, comma-joined) and all 8 pages thread `keywords` +
  `ogImage` into `<Layout>`. Previously keywords never reached the HTML.
- **Doubled title suffix fixed.** The listing controllers hardcoded
  `… | Seabound Souls` in their titles, which the global `app.tsx` Inertia title
  callback then duplicated ("Blog | Seabound Souls | Seabound Souls"). All
  controller titles are now bare; the global callback is the single source of
  the ` | Seabound Souls` suffix.

Editing surface: the existing **Pages** admin (SEO tab already has title,
description, keywords `TagsInput`, and OG-image `MediaPicker`).

## Findings worth keeping

- **Reused `Page` records instead of a new settings model.** The system Pages
  (`home`/`blog`/`destinations`/`search`) already existed with the full `seo_*`
  field set and a distinct `template`; the listing controllers already fetched
  their Page for the masthead. So this was mostly *wiring*, not new
  infrastructure — no `SeoSetting` model/resource needed.
- **Route precedence makes the system Pages safe SEO holders.** The explicit
  `/blog`, `/destinations`, `/search`, `/contact` routes are registered before
  the catch-all `/{slug}` (PageController), so those Page records can never be
  rendered as content — they exist purely to hold SEO. Verified in the final
  review.
- **`?:` (elvis), not `??`.** Filament may store a cleared field as `''` rather
  than `null`; `??` only falls back on `null`, so a blank SEO field would leak an
  empty tag. All fallbacks use `?:` so empty strings fall back too (the homepage
  description was the last `??` holdout — fixed in f82c006).
- **Inertia sets meta client-side.** There is no SSR here, so the keywords/title
  tags appear in the live DOM (inspect element), not in view-source — which is
  why the original "can't see it in the HTML" observation was partly a
  view-source vs rendered-DOM distinction, on top of the genuine missing tag.
- **No JS test runner** in the project, so the frontend threading is verified by
  `npm run build` + the tested controller `meta` props; the React `Layout`
  change is a trivial guarded conditional.

## Test plan

TDD backend, in-memory SQLite. New `tests/Feature/SeoMetaTest.php` asserts, per
route, that `meta.title` has no manual brand suffix, reads `seo_*` from the Page
record when set and falls back when blank, and carries `keywords` (array) +
`og_image`. Suite **105 → 107 passing**; `npm run build` green.

Spec: `docs/superpowers/specs/2026-07-09-site-wide-seo-design.md`.
Plan: `docs/superpowers/plans/2026-07-09-site-wide-seo.md`.

## Follow-ups / residual

- On the next Cloud deploy the `contact` Page migration runs, creating the
  editable contact-SEO row in production.
- `Blog/Show`, `Page/Show`, `SpotGuide/Show` still type `meta` as `any` — a later
  tidy could give them the explicit `{ title, description, keywords?, og_image? }`
  shape the other pages use.
- Not included (could follow): canonical URLs, `og:type`, Twitter cards,
  structured data.
