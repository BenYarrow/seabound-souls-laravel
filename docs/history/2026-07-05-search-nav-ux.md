---
title: Search & navigation UX — live dropdown, animated search, staggered mobile menu
tags: [search, navigation, frontend, scout, api]
status: stable
completed: 2026-07-05
commits: [49774bc, effbe27, edbd507, 94d2b60, 7739d11, cf22eeb, 83d1602, 86c0760]
pr: 8
---

# Search & navigation UX

Reworked the nav search and menu after Ben flagged three issues: the search "didn't seem to search", the desktop search open was jarring, and the mobile menu had an awkward upward swoop. Built via brainstorm → spec → plan → subagent-driven execution.

## What shipped
- **`SiteSearch` service** (`app/Services/SiteSearch.php`) — shared published Scout search across spot guides + blogs, normalised to one typed result list; `search(query, ?limit)`. Both the web page and the API use it (no duplication).
- **`GET /api/search`** (`Api\SearchController`, route `api.search`) — live-suggestion JSON, capped 6/type. 4 TDD feature tests.
- **`SearchPanel`** component — animated slide-down (no pop-in), debounced (250ms) live dropdown with thumbnail + type badge, loading/empty states, keyboard nav (↑/↓/Enter/Esc), in-flight request cancellation via `AbortController`.
- **NavBar** — wired in `SearchPanel`; mobile menu now slides down from the header with a **staggered link cascade** (`transitionDelay: index*60ms`) + dim backdrop, replacing the full-viewport upward swoop.

## Findings worth keeping
- **The search was never broken** — it worked on Enter. The real problem was UX: a bare input with no button, no live feedback, and no submit affordance read as "nothing happens". Live results fixed the perception.
- **Scout test pattern reused:** suite runs `SCOUT_DRIVER=null`; search tests opt into `collection` in `setUp` (established in the earlier search slice).
- **`SearchPanel` uses raw `bg-white`/`gray-*`** — deliberately deferred to the future dark-mode token sweep (spec non-goal); matches the rest of the codebase, which has no token layer yet.
- **`/api/search` is unthrottled** — pre-existing project posture (sibling `/api/*` endpoints too), but this is the first *typing-driven* endpoint, so request volume is higher. Added to the backlog to throttle before production.

## Test plan
`php artisan test` → 45 passed, 323 assertions. Browser-verified (controller): desktop live dropdown returns Karpathos, ArrowDown highlights + Enter navigates to the guide, mobile menu cascades down with backdrop, no console errors.

## Process
Brainstorm spec: `docs/superpowers/specs/2026-07-05-search-nav-ux-design.md`. Plan: `docs/superpowers/plans/2026-07-05-search-nav-ux.md`. Executed via subagent-driven development (implementer + reviewer per task, final whole-branch review — all clean).

## Follow-ups
See `docs/TODO.md`: rate-limit `/api/search`; dark-mode sweep now also covers `SearchPanel`.
