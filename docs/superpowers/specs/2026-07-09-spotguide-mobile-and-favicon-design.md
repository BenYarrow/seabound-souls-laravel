# Spot-guide mobile layout + favicon — Design

**Date:** 2026-07-09
**Status:** Approved (pending spec review)

## Goal

Two post-launch polish items:
1. Fix the spot-guide masthead on mobile — the `SpotOverview` and
   `LiveWeatherData` currently pile on top of the image (cramped/illegible).
2. Add the branded favicon (the site currently has an empty `public/favicon.ico`
   and no favicon `<link>`).

## Background

`Pages/SpotGuide/Show.tsx` passes both `SpotOverview` and `LiveWeatherData` as
**children of `StaticMasthead`**, which is a fixed-height box with the image
positioned `absolute inset-0`. On desktop the children are absolutely positioned
(right sidebar / bottom-left card) and overlay the image correctly. On mobile
they are normal-flow, so they stack at the **top of the masthead over the
image** — the reported problem. `LiveWeatherData` already has a full-width mobile
variant, but as normal flow it lands in the wrong place.

`public/favicon.ico` is 0 bytes; `resources/views/app.blade.php` has no favicon
link. The branded icon exists in the sibling reference repo at
`../seabound-souls-sanity-next-js/src/app/favicon.ico` (the user explicitly
pointed here, so reading that one file is authorised for this task).

## Decisions

- **Live weather stays over the image at every breakpoint** (user preference).
  On mobile it becomes a full-width bar pinned to the *bottom of the image*
  (`absolute bottom-0 inset-x-0`), not pushed below.
- **Only `SpotOverview` moves below the image on mobile.**
- **Desktop must be pixel-identical** — no visual change at `lg` and up.
- Render each component **once** (no duplicated mobile copy) so the weather API
  is not called twice.

## Scope & Components

### 1. `Pages/SpotGuide/Show.tsx`
Wrap the masthead + overview in a single `relative` div. Keep
`<LiveWeatherData>` as a **child of `StaticMasthead`** (overlays the image at all
breakpoints). Move `<SpotOverview>` to be a **sibling** of the masthead inside
the `relative` wrapper:
- Desktop: `SpotOverview`'s existing `lg:absolute lg:right-0 lg:top-0 lg:h-full`
  now positions against the wrapper, whose height equals the masthead height on
  desktop (the overview is `absolute` there, adding no height) — so it overlays
  the masthead's right edge exactly as before.
- Mobile: `SpotOverview` is normal flow → renders below the image.

### 2. `Components/Common/LiveWeatherData.tsx`
Mobile variant (`lg:hidden`, both the skeleton and the loaded state): change from
normal-flow `w-full` to `absolute bottom-0 inset-x-0 w-full` so the bar overlays
the bottom of the image. Desktop variant unchanged.

### 3. `Components/Common/SpotOverview.tsx`
- Add a dark background to the **mobile** wrapper (`max-lg:bg-secondary`) so the
  white icons/text remain legible now that the block sits below the image on the
  page background. (Desktop keeps `lg:bg-secondary/90`.)
- Hide the desktop expand-chevron button on mobile (`max-lg:hidden` / `lg:flex`)
  — it only drives the desktop collapsible sidebar.

### 4. `StaticMasthead.tsx`
No change. It still receives `LiveWeatherData` as a child, so the existing
`children ?` branch keeps the spot-guide title centred and suppresses the scroll
indicator. (Coordinates are required on spot guides, so a `LiveWeatherData`
element is always passed.)

### 5. Favicon
- Copy `../seabound-souls-sanity-next-js/src/app/favicon.ico` →
  `public/favicon.ico` (replacing the empty file).
- Add to `resources/views/app.blade.php` `<head>`:
  `<link rel="icon" href="/favicon.ico" sizes="any">`.

## Error Handling / Edge Cases

- Spot guide with no `spot_overview`: `SpotOverview` already returns `null` — the
  wrapper simply shows the masthead. Fine.
- Weather fetch failure: `LiveWeatherData` returns `null` at runtime, but the
  element is still passed as a child, so the centred-title branch is unaffected.

## Testing

Responsive CSS and a favicon are not unit-testable; the backend is untouched so
the PHPUnit suite stays green (run `php artisan test` to confirm). Verification is
in the browser preview:
- **Desktop (lg+):** spot-guide masthead pixel-identical — right sidebar overview,
  bottom-left weather card.
- **Mobile:** image with the full-width weather bar over its bottom edge, then the
  overview block (dark bg, legible) stacked below; no chevron.
- **Tablet:** sensible intermediate (overview below, weather bar over image).
- **Favicon:** the branded icon shows in the browser tab.

## Out of Scope

- Any desktop restyle.
- PNG/apple-touch-icon/manifest variants — the sibling provides only `.ico`;
  a single `favicon.ico` is sufficient.

## Delivery

Branch `feat/spotguide-mobile-and-favicon`; two commits (layout, favicon);
inline execution with preview verification; folded reconcile before merge; PR.
