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

- The photographer does **not** want to provide an email address and does not want an
  account. No PII is stored for him.
- The design must not foreclose two plausible futures: a public photographer profile
  page, and photographer-managed content behind a login.
- Neither of those futures may require undoing or migrating work done now.

---

## Why this is small: two existing choke points

**Serialisation.** `MediaLibrary::imagePayload()` (`app/Models/MediaLibrary.php:54`)
returns `{url, alt, focal_x, focal_y}` and is the single source of image data for the
entire site — 45+ call sites across every controller, `ResolvesContentBlockMedia`,
search, and contributor profiles. One new key there reaches every image everywhere.

**Rendering.** `CoverImage` (`resources/js/Components/Common/CoverImage.tsx`) draws
essentially every image on the site — mastheads, galleries, split-image-text, cards,
maps, slider. Only `SingleImage.tsx:24` still uses a raw `<img>` (converted as part of
this work). The credit UI therefore lives in one component.

## Why a standalone model, not a user role

A credit is not an account. The `users` table carries auth semantics — password hashes,
panel access, a policy layer gating on `isOwner()` / `isContributor()`. Creating
login-capable records for people whose only role is to appear in a caption would mean
every policy carrying a third case permanently, to serve someone who has declined an
account.

`Tag` is the precedent in this repo: a standalone, auth-free model that grew a full
public presence (`/blog/tags/{slug}`, hub page, masthead, thumbnail, SEO) without
difficulty. `Photographer` follows the same trajectory.

The standalone table is also the better base for the *login* future, not just the page
future: profile content lives on the `photographers` row from day one, so granting an
account later changes only **who may edit that row**. With a user-role approach, profile
data would sit on `users` rows for people with no account — the awkward version.

---

## Data model

### New table: `photographers`

| Column | Type | Notes |
|---|---|---|
| `name` | string, required | The credit line text |
| `slug` | string, nullable, partial-unique | Auto-filled from `name` in a `booted()` hook, as `Tag` does |
| `socials` | json, nullable | Platform → URL map; same keys as contributors |
| `credit_link` | string, nullable | The **key** of the active link target |
| `bio` | text, nullable | Unused until the page ships |
| `thumbnail_media_id` | FK → `media_library`, nullable | Unused until the page ships |
| `static_masthead_media_id` | FK → `media_library`, nullable | Unused until the page ships |
| `profile_blocks` | json, nullable | Unused until the page ships |
| `seo_title`, `seo_description` | string/text, nullable | Unused until the page ships |
| soft deletes | | Matches `Tag` |

The `slug` index must be **partial-unique (`WHERE deleted_at IS NULL`)**, not plainly
unique — the same combination `tags` uses. Soft deletes plus a plain unique index would
block re-creating a photographer whose earlier record was soft-deleted. (Contrast
`users.slug`, which is plainly unique because `users` has no soft deletes.)

**No `email` column.** The photographer declined to give one, so storing it would be
wrong regardless of convenience. When an invite is eventually needed, the owner enters
the address in the invite dialog at that moment — a fresher address than one stored for
years, and no PII on file for someone who did not consent to provide it.

**No `user_id` column.** Deliberately deferred. This differs from `slug`, which *is*
added now: `slug` derives from data already held, so adding it later needs a backfill
migration; `user_id` is null until someone is invited, so adding it later is a two-line
migration with no data work. Carrying an always-null FK now would misrepresent what the
system can do.

### `media_library` gains `photographer_id`

Nullable FK with `nullOnDelete()`. Null means "our image" — the default and the 95% case.

### Why `credit_link` stores a key, not a URL

The active target is a **key** (`instagram`), resolved against `socials` at read time —
never a second copy of the URL. One source of truth: change the Instagram handle in one
field and every credit on the site follows. A duplicated URL column would drift.

The option list is:

```
none | profile | website | instagram | youtube | tiktok | facebook | x
```

`profile` resolves to `/photographers/{slug}` and is **not offered in the Select** until
the public page exists. Because `creditPayload()` treats any unrecognised key as "no
URL", a `profile` value set via tinker or a seeder degrades to plain text rather than
linking to a 404 — no feature flag required.

When the page does ship, switching every credit on the site from Instagram to the
on-site profile is a dropdown change in the admin, per photographer, with no deploy.

### Model API

```php
Photographer::creditPayload(): ?array  // {name: string, url: string|null}
```

`url` is null when `credit_link` is unset, `none`, unrecognised, or points at a socials
key whose value is empty.

---

## Admin

- **`PhotographerResource`**, owner-only via a new `PhotographerPolicy`, in the Content
  navigation group.
- **Form schema lives in `App\Filament\Forms\PhotographerProfileForm::schema()` from the
  first commit**, even though only one caller exists. This is the single decision that
  makes the future handover free: contributors already prove the pattern — the owner
  edits via `ContributorResource`, the contributor self-edits via
  `App\Filament\Pages\MyProfile`, and both call the same
  `ContributorProfileForm::schema()`. One schema, two entry points, different
  authorisation.
- **`credit_link` Select options derive from the *filled* socials**, so a target with no
  URL behind it cannot be selected.
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
current scope requires it, but this is the cheapest possible moment to close it, and it
is precisely the trap that would bite on the day a photographer is first invited.

(For contrast, `MediaLibraryPolicy` is already safe by construction — every method is
`$user->isOwner() || $media->user_id === $user->id`, so a new role automatically gets
only its own media.)

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
- `<a target="_blank" rel="noopener noreferrer">` when `url` is set; a plain `<span>`
  when it is not
- Accessible label reads "Photo by {name}, opens in a new tab" rather than a bare name
- A relative `url` (the future `profile` case) renders an Inertia `<Link>` instead —
  decided in the component from the URL shape

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
free; this is where the implementation time actually goes.

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
| `credit_link` holds an unrecognised key (incl. `profile` today) | Plain text credit |
| Photographer soft-deleted | Credit vanishes; restoring brings it back |

The soft-delete case is subtle and deserves a pinning test: `nullOnDelete()` is a
database-level action that fires only on a *hard* delete, so `photographer_id` still
points at the soft-deleted row. The credit disappears because the relation returns null
under the SoftDeletes global scope — the right outcome, reached by a different mechanism
than the schema implies.

---

## Testing

Tests first, per the project standard.

**PHPUnit**

- `creditPayload()` across the full resolution matrix (valid key, cleared key,
  unrecognised key, `none`, no socials at all)
- `imagePayload()` carries `credit`, and carries null when no photographer is assigned
- Soft-deleted photographer nulls the credit; restore brings it back
- `PhotographerPolicy` denies contributors all access
- Bounded query count on `/destinations` (N+1 guard)
- `MediaLibraryResource` scoping hardening: a non-owner sees only their own media

**Vitest**

- `ImageCredit` renders an anchor when `url` is set, a `<span>` when it is null
- External vs internal (relative) link handling
- `CoverImage` layout is unchanged when no credit is present

---

## Out of scope

Deliberately excluded to keep this piece focused:

- The public `/photographers/{slug}` page
- A `list_photographers` content block for the About page
- Photographer login — `user_id`, the role, the invite action, `MyPhotographerProfile`

The columns supporting the first two are created now and sit empty. The third needs a
two-line migration when the day comes.

## The upgrade path

| Now | Later, if wanted |
|---|---|
| `photographers`: name, slug, socials, `credit_link`, profile fields | `+ user_id` (two-line migration) |
| `PhotographerProfileForm::schema()` | Reused verbatim |
| `PhotographerResource` (owner-only) | `+ MyPhotographerProfile` page |
| Credits link out to Instagram | `credit_link` switched to `profile` in a dropdown |

Nothing in the left column is undone to reach the right.

The login step is cheaper than it looks: `SetPasswordController`
(`app/Http/Controllers/Contributor/SetPasswordController.php:20`) is entirely
role-agnostic — it accepts any `User`, sets a password, logs them in, redirects to
`/admin`. The signed-link middleware sits on the route, not the role. So the future
invite action is: create a `User` with `role = photographer`, link
`photographers.user_id`, mint the same signed link, reuse the same controller, and add a
`MyPhotographerProfile` page pointing the existing schema at
`auth()->user()->photographer`.
