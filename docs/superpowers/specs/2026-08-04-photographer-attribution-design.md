# Photographer Attribution — Design

**Date:** 2026-08-04
**Branch:** `feature/photographer-attribution`
**Status:** Approved design, ready for planning

---

## Problem

A photographer is supplying images for spots the site has no photography for (a handful
of images on one spot guide). His work must be credited wherever it appears, with a link
to a destination of his choosing — currently his Instagram.

Two needs sit behind this:

- **Public:** credit his images wherever they render.
- **Admin:** distinguish his images from the site's own in the media library at a glance.

Roughly 5% of site images will carry a credit. The other 95% are the site's own and
render exactly as they do today.

## Constraints

- The photographer does not want a page or an account today. He is happy with a credit
  linking to his Instagram.
- **The owner must be able to give any photographer a page later without reopening the
  development process** — filling in the admin form must be sufficient. This is a firm
  requirement and it drives the scope below.
- No PII is stored for a photographer who hasn't provided it.

---

## Why this is tractable: two existing choke points

**Serialisation.** `MediaLibrary::imagePayload()` (`app/Models/MediaLibrary.php:54`)
returns `{url, alt, focal_x, focal_y}` and is the single source of image data for the
entire site — 45+ call sites across every controller, `ResolvesContentBlockMedia`,
search, and contributor profiles. One new key there reaches every image everywhere.

**Rendering.** `CoverImage` (`resources/js/Components/Common/CoverImage.tsx`) draws
essentially every image on the site. Only `SingleImage.tsx:24` still uses a raw `<img>`
(converted as part of this work). The credit UI therefore lives in one component.

## Why a standalone model, not a user role

A credit is not an account. The `users` table carries auth semantics — password hashes,
panel access, a policy layer gating on `isOwner()` / `isContributor()`. Creating
login-capable records for people whose only role is to appear in a caption would mean
every policy carrying a third case permanently.

`Tag` is the precedent in this repo: a standalone, auth-free model that grew a full
public presence (`/blog/tags/{slug}`, hub page, masthead, thumbnail, SEO) without
difficulty.

The standalone table is also the better base for a future login, not just the page:
profile content lives on the `photographers` row from day one, so granting an account
later changes only **who may edit that row**. With a user-role approach, profile data
would sit on `users` rows for people with no account.

---

## Data model

### New table: `photographers`

| Column | Type | Notes |
|---|---|---|
| `name` | string, required | The credit line text |
| `slug` | string, nullable, partial-unique | Auto-filled from `name` in a `booted()` hook, as `Tag` does |
| `socials` | json, nullable | Platform → URL map; same keys as contributors |
| `credit_link` | string, nullable | The **key** of the active link target |
| `bio` | text, nullable | Short intro shown on the profile page |
| `thumbnail_media_id` | FK → `media_library`, nullable | Card image for the roll-up block |
| `static_masthead_media_id` | FK → `media_library`, nullable | Profile page hero |
| `profile_blocks` | json, nullable | Content builder — **also the page's visibility gate** |
| `seo_title`, `seo_description` | string/text, nullable | Profile page SEO |
| `user_id` | FK → `users`, nullable | Reserved for a future login; unread today |
| soft deletes | | Matches `Tag` |

The `slug` index must be **partial-unique (`WHERE deleted_at IS NULL`)**, not plainly
unique — the same combination `tags` uses. Soft deletes plus a plain unique index would
block re-creating a photographer whose earlier record was soft-deleted. (Contrast
`users.slug`, which is plainly unique because `users` has no soft deletes.)

**No `email` column.** The existing invite flow captures email in the action's dialog at
invite time rather than storing it in advance — see
`ListContributors.php:32`. There is nothing to store today, and `users.email` is
required and unique, so an address is needed at account-creation time regardless of what
this table holds.

**`user_id` is included but unread.** Nothing in this scope populates or consumes it. It
exists so that adding a login later is a pure feature addition against a schema that
already anticipates it, with no migration.

### `media_library` gains `photographer_id`

Nullable FK with `nullOnDelete()`. Null means "our image" — the default and the 95% case.

### Why `credit_link` stores a key, not a URL

The active target is a **key** (`instagram`), resolved against `socials` at read time —
never a second copy of the URL. One source of truth: change the Instagram handle in one
field and every credit on the site follows.

```
none | profile | website | instagram | youtube | tiktok | facebook | x
```

`profile` resolves to `/photographers/{slug}` and is offered in the Select **only when
the photographer has a live page** (see the visibility gate below). Switching a
photographer's credits from Instagram to their on-site profile is then a dropdown change
in the admin, per photographer, with no deploy.

### Model API

```php
Photographer::hasPublicPage(): bool     // slug present AND profile_blocks non-empty
Photographer::creditPayload(): ?array   // {name: string, url: string|null}
```

`url` is null when `credit_link` is unset, `none`, unrecognised, points at a socials key
whose value is empty, **or is `profile` while `hasPublicPage()` is false**. That last
clause is what stops a credit linking to a 404 if the owner empties a profile after
pointing credits at it.

---

## Public profile page

### Visibility is derived, never a manual flag

`hasPublicPage()` is true when the record has a slug and **non-empty `profile_blocks`**.
The content builder is the page body; no body, no page.

This mirrors `User::hasPublicProfile()`, where a contributor earns a public page by
having ≥1 published guide. The reason it matters here: once the page capability exists,
without a gate *every* photographer would have one — including a man who wanted nothing
but a photo credit. That's a thin, empty page, bad for him and bad for SEO. With the
gate, building the capability now costs nothing in the meantime, because no page goes
live until someone deliberately fills one in.

Filling in the content builder **is** the decision to publish the page. There is no
separate switch to forget.

### Surface

| Item | Detail |
|---|---|
| Route | `GET /photographers/{slug}` → `PhotographerController@show`, name `photographers.show` |
| Behaviour | `abort_unless($photographer->hasPublicPage(), 404)` — directly parallel to `ContributorController@show` |
| Page | `Pages/Photographers/Show.tsx` — masthead, name, bio, `SocialLinks`, content-builder body |
| Sitemap | Added to `SitemapBuilder`, one URL per photographer with a live page, priority 0.6 |

`SocialLinks` (`resources/js/Components/Common/SocialLinks.tsx`) is reused as-is — it
takes a `Record<string, string>`, renders only filled entries, and ignores unknown keys.

Note the route sits above the `/{slug}` catch-all in `routes/web.php`, as
`/blog/tags` does relative to `/blog/{slug}`.

### `list_photographers` content block

A new content-builder block for the About page, modelled directly on
`contributor_roll_up`:

- Filament schema: `heading` + optional `intro` (see `ContentBuilderBlocks.php:154`)
- Resolver in `ResolvesContentBlockMedia`: a fresh query for photographers with a live
  page, ordered by name, emitting `{name, slug, thumbnail, bio}` — the owner never
  hand-picks, so there is no ID list to resolve
- Component: `Components/Content/PhotographerRollUp.tsx`

---

## Admin

- **`PhotographerResource`**, owner-only via a new `PhotographerPolicy`, in the Content
  navigation group.
- **Form schema lives in `App\Filament\Forms\PhotographerProfileForm::schema()`.**
  Contributors already prove the pattern — the owner edits via `ContributorResource`,
  the contributor self-edits via `App\Filament\Pages\MyProfile`, and both call the same
  `ContributorProfileForm::schema()`. One schema, two entry points, different
  authorisation. Keeping the photographer schema in its own class from the first commit
  is what makes a future self-edit page free.
- **`credit_link` Select options derive from the *filled* socials**, plus `profile` when
  `hasPublicPage()` is true — a target with no URL behind it can never be selected.
- **`MediaLibraryResource`** gains: a Photographer select on the form (the
  retro-assignment path for existing images), a Photographer column, and a
  `SelectFilter` — this is the admin "distinguish ours from his" requirement.
- **Bulk action** on the media table to assign a photographer to selected rows. The real
  workflow is "a batch of images arrives from one person"; thirty one-at-a-time edits
  would be miserable, and this is ~5 lines in Filament.

### Security hardening folded in

`MediaLibraryResource::getEloquentQuery()` (line 117) and `folderOptions()` currently
scope with:

```php
if ($user && $user->isContributor()) { ... }
```

This is an opt-*in* restriction: any future role fails the check, falls through, and
sees the entire media library including house media. Inverted to:

```php
if ($user && ! $user->isOwner()) { ... }
```

Behaviourally identical today, safe against every role added later. Nothing in the
current scope requires it, but this is the cheapest moment to close it, and it is
precisely the trap that would bite on the day a photographer is first given a login.

(For contrast, `MediaLibraryPolicy` is already safe by construction — every method is
`$user->isOwner() || $media->user_id === $user->id`.)

---

## Payload and rendering

### Server

`imagePayload()` gains one key:

```php
'credit' => $this->photographer?->creditPayload(),
```

Null for 95% of images. All 45+ call sites inherit it with no edit.

**N+1 is the main performance risk.** Every controller eager-loading `thumbnailMedia`,
`staticMastheadMedia` and friends now needs `thumbnailMedia.photographer`, or a
destinations page issues a query per card. `ResolvesContentBlockMedia` needs the same
treatment where it hydrates block media. Covered by an explicit bounded-query-count test.

### Client

`FocalImage` in `@/types/media` gains:

```ts
credit?: { name: string; url: string | null } | null
```

A new **`ImageCredit`** component renders it:

- Small, bottom-right, with a subtle scrim so it stays legible over arbitrary photography
- **Always visible, never hover-only** — hover-only fails silently on touch devices,
  which is where most of the site is read
- `<a target="_blank" rel="noopener noreferrer">` for an external URL; an Inertia
  `<Link>` when the URL is relative (the `profile` case); a plain `<span>` when null
- Accessible label reads "Photo by {name}, opens in a new tab" rather than a bare name

Because the badge sits over the photo, it looks the same in light and dark by design:
the photo is its background, not the page. It still uses theme tokens rather than raw
colour utilities so it passes the CI colour guard.

### Credit density is a design input

At ~5% coverage, a destination grid shows **zero** badges today and perhaps one later. A
lone badge among eleven bare cards reads as a glitch unless it is deliberately styled to
look intentional — consistent placement, quiet, confident. Sparse credits do not clutter,
but they do stand out, and the treatment must account for that.

### The main implementation risk

`CoverImage` currently returns a bare `<img>` and passes the caller's `className`
straight to it. Callers depend on that; several pass `absolute inset-0 w-full h-full`.
Hanging a positioned badge off it means either wrapping the `img` (changing layout for
all ~16 consuming components) or rendering the badge as a sibling that depends on the
parent being `relative` (most, but not provably all, already are).

**Resolution:** `CoverImage` wraps consistently whether or not a credit is present, so
layout can never differ between a credited and an uncredited image, with the `className`
split between wrapper (position/size) and `img` (object-fit). This requires a verified
pass over every consuming component at mobile, tablet and desktop. The data plumbing is
free; this is where the front-end time actually goes.

### Coverage exclusions

- **Map markers and popups** — `DestinationsMap` and `SpotGuideMap` use `CoverImage` for
  ~40px pin thumbnails that are UI chrome, not photography display. A badge there is
  illegible. Opt out via an explicit `showCredit={false}` prop.
- **The two logos** in `NavBar` and `Footer` are not library images and are unaffected.

### Additions

- `SingleImage.tsx:24` converts from a raw `<img>` to `CoverImage`, so it stops silently
  skipping credits.
- The **gallery lightbox** (fslightbox) shows the credit. The full-size view is the image
  a reader is actually looking at, so it matters most there.

---

## Edge cases

Every path resolves to plain text or nothing. A dead `href` is never rendered.

| Case | Behaviour |
|---|---|
| Image has no photographer (the 95%) | No badge; layout identical to today |
| Photographer has no socials filled | Plain text credit, no anchor |
| `credit_link` points at a key since cleared | Plain text credit |
| `credit_link` holds an unrecognised key | Plain text credit |
| `credit_link` is `profile` but `profile_blocks` was emptied | Plain text credit; page 404s |
| Photographer soft-deleted | Credit vanishes; restoring brings it back |
| Photographer has no `profile_blocks` | No public page (404); credits still work |

The soft-delete case deserves a pinning test: `nullOnDelete()` is a database-level action
that fires only on a *hard* delete, so `photographer_id` still points at the soft-deleted
row. The credit disappears because the relation returns null under the SoftDeletes global
scope — the right outcome, reached by a different mechanism than the schema implies.

---

## Testing

Tests first, per the project standard.

**PHPUnit**

- `creditPayload()` across the full resolution matrix (valid key, cleared key,
  unrecognised key, `none`, no socials, `profile` with and without a live page)
- `hasPublicPage()` true/false cases
- `imagePayload()` carries `credit`, and null when no photographer is assigned
- Soft-deleted photographer nulls the credit; restore brings it back
- `/photographers/{slug}` renders for a live page, 404s without `profile_blocks`, 404s
  for an unknown slug
- `list_photographers` resolver returns only photographers with live pages
- Sitemap contains live photographer pages and excludes gated ones
- `PhotographerPolicy` denies contributors all access
- Bounded query count on `/destinations` (N+1 guard)
- `MediaLibraryResource` scoping hardening: a non-owner sees only their own media

**Vitest**

- `ImageCredit` renders an anchor for external, an Inertia `<Link>` for relative, a
  `<span>` when null
- `CoverImage` layout is unchanged when no credit is present
- `PhotographerRollUp` renders nothing when the resolved list is empty

---

## Out of scope

**Login only.** `ROLE_PHOTOGRAPHER`, the invite action, panel gating, and a
`MyPhotographerProfile` self-edit page are not built.

The reasoning: giving a photographer a page does not require them to have an account.
If one wants a page, the owner fills in the admin form and it goes live — no dev cycle,
which is the firm requirement above. A login is only needed if a photographer wants to
edit the page *themselves* — a different and rarer trigger, and the most expensive,
highest-risk piece (a new role, a policy pass over every resource, a fresh authorisation
surface). The page unblocks the owner; the login unblocks the photographer. Only the
first is a bottleneck the owner would hit.

The schema (`user_id`) and the shared form class (`PhotographerProfileForm::schema()`)
both anticipate it, so it remains a pure feature addition. `SetPasswordController`
(`app/Http/Controllers/Contributor/SetPasswordController.php:20`) is already entirely
role-agnostic — it accepts any `User`, sets a password, logs them in, redirects to
`/admin` — and the signed-link middleware sits on the route, not the role. So the future
work is: create a `User` with `role = photographer`, link `photographers.user_id`, mint
the same signed link, reuse the same controller, and point the existing schema at
`auth()->user()->photographer`.
