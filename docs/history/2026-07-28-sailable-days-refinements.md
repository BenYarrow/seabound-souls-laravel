---
title: Sailable-days post-launch refinements — metric evolution + mobile collapse
tags: [destinations, weather, frontend, ux, data-quality]
status: stable
completed: 2026-07-28
commits: [211a8b7, 1e88162, 9a9fa57]
pr: [40, 41]
---

# Sailable-days refinements (same-day, post-launch)

Follow-ups to the sailable-days ranking shipped earlier the same day (see
[2026-07-28-sailable-days-ranking](2026-07-28-sailable-days-ranking.md)),
driven by owner eyeballing the live page.

## 1. Mobile: collapsible filter bar (PR #40, `2c0f392`)

The sticky filter bar stacked its five controls vertically on mobile, eating
too much of the viewport. Below `lg` it now collapses behind a tap-to-expand
header showing a one-line summary of the active filters (e.g. *July · 20 kts ·
All spots · By continent*) + a chevron; the sticky bar drops from ~445px to
~50px tall. Desktop is unchanged (inline row, always visible). Purely
presentational — `aria-expanded`/`aria-controls` wired, chevron rotates.
Verified via DOM at mobile (collapsed `display:none`, 50px) and desktop
(toggle `lg:hidden`, controls inline) on a local build.

File: `resources/js/Components/Destinations/DestinationFilterBar.tsx`.

## 2. The sailable metric evolved twice more (data-quality)

The "sailable day" definition took **two real-world corrections** after launch,
each from an owner spotting a wrong ranking against lived knowledge. The journey
is the lesson, so it's recorded in full:

### 2a. Sustained → gust (shipped in the original PR #39, recorded here for the arc)
Originally a day was sailable if ≥2 hours cleared the minimum on **sustained**
wind (`wind_speed_10m`). Karpathos June then read ~1 sailable day when the
meltemi blows almost daily. Cause: **Open-Meteo's sustained 10m wind
systematically under-reads thermal/venturi spots** (model ~14 kt vs. gusts
~28 kt, which track felt wind). Switched the ranking to the daily 2nd-highest
**gust** (`qualifying_gust_kts`).

### 2b. Gust → gust + sustained floor (blend) (PR #41, `1e88162`)
Pure-gust then over-counted **gusty-but-not-steady** days. Symptom: Karpathos
(gust/sustained ratio ≈ **2.1** — spiky) outranked Langebaan (≈ **1.3** —
steady) even in December/January, when Karpathos's meltemi is long gone and the
"wind" is cold frontal-storm gust spikes.

Fix — a day counts at minimum `X` only if:

> `qualifying_gust_kts ≥ X` **AND** `qualifying_wind_kts ≥ 0.6·X`

(`SUSTAINED_FLOOR_FRACTION = 0.6` in `resources/js/Helpers/sailableDays.ts`.)
The sustained floor rejects spiky storm days; steady spots are unaffected
(their sustained already clears the floor). Both columns were already stored, so
**no migration, no re-fetch** — the payload just ships per-day `gusts[]` +
`winds[]` (index-aligned) and the blend is applied client-side.

**Effect (local full dataset, min 20 kt):**

| | gust-only | blend |
|---|---|---|
| Karpathos Dec | 20.0 | **14.0** → Langebaan (18.3) now wins ✓ |
| Karpathos Jan | 21.6 | 17.7 (narrowed; still edges Langebaan 14.3) |
| Langebaan (steady, any month) | — | **unchanged** |

December's anomaly is fixed. **January is improved but not flipped** — Karpathos
genuinely gets windy (if cold) Januaries with real sustained wind, so a wind-only
metric can't rank it below a warm-season spot. That residual is the
**temperature dimension**, deliberately deferred: the owner chose the sustained
floor over a temperature gate for now. A cold-but-windy month still ranks.

Files: `resources/js/Helpers/sailableDays.ts`,
`app/Http/Controllers/DestinationController.php` (payload now ships `gusts` +
`winds`), tests in `sailableDays.test.ts` (incl. a floor-exclusion case that
fails under pure-gust) + `DestinationSailablePayloadTest.php`. `CLAUDE.md`
metric note updated (`9a9fa57`).

## Also on trunk since the last reconcile
- **"Written by" band restyle** (PR #38) — the spot-guide author card's band
  moved from a dark-teal gradient to a calm cream background with the wave lines
  in primary / primary-darker blue (owner feedback: the dark band was too
  in-your-face). Presentational only.

## Test plan / verification
- PHP **253** pass, JS **29** pass, `npm run build` clean (blend PR).
- Blend arithmetic + `gusts`/`winds` index-alignment hand-verified in review;
  change fully encapsulated (no stale `SailableMonth.values` consumer).
- Mobile collapse verified via DOM at both breakpoints on a local build.

## Follow-ups
- **Temperature gate** for the sailable metric — would flip cold-but-windy
  months (e.g. Karpathos January) below warm-season spots. Deferred; the blend
  was chosen first. In `docs/TODO.md`.
- **Production data** — the earlier prod "Fetch all weather" only populated a
  few spots (suspected queue-worker timeout on the batched job); a full
  synchronous `php artisan weather:fetch` on Cloud is still owed, independent of
  the blend (which needs no re-fetch — both columns already exist).
- **`test` dummy spot** tops every month in the ranking — unpublish/remove it in
  production.
- Owner live-browser visual/dark/responsive pass on `/destinations` still owed
  (local `.test` blocked automation).
