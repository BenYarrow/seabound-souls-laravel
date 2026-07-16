# Contributor Profiles + About Roll-up — Design Spec

**Date:** 2026-07-15
**Status:** Approved (design), pending implementation plan
**Branch:** `feature/contributor-profiles`
**Sub-project:** 2 of the contributor workflow (sub-project 1 shipped 2026-07-13; see `docs/history/2026-07-13-rider-contributor-workflow.md`).

## Problem

Sub-project 1 gave invited contributors the ability to author spot guides and added a text attribution byline ("By {contributor}" vs "Seabound Souls"), but the byline links nowhere and contributors have no public presence. This sub-project gives each contributor a public **profile page** (their story, photo, socials, and guides) that the byline links to, and turns the About page into "who we are + our contributors". A contributor's public presence is **earned by contributing** — it only goes live once they have a published guide.

## Decisions (from brainstorm)

1. **Full sub-project (individual profiles + a roll-up).** Each contributor gets a public profile page; the About page lists them.
2. **Extend the `users` table** (a handful of contributors — no separate `contributor_profiles` model). New nullable columns only; owners are unaffected.
3. **Profile content is our content builder.** Contributors build their profile body from the existing content blocks (same `content_blocks` mechanism + `ResolvesContentBlockMedia`), not a plain bio field.
4. **Derived visibility, no manual flag.** A profile is public **iff** the user is a contributor with ≥1 **published** spot guide — reusing the existing byline gate. No "publish my profile" toggle.
5. **Incentive framing.** The contributor's profile-editing screen states the profile goes live only after their first published guide — self-promotion earned by contributing.
6. **Slug from first + last name** (`jane-smith`), numeric suffix on collision (`jane-smith-2`), unique among non-trashed users.
7. **One profile image, used twice** — the roll-up card thumbnail *and* the profile-page portrait.
8. **Six socials:** Instagram, YouTube, TikTok, Facebook, X/Twitter, personal website. Only filled ones render, as a row of tappable brand icons.
9. **Self-service editing** — contributors edit their own profile in Filament (policy-scoped); the owner can edit any.
10. **URL: `/contributors/{slug}`** via a **named route** (`contributors.show`) so every link is generated from one place — a later switch to `/crew` is a one-line route change + a 301. Chosen now as the safe literal default; no public domain exists yet, so it can be changed pre-launch for free.

## Data model — extend `users` (new migration)

| Column | Type | Notes |
|---|---|---|
| `slug` | string, nullable | Public URL slug from `"{first} {last}"`; unique among non-trashed users (numeric suffix on collision). Only set for contributors. |
| `profile_image_media_id` | FK → `media_library`, nullable, `nullOnDelete` | Portrait: roll-up card thumbnail + profile-page portrait. |
| `static_masthead_media_id` | FK → `media_library`, nullable, `nullOnDelete` | Profile-page hero; null → the gradient `StaticMasthead` fallback. |
| `profile_blocks` | JSON, nullable | The profile-body content builder (existing block set). |
| `socials` | JSON, nullable | Map of `instagram`/`youtube`/`tiktok`/`facebook`/`x`/`website` → URL. Blank/absent keys don't render. |

- `users` uses no soft-deletes today; "unique among non-trashed" degrades to plain unique. Keep the slug generator collision-safe regardless.
- **`User` model:** add the columns to `$fillable`; `socials` and `profile_blocks` cast to `array`; add `profileImageMedia()` + `staticMastheadMedia()` `belongsTo` relations; a slug generator on save (from first+last, only for contributors, collision-suffixed, skip if unchanged); a `hasPublicProfile(): bool` (contributor && has ≥1 published guide) reusing the existing gate; a `scopeWithPublishedGuides()` for the roll-up; `publishedAuthoredGuides()` (authored guides filtered to published).

## Editing (Filament)

### Self-service "My Profile" page
- A custom panel page (`Filament\Pages\Page`) at e.g. `/admin/my-profile`, editing the **logged-in user's own** record: profile image + masthead (`MediaPicker`), socials (a `TextInput` per platform, URL-validated), and the profile content builder (`Builder` with the shared `ContentBuilderBlocks`).
- Prominent notice: *"Your public profile goes live once you have at least one published spot guide."* (Show a subtle "live / not yet live" status derived from `hasPublicProfile()`.)
- Visible to contributors (owners manage via the resource instead).

### Owner editing
- The existing `ContributorResource` (owner-only) gains the same profile fields on its edit form, so the owner can edit any contributor's profile.

### Policy
- A contributor may edit **only their own** profile (the My Profile page always targets `auth()->id()`, so there's no cross-user write path). Owner-only fields from sub-project 1 (`is_published`, `is_featured`, role) are untouched here.

## Public profile page — `GET /contributors/{slug}` → `ContributorController@show`

- Route named `contributors.show`. `/contributors/{slug}` (two segments) can't collide with the one-segment catch-all `/{slug}`, but declare it with the other named web routes (before the catch-all) for clarity.
- Resolve the user by `slug` **among those with a public profile** (`hasPublicProfile()`); **404** on unknown slug, non-contributor, or a contributor with no published guide.
- Renders Inertia page `Contributors/Show` with:
  - `contributor`: `{ name, profile_image (imagePayload|null), profile_blocks (media-resolved), socials (filled-only map) }`
  - `static_masthead`: `imagePayload | null` (null → gradient fallback)
  - `guides`: their published guides projected to the guide-card shape (`title`, `slug`, `thumbnail`, country, wind/direction badges as used elsewhere)
  - `meta`: auto-generated SEO (`"{name} — Seabound Souls"` / a sensible description)

### `Contributors/Show.tsx` (frontend)
- `StaticMasthead` (their masthead or gradient fallback) + name.
- **Intro band:** the profile image as a framed rounded portrait overlapping the masthead's bottom edge; name + short intro beside/below; a **row of teal social icon buttons** (FontAwesome brand icons, hover states) rendering only filled socials.
- Their `profile_blocks` via `ContentBuilder`.
- A grid of their published guides (reuse the existing guide-card look).
- Reuses `CoverImage`, `BlockWrapper`, `ContentBuilder`; sits with the site's editorial/coastal style.

## About page + roll-up

- Rename the `about-us` Page → **`about`**: update the seeded Page slug, the nav link, and add a **301 redirect** `/about-us → /about` (so any existing link survives).
- New **"Contributor roll-up" content block** (`contributor_roll_up`) for the content builder — the owner drops it into the About page. It **auto-renders** every contributor with a public profile (avatar portrait card + name + short blurb + guide count) linking to `/contributors/{slug}`. Auto-maintained (no hand-picking); resolved server-side in the shared `ResolvesContentBlockMedia` trait (or a sibling resolver) so it needs no controller changes.
- Card styling matches the existing card grids.

## Clickable byline

- `SpotGuide::authorPayload()` gains `slug` (the contributor's) → `{ kind, name, slug }`.
- The byline on **destination cards** (`Destinations/Index.tsx`) and the **spot-guide page** (`SpotGuide/Show.tsx`) becomes a link to `/contributors/{slug}` when `kind === 'contributor'` (a published guide's contributor author always has a live profile, so the link is always valid). House byline stays plain text.

## Testing (TDD — write tests first)

- **Model:** slug generates from first+last, collision-suffixed, only for contributors; `hasPublicProfile()` true only for a contributor with a published guide (false for: owner, contributor with only drafts, contributor with none); `socials`/`profile_blocks` cast to array; media relations resolve.
- **`ContributorController@show`:** renders a live profile with its published guides; **404** on unknown slug, owner, and contributor-without-published-guide; socials payload contains only filled keys; guide list excludes drafts.
- **Roll-up block:** lists only contributors with a public profile; excludes draft-only/owner; links resolve.
- **Byline:** payload carries the contributor slug; house guides carry no slug.
- **About rename:** `/about` 200s; `/about-us` 301s to `/about`.
- **Admin:** My Profile page saves the logged-in contributor's fields and cannot write another user's record; owner can edit a contributor's profile via the resource.
- Mocks/no-network per project convention. Suite runs on SQLite; the migration is plain columns + FKs (portable) — smoke-test on the real Postgres dev DB per the divergence lesson.

## Out of scope (deliberate — additive later)

- No owner/founder profiles (the About prose covers the founders' story).
- No manual profile-visibility toggle (visibility is derived).
- No per-contributor SEO overrides (auto-generated meta).
- No contributor blog authorship (contributors author guides only).
- No follower/comment/social features.
- No hand-picking / ordering of the roll-up (auto-listed).

## Follow-ups after merge

- If desired later, switch `/contributors` → `/crew` (one route change + a 301) once discussed.
- Consider a per-contributor SEO override and a roll-up sort order if the crew grows.
