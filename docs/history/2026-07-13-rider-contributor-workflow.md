---
title: Rider contributor workflow (sub-project 1) + attribution & identity
tags: [riders, roles, filament, workflow, attribution, media, auth]
status: stable
completed: 2026-07-13
pr: 32
commits:
  - 3fda7f7 # design spec
  - 4e5baa2 # implementation plan
  - f5fbd3e # owner/rider role + role-based panel access
  - 04e3446 # author user_id on spot guides
  - 74d03ee # review lifecycle status + transition methods
  - b23766c # owner-scoped media library
  - d2105b0 # re-authorize media picker selections
  - c4ef1ed # role policies + rider-scoped query + inline country create
  - 58a2c9c # in-panel workflow notifications (email-ready)
  - 1e5f5f4 # submit/publish/request-changes actions + role-gated toggles
  - 7298fa7 # Invite Rider action + signed set-password flow
  - a3e529b # regenerate session on rider login
  - 3d5cea1 # map-click coordinate picker
  - 2af3bf9 # idempotent mapbox lazy-load
  - 0941def # logged-in preview of unpublished guides + banner
  - d14b47b # final-review fixes (review_status backfill, no-publish lock)
  - 0ac04cb # notifications.data -> jsonb (Postgres)
  - 4ef8ab0 # Riders admin section + per-rider guides panel
  - 2a0a834 # app.css standalone Vite entry (set-password page)
  - 234384e # label Riders (not Users)
  - 170876f # scope media folder options + stamp rider ownership on resource create
  - 9dfa0bd # require an image file on media create/edit
  - 90cef98 # featuring is owner-only across all write paths
  - 42f9e87 # free a guide's slug for reuse after soft delete
  - 25b78f1 # split rider name into first/last
  - d8f596c # public author attribution
  - 0b2e6ad # auto-flag rider edits to approved guides
---

# Rider contributor workflow

## Why

The site grew by the two owners physically visiting spots. That travel is ending,
capping growth at the handful of live guides. To keep growing, a small number of
vetted, invited windsurfers ("Riders") can now contribute spot guides for owner
review and publishing — with a better authoring experience (map-click coordinates)
and public attribution so visitors know a guide came from a contributor, not "us".

Design spec: `docs/superpowers/specs/2026-07-13-rider-contributor-workflow-design.md`.
Plan: `docs/superpowers/plans/2026-07-13-rider-contributor-workflow.md`.

## What shipped

**Roles & accounts.** `users.role` enum (`owner` | `rider`, default `rider`);
`User::canAccessPanel()` now admits any recognised role (owner or rider), with
per-resource **policies** doing the real gating. Existing account backfilled to
`owner`. Riders are created by an **owner-only Invite action** that mints a signed,
7-day set-password link (shown in-panel to copy — email is a later drop-in); the
rider sets their password via `Rider\SetPasswordController` (`session()->regenerate()`
on login) and lands in the panel.

**Rider identity.** Riders have structured `first_name` / `last_name` (invite +
edit forms collect both; a `User` saving hook keeps the canonical `name` in sync as
"First Last"; the owner's brand name is untouched).

**Riders admin section.** `RiderResource` (on the `User` model, scoped to
`role = rider`, owner-only via `UserPolicy`): a roster (name / email / guide count /
joined) + a per-rider edit page whose relation manager lists that rider's authored
guides with a link into the full editor. Labelled "Rider(s)" (not "User(s)").

**Authorship & review lifecycle.** `spot_guides.user_id` = author (auto-stamped on
create). New `review_status` (`draft` → `in_review` → `changes_requested` →
`approved`) + `review_note` / `submitted_at` / `reviewed_at`. `is_published` stays
the **owner-only** live switch. Transitions via model methods
(`submitForReview` / `publish` / `requestChanges`) wired to Filament header actions.
Notifications on the **`database` channel** (Filament bell) — structured so adding
`mail` later needs no rework.

**Editing after approval (auto-flag).** A rider editing an already-approved guide
keeps it live but flips it back to `in_review` and notifies owners — so live content
never changes silently. Owner edits and draft edits are unaffected.

**Own-work isolation.** Riders see only their own spot guides (query-scoped +
policy). **Media is owner-scoped**: `media_library.user_id` (`null` = house media);
riders see/upload/manage only their own, never house media — enforced in the resource
query, the picker browser query, folder-option lists, upload stamping (both the
resource create page and the picker), and a re-authorization guard on the picker's
`toggleSelect`/`confirm` (Livewire methods are network-callable). Countries: riders
can create one inline but not edit/delete existing ones. Blogs / Pages / Contact
Enquiries are owner-only.

**Map-click coordinate picker.** Reusable `MapCoordinatePicker` custom field beside
every lat/long pair (main spot + each windsurfing-location / stay / eat repeater
row). Opens a Mapbox modal (token via `config('services.mapbox.token')`, lazy-loaded
from CDN once, idempotently); clicking writes `decimal:7` coords into the sibling
fields via a pure static `siblingPathFor` derivation. Stores nothing itself.

**Preview of unpublished guides.** `SpotGuideController@show` renders an unpublished
guide only for the owner (any) or its author (their own) — else 404; an amber
"Unpublished preview" banner shows. A Filament Preview action opens the real page.

**Public attribution.** Each guide carries an author payload (`house` vs named
`rider`). A single site-wide `showProvenance` flag (true once any *published* rider
guide exists) gates bylines everywhere: rider guides show "By {name}", house guides
show "Seabound Souls", and nothing shows until the first rider guide is published.

## Findings worth keeping

- **SQLite tests hide Postgres failures.** Enabling Filament `->databaseNotifications()`
  made the bell query `data->>'format'`, which Postgres rejects on the stock `text`
  `notifications.data` column — but the SQLite suite passed. Fixed with a pgsql-only
  `ALTER … TYPE jsonb`. Smoke-test JSON-column features on real Postgres.
  (Memory: `project-sqlite-postgres-json-divergence`.)
- **`withoutVite()` hides missing manifest entries.** A standalone Blade page
  (`rider/set-password`) using `@vite('resources/css/app.css')` 500'd in build mode
  because `app.css` wasn't a Vite `input` entry (the SPA bundles it via `app.tsx`).
  Added it as a standalone input. Tests stub Vite, so smoke-test built Blade pages.
  (Memory: `project-vite-manifest-standalone-blade`.)
- **Two write paths for a flag.** Hiding a form field (`is_published`, `is_featured`)
  from riders isn't enough — inline table `ToggleColumn`s and network-callable
  Livewire methods are separate write paths. Guarded both at the model layer.
- **Soft-delete + unique slug.** A plain unique index counts trashed rows, locking a
  soft-deleted guide's slug. Replaced with a partial unique index
  (`WHERE deleted_at IS NULL`) + validation scoped to non-trashed. Blog/Page have the
  same latent bug — spun off as a follow-up (see TODO).

## Test plan

205 PHPUnit tests (from 148 at branch start), `npm run build` clean. Coverage added
for: role-based access, all policies, media isolation + picker re-auth, the full
lifecycle + notifications, invite + signed set-password, the coordinate-picker
sibling-path derivation + persistence, preview access, attribution payloads +
`showProvenance`, name split, slug reuse, required-image, owner-only featuring, and
the edit-after-approve auto-flag. Interactive checks (map picker clicks, byline
appearance, full dry-run) verified manually by the owner during the build.

## Follow-ups

- Wire the **`mail` channel** so invites + workflow notifications email out (in-panel
  / manual link only today).
- **Blog / Page slug reuse** — apply the same partial-index fix (background task).
- Rider **public profile pages** (bio / socials, clickable byline) — deferred
  sub-project 2.
