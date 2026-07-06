# Search & Navigation UX — design

**Date:** 2026-07-05
**Branch:** `feat/search-nav-ux`
**Status:** approved design → implementation

## Problem

Three UX issues in `resources/js/Components/Common/NavBar.tsx` (reported by Ben):

1. **Search feels like it "doesn't search."** The desktop nav search is a bare input — no button, no hint, no live feedback. It *does* work (Enter → `/search?q=…` → correct results), but with no submit affordance or live results, typing "karpathos" appears to do nothing.
2. **Desktop search open is jarring.** The search bar is conditionally rendered (`{showSearch && …}`) with no transition, so it pops in and shoves the header down.
3. **Mobile menu animation is awkward.** The closed menu is parked at `translate-y-[calc(100vh+5rem)]` (a full viewport below) and flies up on open — a large, slow upward swoop.

Backend search is confirmed working (`SCOUT_DRIVER=database`, published Scout search across SpotGuides + Blogs). No backend search bug.

## Goals

- Make search feel responsive and obviously functional via **live results as you type**.
- Smooth, on-brand animations for the desktop search open and the mobile menu.
- Keep the existing "Editorial Coastal Cinema" look Ben is happy with — polish, not redesign.

## Non-goals

- Dark mode (separate greenfield track). Build with theme-friendly classes but don't verify dark mode here.
- Redesigning the `/search` results page (works fine).
- Changing the Scout driver or search relevance.

## Design

### A. `SiteSearch` service (backend, shared logic)

New `app/Services/SiteSearch.php` with:

```
search(string $query, ?int $limit = null): array
```

- Returns the normalized combined results array (spot guides + blogs), each `{type, title, slug, url, description, thumbnail}`.
- Published-only (reuses the `->query(fn) => where is_published` constraint).
- Under 2 chars → empty array.
- `$limit` caps each type (null = no cap, for the full page).

Refactor `SearchController@index` to use it (removes the inline duplication).

### B. `GET /api/search` endpoint (live suggestions)

- `routes/api.php`: `Route::get('/search', [Api\SearchController::class, 'index'])->name('api.search')`.
- `Api\SearchController@index(Request)` → `{ results: [...] }` JSON, `limit = 6`, via `SiteSearch`.
- Matches existing `Api\LiveWeatherController` / `Api\WeatherDataController` convention.

### C. `SearchPanel` component (frontend, extracted from NavBar)

New `resources/js/Components/Common/SearchPanel.tsx`. Owns the input + live dropdown; NavBar owns only open/close state and renders `<SearchPanel open={showSearch} onClose={…} />`.

- **Open animation:** container animates `max-height` + `opacity` + slight `translate-y` via CSS transition (~300ms ease-out) — no more pop-in. Always rendered; animates between states.
- **Live results:** debounced ~250ms; on 2+ chars, `axios.get(route('api.search'), { params: { q } })`; render dropdown of results (thumbnail, title, type badge). States: idle, loading (spinner), results, empty ("No results for …").
- **Keyboard:** ArrowUp/ArrowDown move a highlighted index; Enter on a highlighted item → navigate to its `url`; Enter with none highlighted → `router.get('/search', { q })` (full page); Esc → close.
- **Click:** result → navigate to `url`; outside click → close.

### D. Mobile menu — slide-down + staggered cascade

In NavBar:
- Replace the parked-below closed state with a **slide-down-from-header**: panel animates `translate-y(-8px → 0)` + `opacity(0 → 1)` when open; reverse on close.
- **Staggered links:** each nav link transitions in with `transition-delay: index * 50ms` (fade + small rise), so links cascade. Reset delays on close.
- **Backdrop:** subtle dim (`bg-black/40`) over page content below the header while open; click to close.
- Pure CSS transitions (no new animation lib; framer-motion is available but unnecessary here).

## Testing

- **TDD (backend):** `Api\SearchControllerTest` — published-only, min-length (<2 → empty), limit of 6 respected, both types returned, result shape. Reuse `collection` Scout driver in tests (per the established Scout test pattern).
- **Refactor safety:** existing `SearchControllerTest` (web page) must stay green after the `SiteSearch` extraction.
- **Frontend:** no JS test runner in the project → verify in the browser via the preview tools at mobile + desktop breakpoints: search open animation, live dropdown (type "kar" → Karpathos), keyboard nav, Esc/outside-click close, mobile menu cascade + backdrop.

## Files touched

- `app/Services/SiteSearch.php` (new)
- `app/Http/Controllers/Api/SearchController.php` (new)
- `app/Http/Controllers/SearchController.php` (refactor to use service)
- `routes/api.php` (new route)
- `resources/js/Components/Common/SearchPanel.tsx` (new)
- `resources/js/Components/Common/NavBar.tsx` (wire SearchPanel; mobile menu animation)
- `tests/Feature/Api/SearchControllerTest.php` (new)
