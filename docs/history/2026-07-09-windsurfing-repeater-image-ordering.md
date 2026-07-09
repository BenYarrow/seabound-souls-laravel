---
title: Windsurfing-spot repeater — image field + drag-only ordering
tags: [filament, admin-ux, spot-guide, media]
status: stable
completed: 2026-07-09
commits: [85deafa, 1b94745]
pr: 22
---

# Windsurfing repeater: image + drag ordering

## What shipped

Two small admin-UX fixes on the SpotGuide form's three repeaters:

- **Windsurfing spots gained an image field** — a
  `MediaPicker::make('thumbnail_media_id')` (the stay/eat repeaters already had
  one). The `WindsurfingLocation` model, `SpotGuideController@show`, and the
  public `RecommendationCards` component already supported the image; only the
  form lacked the picker.
- **Removed the redundant manual `sort_order` number input** from all three
  repeaters. Ordering was never manual — `->orderColumn('sort_order')` already
  makes each repeater drag-reorderable and persists the drag position into
  `sort_order`. The `TextInput` just duplicated it confusingly.

## Findings worth keeping

- **`->orderColumn('sort_order')` IS the drag-reorder mechanism** in Filament v3
  — it makes the repeater reorderable and writes each row's position to the
  column on save. A manual `TextInput` bound to the same column is redundant (and
  its value is overwritten by the position on save).
- **The public page already reflects the admin order** because both
  `SpotGuide::recommendations()` and `SpotGuide::windsurfingLocations()` relations
  `->orderBy('sort_order')`. No frontend/controller change was needed for either
  the image or the ordering.
- **The whole feature was one file** (`SpotGuideResource.php`) — everything
  downstream (model column, controller payload, card rendering) was already in
  place; the gap was purely the admin form.

## Test plan

TDD. `tests/Feature/Filament/WindsurfingLocationImageTest.php` drives the real
form via `Livewire::test(CreateSpotGuide::class)->fillForm([...])->call('create')`:
creates a spot guide with two windsurfing locations carrying `thumbnail_media_id`
(and no manual `sort_order`), then asserts both persist with their image and get
distinct `sort_order` by entry position. Red before the picker was added (image
not collected), green after. Suite green.

Manual: in the panel, drag rows in each repeater to confirm the order persists,
and set a windsurfing-spot image to confirm the card renders it.

Spec: `docs/superpowers/specs/2026-07-09-windsurfing-repeater-image-ordering-design.md`.
