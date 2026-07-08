---
title: Image focal points
tags: [media, images, focal-point, filament, frontend]
status: stable
completed: 2026-07-06
commits: [e0282a8, eaf1bfa, 5ff3b11, 75d1488, 69c6dd0]
pr: 11
---

# Image focal points

`object-cover` images centre-cropped by default, so full-height mastheads sliced the subject out of frame on mobile (the destinations sunset shot lost its windsurfers). Editors can now set a per-image focal point that every cropped display honours. Built brainstorm → spec → plan → subagent-driven (5 tasks, opus final review — clean).

## What shipped
- **Storage:** `focal_x`/`focal_y` on `media_library` (percent, default 50/50 = centre). Additive migration — existing images unchanged until a point is set.
- **One backend shape:** `MediaLibrary::imagePayload()` → `{url, alt, focal_x, focal_y}`. Every controller + the `ResolvesContentBlockMedia` trait emit it instead of bare URL strings. **`meta.og_image` stays a URL string** (feeds `<meta>`, not an `<img>`).
- **One front-end renderer:** `<CoverImage>` (tolerant of string | object | null) applies `object-position` from the focal point; all 17 `object-cover` sites render through it.
- **Admin set-UI:** click the image in the MediaPicker preview to drop the focal marker; an auth-gated `POST /admin/media/{media}/focal` persists it (validated 0–100). The preview mirrors the point live via `object-position`.

## Findings worth keeping
- **The tolerant `CoverImage` (string | FocalImage | null) is what kept the app working through the rollout** — components adopted it (Task 3, still fed strings → no visual change) *before* the payload flipped to objects (Task 4). No broken intermediate state.
- **Response cache masks data changes in dev.** `spatie/laravel-responsecache` served a stale (centre) payload after a focal update until `php artisan responsecache:clear` — worth remembering when verifying content/data changes locally.
- **Two `primary` scales:** the Filament admin theme uses `Color::Amber` (full `primary-50…950`), while the Inertia frontend's `primary` has only `lightest/lighter/DEFAULT/darker`. Admin blades and frontend components draw from different palettes.
- Verified end-to-end: setting focal 50/80 on the Karpathos masthead rendered `object-position: 50% 80%` and kept the windsurfer in frame on mobile; gallery images (unset) stayed centred.

## Test plan
`php artisan test` → 63 passed, 401 assertions (MediaLibrary cast/payload unit; controller feature tests asserting the focal-bearing shape; MediaPicker focal-set endpoint auth/validation/persist). `npm run build` clean. Browser-verified the masthead object-position.

## Follow-ups (see docs/TODO.md)
- `CoverImage` applies `loading="lazy"` to *all* covers incl. above-the-fold mastheads — add an `eager` prop for LCP-critical mastheads.
- Multi-select (gallery/slider) focal-set UI (currently single-select preview only; multi defaults to centre).
- `Card.tsx` is unused dead code (imageUrl still typed `string`) — prune or update opportunistically.
- Fire-and-forget focal `fetch()` has no failure feedback; focal route uses generic `auth` (folds into the "restrict Filament panel access" TODO).
