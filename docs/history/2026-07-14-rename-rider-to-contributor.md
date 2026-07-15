---
title: Rename "Rider" to "Contributor" across the codebase
tags: [refactor, contributor-workflow, naming, admin]
status: stable
completed: 2026-07-14
commits: [e6a685c]
pr: 33
---

# Rename Rider → Contributor

## What shipped

A full, no-data-loss rename of the sub-project-1 role from **Rider** to **Contributor**, touching every layer: the stored `users.role` value (`rider` → `contributor`), the role constant/helpers on `User`, the Filament admin section (`RiderResource` → `ContributorResource`, labels, breadcrumbs, nav), the invite action, the signed set-password route namespace/controller (`Contributor\SetPasswordController`), the public-attribution copy, and all docs (`CLAUDE.md`, `SITREP.md`, history).

Ben confirmed production held **no** contributor data yet, so the migration could rewrite the role value directly rather than dual-read.

## Key changes

- **Data migration** `2026_07_14_100000_rename_rider_role_to_contributor.php` — `UPDATE users SET role='contributor' WHERE role='rider'` plus a `->change()` to move the column default from `rider` to `contributor`.
- `User::ROLE_RIDER` → `ROLE_CONTRIBUTOR` (value `'contributor'`); `isRider()` → `isContributor()`.
- `RiderResource` → `ContributorResource` (model still `User`, scoped `where('role', ROLE_CONTRIBUTOR)`), `modelLabel`/`pluralModelLabel` `contributor`/`contributors`, nav + breadcrumbs fixed (the old "Users List / Users" strings).
- Routes renamed to the `contributor.password.*` namespace; controller moved to `App\Http\Controllers\Contributor\`.

## Findings worth keeping

- **An earlier migration referenced the renamed constant.** Migration `100070` (add first/last name) used `User::ROLE_RIDER`; after the rename that constant no longer existed, so a fresh `migrate` (e.g. the SQLite test bootstrap) would fatal. Fix: replaced the constant reference with the **literal** `'rider'` in that historical migration (migrations must not depend on evolving app constants) and removed the now-unused import.
- **Rename doubled some phrases** during search-replace — "Contributor contributor workflow" (the feature was literally named "Rider contributor workflow"), "an an invited contributor". Caught in review; fixed.

## Test plan

Full suite green after the rename (the contributor-workflow tests from #32 carried over, retargeted to the new names). No new behaviour, so no new tests beyond the retargeted ones.

## Follow-ups

- None specific to the rename. The broader contributor-workflow follow-ups (email delivery, public profile pages) are unchanged — see `docs/TODO.md`.
