# Rider contributor workflow — design (sub-project 1)

**Date:** 2026-07-13
**Status:** Approved design, pending implementation plan
**Branch:** `feature/rider-contributor-workflow`

## Background & motivation

Seabound Souls grew by the two of us physically travelling to spots and gathering
first-hand data. For personal reasons that travel is ending, which caps the site's
growth at the handful of spots currently live (local DB now mirrors production).

To keep the site growing, we want to let a small number of vetted, invited
windsurfers — **"Riders"** — contribute spot guides for our review, then publish
them. A Rider is not just a content contributor; they are a named member of the
crew who (in a later sub-project) gets their own promotable profile page, so a
guide they write can be attributed to them rather than to us.

There is a real forcing function: a tester is already in Gran Canaria and wants to
start. This document specifies **sub-project 1** — the minimum that lets that
Rider log in, build a guide with a decent map-based editing experience, submit it,
and lets the owner preview, review, and publish it end-to-end.

## Scope

### In scope (sub-project 1)
1. Roles & invited Rider accounts.
2. Authorship on spot guides.
3. Submit → review → publish lifecycle (with "changes requested").
4. Panel scoping so Riders only touch their own work and their own media.
5. Map-click coordinate picker for every lat/lng pair.
6. Logged-in preview of unpublished guides on the real public page.
7. In-panel notifications, structured so email is a later drop-in.

### Out of scope (later sub-projects)
- Rider public profile page (`/riders/{slug}`) and public attribution linking
  (sub-project 2).
- About → team/crew roll-up page (sub-project 2).
- Email delivery of invites and notifications (drop-in once mail is configured).
- Place-search / geocoding autofill in the map picker.
- Public self-registration of Riders.

## Guiding principle

**The house owns what is live.** Once a guide is published it is the site's; only
the owner can unpublish or delete it. Riders retain control only over their own
work while it is still unpublished.

## Design

### 1. Roles & accounts

- Add `users.role` — enum `owner` | `rider`, not null, default `rider`. A data
  migration backfills the single existing account to `owner`.
- `User::canAccessPanel()` currently gates to the single config email. Change it to
  allow any user whose `role` is `owner` or `rider`. (The owner is still identified
  as the config-email account; `role` is the access mechanism.)
- **Invite flow** — owner-only Filament action ("Invite Rider"):
  - Input: name + email.
  - Creates a `User` with `role = rider` and an unusable/random password.
  - Generates a **signed, expiring set-password URL** (Laravel signed route or
    password-broker token).
  - The link is displayed to the owner to copy and send by hand for now. The same
    code path gains an email send once mail is wired (see §7).
- **Set-password page** — a public route where the invited Rider sets their
  password, after which they can log in to the panel.

### 2. Authorship

- Add `spot_guides.user_id` — FK → `users`, nullable, `nullOnDelete`. Represents
  the guide's **author**. Data migration backfills existing guides to the owner.
- `SpotGuide belongsTo author (User)`; `User hasMany authoredSpotGuides`.
- Owner-created guides get the owner's id; Rider-created guides get the Rider's id.

### 3. Review lifecycle

New columns on `spot_guides`:
- `review_status` — enum `draft` | `in_review` | `changes_requested` | `approved`,
  default `draft`.
- `review_note` — text, nullable (the owner's "changes requested" feedback).
- `submitted_at`, `reviewed_at` — nullable timestamps (audit trail).

Existing `is_published` / `published_at` remain the **live-visibility switch** and
are **owner-only** to change.

Transitions:
| Actor | Action | Effect |
|-------|--------|--------|
| Rider | create | `review_status = draft` |
| Rider | **Submit for review** | `in_review`, `submitted_at = now`; notify owner |
| Owner | **Publish** | `is_published = true`, `review_status = approved`, `published_at` set if null, `reviewed_at = now`; notify author |
| Owner | **Request changes** (with note) | `review_status = changes_requested`, `review_note` saved, `reviewed_at = now`; notify author |
| Rider | edit + re-submit a published guide | `review_status = in_review`; notify owner |

Rules:
- **No read-only lock.** The Rider can edit at any status; the owner may therefore
  review a moving target (accepted trade-off). Status is always visible so it's
  obvious when a guide is mid-edit.
- **Request changes does not auto-unpublish** a live guide. If the owner wants a
  live guide taken down they unpublish it separately.
- Only the owner can toggle `is_published` (Publish / Unpublish).

### 4. Panel scoping (Policies + Filament)

- `SpotGuidePolicy`:
  - Owner → full access to all guides.
  - Rider → `viewAny`/`view`/`create`/`update` limited to guides where
    `user_id = auth id`.
  - **Delete:** Rider may soft-delete only their own guide while it is *not*
    published; a published guide can be soft-deleted only by the owner (house
    ownership principle).
- `SpotGuideResource::getEloquentQuery()` scopes the table to own guides for Riders.
- **Hidden from Riders:** Blog, Page, and Contact Enquiry resources; other users'
  data; and the owner-only Publish / Request-changes / Unpublish actions.
- **Countries:** Riders may **view and create** countries (so they can add a
  missing one for their spot) but may **not edit or delete** existing countries
  (shared house data). Enforced via `CountryPolicy`.
- **Media ownership:** add `media_library.user_id` — nullable (null = house media,
  owned by the owner). Scoping:
  - `MediaLibraryResource` and the `MediaPicker` field show a Rider only rows where
    `user_id = auth id`. The owner sees all rows.
  - Rider uploads set `user_id = auth id`.
  - Riders cannot see, edit, or delete house media; owner media and Rider media are
    cleanly separated.
- Action visibility: **Publish / Request-changes / Unpublish** = owner only;
  **Submit for review** = Rider only, and only on their own guides.

### 5. Map-click coordinate picker

- A reusable **"Pick on map"** control sits beside every latitude/longitude pair:
  the main spot coordinates, each `windsurfing_locations` repeater row, and each
  `recommendations` (stay/eat) repeater row.
- Clicking it opens a modal containing a Mapbox map (reuses the existing
  `MAPBOX_TOKEN`). Clicking the map drops/moves a marker; confirming writes the
  marker's lat/lng (rounded to `decimal:7`) into that row's two fields.
- The map pre-centres on: existing coordinates for that row → else the main spot
  coordinates → else a sensible global default.
- Built as a **custom Filament field** (Blade + Alpine + Mapbox GL) following the
  existing `MediaPicker` custom-field pattern already in the repo, so it composes
  inside repeaters.

### 6. Public preview of unpublished guides

- `SpotGuideController@show`: if the requested guide is **not** published, render it
  only when the request is authenticated **and** the user is the **owner** (any
  guide) or the **author** (their own guide). Otherwise return 404 exactly as today.
- Add a **"Preview"** action/button in Filament that opens the real
  `/destinations/{slug}` page in a new tab.
- When a non-published guide is being previewed, show a subtle **"Unpublished
  preview"** banner on the page so it is unmistakably not the live version.

### 7. Notifications (in-panel now, email-ready)

- Use Laravel **Notification** classes delivered on the **`database` channel**,
  which Filament surfaces via its notification bell:
  - Rider submits → owner: "{Rider} submitted {guide} for review."
  - Owner publishes → author: "Your guide {guide} is now published."
  - Owner requests changes → author: "Changes requested on {guide}" + the note.
- Because these are standard notification classes, adding the `mail` channel later
  is the entire email wiring — no structural rework. The invite link (§1) shares
  this future email path.

## Testing (TDD)

Write tests first for each unit. External services are mocked; no network/live DB.

- **Access:** `canAccessPanel` allows owner and rider, denies a role-less/other
  user.
- **Policies:** a Rider cannot view/update/delete another Rider's guide or house
  media; the owner can; a Rider can create a country but not edit/delete one; a
  Rider cannot delete their own *published* guide.
- **Lifecycle:** Submit sets `in_review` + `submitted_at` and notifies the owner;
  Publish sets `is_published`/`approved`/`published_at`/`reviewed_at` and notifies
  the author; Request-changes sets `changes_requested` + `review_note` and notifies
  the author. Use `Notification::fake()`.
- **Preview access:** unpublished guide → 404 for guest and for a non-author Rider,
  200 for owner and for the author.
- **Invite:** creating a Rider produces a valid signed set-password link; using it
  sets the password and grants login; an expired/invalid link is rejected.
- **Map picker:** client-side JS (not unit-tested for map interaction); a server
  test asserts submitted coordinates persist to the guide and repeater rows.

## Open questions

None outstanding — all decisions resolved during brainstorming (2026-07-13).

## Follow-up sub-projects (not built here)

- **Sub-project 2 — Rider identity & attribution:** Rider profile editor + public
  `/riders/{slug}` page; guides authored by a Rider link to and are attributed to
  that Rider (the "from a contributor, not us" signal); About page becomes a
  generic about-us plus a crew/team roll-up linking to Rider pages.
- **Email delivery:** wire the `mail` channel for invites and notifications.
- **Map picker enhancement:** optional place-search / geocoding to autofill name
  and address alongside coordinates.
