# Image Focal Points — design

**Date:** 2026-07-06
**Branch:** `feat/image-focal-points`
**Status:** approved design → implementation

## Problem

`object-cover` images crop to their centre by default. On the full-height mastheads this is badly visible on mobile (portrait): the destinations sunset shot crops the windsurfers and sun out of frame, though it looks great on desktop. The same centre-crop affects thumbnail cards, galleries, and content-block images. There's no way to tell an image "keep *this* part in view".

## Goal

Let an editor set a **focal point** per image; every cropped display of that image positions the crop around it, so the subject stays in frame at any screen size. No new infrastructure (pure CSS `object-position` + a stored point).

## Non-goals

- Server-side cropping / Spatie conversions / responsive `srcset` (separate "media pipeline" track; independent of this).
- Manual free-form crop boxes (Cropper.js) — focal point only.
- Object storage / AWS — irrelevant to focal points.
- Setting focal points for multi-select (gallery/slider) images from the picker — deferred; those default to centre.

## Design

### 1. Storage — focal point on the image
Migration adds to `media_library`:
- `focal_x` unsignedTinyInteger, default `50`
- `focal_y` unsignedTinyInteger, default `50`

(Percent 0–100; `50/50` = centre.) The point is a property of the image, so it's set once and honoured everywhere that image appears.

`MediaLibrary` model: add to `$fillable`; cast both to `integer`.

### 2. Backend — one image payload shape
Add a method to `MediaLibrary`:

```php
public function imagePayload(): array
{
    return [
        'url' => $this->getUrl(),
        'alt' => $this->name,
        'focal_x' => $this->focal_x ?? 50,
        'focal_y' => $this->focal_y ?? 50,
    ];
}
```

Every place that currently emits a bare image URL switches to this shape (an object, not a string):
- The 7 web controllers (`Homepage`, `SpotGuide`, `Destination`, `Blog`, `Page`, `Search`, `Contact` has none) wherever they expose a `thumbnail` / `static_masthead` / `og_image` / gallery / slider image.
- The `ResolvesContentBlockMedia` trait — each resolved content-block image becomes the same `{url, alt, focal_x, focal_y}` object (replacing the current `*_url` string), consumed by the block components via `<CoverImage>`. Exact per-block key names are pinned in the plan.

Image props therefore change from `"…/x.jpg"` → `{ url, alt, focal_x, focal_y }`. Front-end updated in lockstep (§3). A missing/null image stays `null`/`''` as today.

### 3. Front-end — one `<CoverImage>` component
New `resources/js/Components/Common/CoverImage.tsx`:

```tsx
interface FocalImage { url: string; alt?: string; focal_x?: number; focal_y?: number }
interface Props { image?: FocalImage | null; alt?: string; className?: string }
```

Renders `<img class="object-cover {className}" style={{ objectPosition: \`${focal_x ?? 50}% ${focal_y ?? 50}%\` }} …>` (renders nothing if no image). Every `object-cover` image surface renders through it: `StaticMasthead`, `MastheadSlider`, `Card`, `FeaturedGrid`, `Gallery`, `ImagePair`, `SplitImageText`, `ContentWithBackgroundImage`. Crop behaviour changes in one place.

### 4. Admin — set the focal point inline in the MediaPicker
On the single-select preview card (`media-picker.blade.php`): the image becomes clickable — clicking computes the click position as `x%/y%` within the image and calls a Livewire action that persists `focal_x/focal_y` onto that `MediaLibrary` row. A marker (small ring/dot) shows the current point; a short hint reads "Click the photo to set the focal point". Setting it updates the image everywhere it's used.

Implementation: the MediaPicker's action set includes a `setFocalPoint` handler (or the field's Livewire component gets a method) receiving `(mediaId, x, y)`; Alpine computes x/y from the click offset and calls `$wire`. Guard 0–100.

### 5. Testing
- **Unit:** `MediaLibrary` focal cast + `imagePayload()` returns the shape with focal (defaults 50/50 when unset).
- **Feature:** a representative controller (e.g. `SpotGuideController@show`) exposes `thumbnail`/`static_masthead` as the `{url, focal_x, focal_y}` shape; a `DestinationController`/`Blog` check for the same shape on card thumbnails.
- **Admin:** the MediaPicker focal-set action updates `focal_x/focal_y` on the `MediaLibrary` row (Filament/Livewire test).
- **Front-end:** no JS runner → browser-verify: set a focal point on the destinations masthead image, confirm mobile view keeps the subject in frame (`object-position` reflects the point).

## Files touched
- `database/migrations/xxxx_add_focal_point_to_media_library.php` (new)
- `app/Models/MediaLibrary.php` (fillable/cast + `imagePayload()`)
- `app/Http/Controllers/*` (image exposures → payload shape) + `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`
- `resources/js/Components/Common/CoverImage.tsx` (new) + the ~8 object-cover components
- `resources/views/filament/forms/components/media-picker.blade.php` + `app/Filament/Forms/Components/MediaPicker.php` (focal-set action)
- Tests: `tests/Unit/MediaLibraryTest.php`, controller feature tests, `tests/Feature/Filament/MediaPickerFocalTest.php`

## Rollout
Migration adds columns with a 50/50 default, so **existing images are unaffected** (centre-cropped as now) until an editor sets a point. Pure additive change.
