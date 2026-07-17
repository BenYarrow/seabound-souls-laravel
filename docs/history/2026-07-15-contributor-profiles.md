---
title: Contributor profiles + About roll-up (sub-project 2)
tags: [contributor-workflow, profiles, filament, frontend, seo, masthead]
status: stable
completed: 2026-07-15
commits: [d618060, 1068d18, 62e8019, 5b2958d, 36ffc33, 1ca6767, 69996e6, d20e967, d8375ee, bedaf51, 8d04d66, 8cef7a3, c9193fb, 9c86cb7, 9d38062, 9c0108c, 4db3d22, 6b13019]
pr: 36
---

# Contributor profiles + About roll-up

Sub-project 2 of the contributor workflow (sub-project 1 shipped 2026-07-13). Gives invited contributors a public presence, earned by contributing.

## What shipped

- **Profile data on `users`** (nullable columns, contributors only): `slug` (from first+last, collision-suffixed via `App\Support\SlugGenerator`, generated once and stable), `profile_image_media_id`, `static_masthead_media_id`, `profile_blocks` (JSON content builder), `socials` (JSON map of instagram/youtube/tiktok/facebook/x/website). `User` gained `profileImageMedia()`/`staticMastheadMedia()`, `publishedAuthoredGuides()`, `hasPublicProfile()`, `scopeWithPublicProfile()`.
- **Derived visibility, no manual flag:** a profile is public **iff** the user is a contributor with ≥1 published guide (reuses sub-project 1's byline gate). Owners never get a public profile. Public presence is earned — the My Profile editor states this.
- **Public profile page** `GET /contributors/{slug}` (`ContributorController@show`, named `contributors.show`): masthead (image or gradient fallback) + portrait + social icon row + their content-builder story + a grid of their published guides. 404s on unknown slug, owner, or a contributor without a published guide.
- **Clickable attribution:** `SpotGuide::authorPayload()` gained `slug` + `image`; the spot-guide page shows a prominent **author card** (portrait + "Written by" + name + arrow, on a dark teal band with a flowing wave animation, fading into the content) linking to the profile. Destinations cards show the byline as **plain text** (the card is already a link — a nested anchor is invalid + dead).
- **About page:** `about-us` → `about` (data migration + 301 redirect + nav), plus a **contributor roll-up content block** (`contributor_roll_up`) the owner drops into the About page — auto-lists contributors with a public profile (portrait + name + guide count) linking to their profiles.
- **Self-service editing:** a Filament **My Profile** page (record always `auth()->user()` — no cross-user write path) + owner editing via `ContributorResource`, sharing one `ContributorProfileForm::schema()`.
- **Shared `DestinationCard`** component extracted from the destinations inline card and reused on the profile (byline hidden there).

## Findings worth keeping

- **`ensure_pages_exist` (Inertia testing):** this repo has no `config/inertia.php`, so `assertInertia()->component('X')` fails if `X.tsx` doesn't exist yet. Backend-first tests pass the second arg `false` to assert the component name without the file.
- **Nested anchors (Critical, caught in review):** the destinations card is a full-card `<Link>`; an inner byline `<Link>` is invalid HTML and functionally dead (Inertia click bubbles to the outer card link — verified in the package source). Fix: plain-text byline on cards; clickable byline only where standalone (the spot-guide page).
- **Lazy slug generation → backfill (Important, caught in review):** slugs generate on save, so pre-existing (sub-project-1) contributors had `slug = null`; `hasPublicProfile()` is true for them, so the roll-up would render a broken `/contributors/null` card (the byline guarded null, the roll-up didn't). Fixed with a backfill migration (`2026_07_15_100020`) + a `whereNotNull('slug')` server guard + a client filter.
- **Author card fallback (Important, caught in review):** gating the card on `contributor && slug` misattributed a slugless contributor's guide to the house brand. A contributor now always gets the card — linked when they have a slug, plain text otherwise.
- **N+1:** `authorPayload().image` reads `author.profileImageMedia`; both `DestinationController` and `SpotGuideController` eager-load `author.profileImageMedia`.
- **Herd was unresponsive all session** — verified functionally via `php artisan serve` + Inertia `data-page` decode. Pixel-level look was owner-reviewed live (several visual iterations on the byline band — see below).
- **Wave-flow animation:** a `wave-flow` keyframe (`app.css`) translates a double-width, two-period SVG by ‑50% for a seamless loop; `prefers-reduced-motion` disables it.

## Design iteration (owner-reviewed live)

The author band went through several rounds on Ben's feedback: small italic byline (too weak) → prominent white card on cream (liked the card, "too much whitespace") → moved below the quick-nav → distinct-background attempts (cream = "floating"; pale-teal = "too vulgar"; plain white = "too basic") → **dark teal gradient band with a flowing wave animation, fading into the cream content below** (approved). Profile-page polish: removed the masthead/portrait overlap, dropped the duplicate name under the portrait, "Guides by {first name}", and matched the guides grid's container to the destinations page.

## Test plan

18 new tests across `ContributorProfileModelTest`, `ContributorProfilePageTest`, `AuthorPayloadSlugTest`, `AboutRenameTest`, `ContributorRollupBlockTest`, `MyProfileTest`. Suite **246 passing**; `npm run build` clean; migrations clean on the real Postgres dev DB. Reviewed per-task + whole-branch (Opus); Critical + two Important findings fixed and re-reviewed.

## Follow-ups (see `docs/TODO.md`)

- Owner (post-deploy): drop the "Contributor roll-up" block onto the About page on Cloud; the `about-us`→`about` rename runs via migration.
- Later, if wanted: switch `/contributors` → `/crew` (one route + a 301); a Livewire `fillForm->call('save')` test for the My Profile page; a per-slug 301 when retiring a contributor.
- Team feedback on the profile/byline design may prompt a revisit (Ben is sharing for review).
