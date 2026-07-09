# Windsurfing-spot repeater: image field + drag-only ordering — Design

**Date:** 2026-07-09
**Status:** Approved (design agreed in conversation; pending spec review)

## Goal

Two small admin-UX fixes on the SpotGuide form's three repeaters (windsurfing
spots, hotels/stay, restaurants/eat):
1. Give **windsurfing spots** an image field (the stay/eat repeaters already
   have one; the model, controller, and public card already support it).
2. Remove the redundant manual **`sort_order` number input** from all three
   repeaters — drag-and-drop ordering already works via `->orderColumn('sort_order')`.

## Background

`app/Filament/Resources/SpotGuideResource.php` has three repeaters:
- `windsurfingLocations` — fields name/description/lat/lon + a
  `TextInput::make('sort_order')`; **no image picker**; `->orderColumn('sort_order')`.
- `stayRecommendations` / `eatRecommendations` — have a
  `MediaPicker::make('thumbnail_media_id')` **and** a redundant
  `TextInput::make('sort_order')`; `->orderColumn('sort_order')`.

Already in place (no change needed):
- `WindsurfingLocation` + `Recommendation` models have `thumbnail_media_id`
  (fillable) + a `thumbnailMedia` relation.
- `SpotGuideController@show` passes `thumbnail` (imagePayload) for windsurfing
  locations and recommendations.
- The public `RecommendationCards` component renders `thumbnail` (windsurfing
  locations use the same card), falling back to a dark panel when absent.
- Both `SpotGuide::recommendations()` and `SpotGuide::windsurfingLocations()`
  relations `->orderBy('sort_order')`, so the public page reflects the admin
  drag order.

So `->orderColumn('sort_order')` is **already** the drag-reorder mechanism; the
`TextInput` number box just duplicates it confusingly, and the windsurfing
repeater is the only one missing the image picker.

## Decisions

- **Add** `MediaPicker::make('thumbnail_media_id')->label('Image')` to the
  `windsurfingLocations` repeater (mirroring the stay/eat repeaters).
- **Remove** the `TextInput::make('sort_order')->numeric()->default(0)` from all
  three repeaters. Keep `->orderColumn('sort_order')` — Filament auto-writes the
  drag position into `sort_order`, so ordering still works and persists to the
  public page.
- No model/migration/controller/frontend change — those already support both.

## Scope & Components

Single file: `app/Filament/Resources/SpotGuideResource.php`.
- windsurfingLocations repeater schema: add the MediaPicker; remove the
  `sort_order` TextInput.
- stayRecommendations + eatRecommendations repeater schemas: remove the
  `sort_order` TextInput.

## Testing (TDD)

`tests/Feature/Filament/WindsurfingLocationImageTest.php` (new), driving the form
via `Livewire::test(CreateSpotGuide::class)`:
- Create a spot guide with **two** windsurfing locations, each given a
  `thumbnail_media_id` (a seeded `MediaLibrary` id) and **no** `sort_order` in the
  input. Assert:
  - both `windsurfing_locations` rows persist with the correct
    `thumbnail_media_id` (proves the new image field is wired — before the change
    the field isn't in the repeater schema, so it wouldn't save), and
  - the two rows have distinct `sort_order` values reflecting entry order (proves
    `orderColumn` still assigns order without the manual field).

Coordinates are required on the form (existing rule), so the test supplies valid
lat/lon; `Queue::fake()` to swallow the create-hook weather job. Full suite must
stay green.

Manual verification (panel): drag rows in each repeater and confirm the order
persists; set a windsurfing-spot image and confirm the card renders it.

## Out of Scope

- Any change to recommendations' existing image field, the public card markup,
  or the ordering relations (all already correct).

## Delivery

Branch `feat/windsurfing-repeater-image-ordering` (off `main`, independent of the
open SEO PR — no file overlap). TDD; folded reconcile; PR.
