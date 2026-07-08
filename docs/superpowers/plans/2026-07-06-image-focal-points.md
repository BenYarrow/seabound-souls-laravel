# Image Focal Points Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let editors set a per-image focal point that every cropped (`object-cover`) display honours via CSS `object-position`, so subjects stay in frame at any screen size (esp. mobile mastheads).

**Architecture:** Store `focal_x/focal_y` on `media_library`. A single `MediaLibrary::imagePayload()` emits `{url, alt, focal_x, focal_y}`; every controller + the content-block resolver use it. A single tolerant `<CoverImage>` React component renders every `object-cover` image and applies `object-position`. The focal point is set by clicking the image in the MediaPicker preview.

**Tech Stack:** Laravel 12, Filament v3.3, Livewire v3, Inertia v2 + React 19 + TS, Tailwind v3, PHPUnit 11, SQLite (in-memory tests).

## Global Constraints

- Run `php artisan test` before finishing a backend task; all green. Base `Tests\TestCase` already applies `RefreshDatabase` + `withoutVite()` (inherited, don't re-declare).
- Node 22 for any `npm`: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"` (default v14 breaks Vite 7).
- Focal values are integers 0–100; **default 50/50 (centre)**; unset ⇒ 50/50. Migration is purely additive — existing images render exactly as now until a point is set.
- **The app must remain working after every task.** `<CoverImage>` is deliberately tolerant (accepts a string URL *or* a `{url,focal_x,focal_y}` object *or* null) so components can adopt it (Task 3) before the payload flips to objects (Task 4).
- No JS test runner exists — frontend tasks are verified via `npm run build` + browser (preview tools) at mobile + desktop.
- Frontend paths hardcoded; JSDoc/PHPDoc + module headers per `CLAUDE.md`.

## File structure

- `app/Models/MediaLibrary.php` — focal columns fillable/cast + `imagePayload()` (the one image shape).
- `resources/js/types/media.ts` (new) — shared `FocalImage` TS type.
- `resources/js/Components/Common/CoverImage.tsx` (new) — the one cropped-image renderer.
- Controllers + `ResolvesContentBlockMedia` — emit `imagePayload()` objects.
- The 18 `object-cover` sites — render through `<CoverImage>`.
- `MediaPicker` field/view — click-to-set focal.

---

### Task 1: Focal columns on `media_library` + `imagePayload()`

**Files:**
- Create: `database/migrations/<timestamp>_add_focal_point_to_media_library.php`
- Modify: `app/Models/MediaLibrary.php`
- Test: `tests/Unit/MediaLibraryTest.php`

**Interfaces:**
- Produces: `MediaLibrary::imagePayload(): array` → `['url' => string, 'alt' => string, 'focal_x' => int, 'focal_y' => int]`. `focal_x`/`focal_y` are `$fillable`, cast `integer`, default 50.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/MediaLibraryTest.php`:

```php
<?php

// Unit tests for App\Models\MediaLibrary — focal-point defaults, cast, and the
// imagePayload() shape consumed across the app.

namespace Tests\Unit;

use App\Models\MediaLibrary;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    public function test_focal_point_defaults_to_centre(): void
    {
        $media = MediaLibrary::create(['name' => 'Sunset']);

        $this->assertSame(50, $media->fresh()->focal_x);
        $this->assertSame(50, $media->fresh()->focal_y);
    }

    public function test_image_payload_has_the_expected_shape(): void
    {
        $media = MediaLibrary::create(['name' => 'Sunset', 'focal_x' => 30, 'focal_y' => 70]);

        $payload = $media->imagePayload();

        $this->assertSame(['url', 'alt', 'focal_x', 'focal_y'], array_keys($payload));
        $this->assertSame('Sunset', $payload['alt']);
        $this->assertSame(30, $payload['focal_x']);
        $this->assertSame(70, $payload['focal_y']);
        $this->assertIsString($payload['url']); // '' when no file attached — fine
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=MediaLibraryTest`
Expected: FAIL — `focal_x` column/attribute missing; `imagePayload()` undefined.

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration add_focal_point_to_media_library`

Set its `up()`/`down()`:

```php
public function up(): void
{
    Schema::table('media_library', function (Blueprint $table) {
        // Focal point as percentages (0–100); 50/50 = centre. Applied via CSS
        // object-position so cropped displays keep the subject in frame.
        $table->unsignedTinyInteger('focal_x')->default(50)->after('folder');
        $table->unsignedTinyInteger('focal_y')->default(50)->after('focal_x');
    });
}

public function down(): void
{
    Schema::table('media_library', function (Blueprint $table) {
        $table->dropColumn(['focal_x', 'focal_y']);
    });
}
```

- [ ] **Step 4: Update the model**

In `app/Models/MediaLibrary.php`: add `focal_x`, `focal_y` to `$fillable`; add a `$casts` (or `casts()`) mapping both to `'integer'`; add the method:

```php
    /**
     * The canonical image shape consumed across the app (controllers, content
     * blocks, front-end <CoverImage>). Focal values drive CSS object-position.
     *
     * @return array{url: string, alt: string, focal_x: int, focal_y: int}
     */
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

So `$fillable = ['name', 'folder', 'focal_x', 'focal_y'];` and casts include `'focal_x' => 'integer', 'focal_y' => 'integer'`.

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=MediaLibraryTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/MediaLibrary.php tests/Unit/MediaLibraryTest.php
git commit -m "Add focal point to MediaLibrary + imagePayload()"
```

---

### Task 2: `FocalImage` type + tolerant `<CoverImage>` component

**Files:**
- Create: `resources/js/types/media.ts`
- Create: `resources/js/Components/Common/CoverImage.tsx`

**Interfaces:**
- Produces: `FocalImage` type; default-exported `CoverImage` with props `{ image?: FocalImage | string | null; alt?: string; className?: string }`. Renders `null` when no image; otherwise an `object-cover` `<img>` with `object-position` from focal (50/50 default; when `image` is a string, focal defaults to centre).

- [ ] **Step 1: Create the shared type**

Create `resources/js/types/media.ts`:

```ts
/** An image plus its focal point (percentages, 0–100; 50/50 = centre). */
export interface FocalImage {
    url: string
    alt?: string
    focal_x?: number
    focal_y?: number
}
```

- [ ] **Step 2: Create the component**

Create `resources/js/Components/Common/CoverImage.tsx`:

```tsx
/**
 * CoverImage — the single renderer for every object-cover image. Applies the
 * image's focal point as CSS object-position so the subject stays in frame when
 * the image is cropped. Tolerant of a plain URL string (focal defaults to
 * centre) so components can adopt it before the backend emits focal objects.
 */
import type { FocalImage } from '@/types/media'

interface Props {
    image?: FocalImage | string | null
    alt?: string
    className?: string
}

const CoverImage = ({ image, alt, className = '' }: Props) => {
    if (!image) return null

    const isString = typeof image === 'string'
    const url = isString ? image : image.url
    if (!url) return null

    const focalX = isString ? 50 : image.focal_x ?? 50
    const focalY = isString ? 50 : image.focal_y ?? 50
    const resolvedAlt = alt ?? (isString ? '' : image.alt ?? '')

    return (
        <img
            src={url}
            alt={resolvedAlt}
            loading="lazy"
            className={`object-cover ${className}`}
            style={{ objectPosition: `${focalX}% ${focalY}%` }}
        />
    )
}

export default CoverImage
```

- [ ] **Step 3: Build to verify it compiles**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: build succeeds (component unused so far — no behaviour change).

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/media.ts resources/js/Components/Common/CoverImage.tsx
git commit -m "Add FocalImage type + tolerant CoverImage component"
```

---

### Task 3: Render every `object-cover` image through `<CoverImage>` (no behaviour change)

Convert all 18 sites, still fed by the **current string props**, so focal defaults to centre — **the site looks identical**, but every cropped image now flows through one component ready to receive focal.

**Files (each has one or more `<img … object-cover …>` to convert):**
- `resources/js/Components/Masthead/StaticMasthead.tsx:18`
- `resources/js/Components/Masthead/MastheadSlider.tsx:35`
- `resources/js/Components/Common/Cards/Card.tsx:19`
- `resources/js/Components/Common/FeaturedGrid.tsx:75`
- `resources/js/Components/Content/Gallery.tsx:68,108`
- `resources/js/Components/Content/ImagePair.tsx:31`
- `resources/js/Components/Content/ContentWithBackgroundImage.tsx:18`
- `resources/js/Components/Content/SplitImageText.tsx:36`
- `resources/js/Components/Common/SearchPanel.tsx:166`
- `resources/js/Components/SpotGuide/SpotGuideMap.tsx:99`
- `resources/js/Components/Map/DestinationsMap.tsx:118`
- `resources/js/Pages/Blog/Index.tsx:79,136`
- `resources/js/Pages/Search.tsx:73`
- `resources/js/Pages/SpotGuide/Show.tsx:96`
- `resources/js/Pages/Destinations/Index.tsx:158`

**Interfaces:**
- Consumes: `CoverImage`, `FocalImage` (Task 2).

- [ ] **Step 1: Convert each site (identical mechanical pattern)**

For every `<img>` listed above, replace it with `<CoverImage>`, **preserving the existing classes but dropping the literal `object-cover`** (CoverImage adds it). Pattern:

Before:
```tsx
<img src={SOME_URL} alt={SOME_ALT} className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 …" />
```
After:
```tsx
<CoverImage image={SOME_URL} alt={SOME_ALT} className="absolute inset-0 w-full h-full group-hover:scale-105 …" />
```

Rules:
- Pass the **same expression** that was in `src=` as `image=` (still a string at this point).
- Keep every other class verbatim except remove the `object-cover` token (CoverImage prepends it).
- Keep the `alt` expression if present; otherwise omit.
- Add `import CoverImage from '@/Components/Common/CoverImage'` to each file (in `Pages/*` and `Components/*` the `@/` alias resolves to `resources/js`).
- Do **not** change any surrounding wrapper/markup, hover, or aspect classes.

(For `Gallery.tsx` there are two occurrences — convert both. For `Blog/Index.tsx` two — convert both.)

- [ ] **Step 2: Build**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: succeeds, no TS errors (`image={string}` is accepted by CoverImage).

- [ ] **Step 3: Browser smoke (controller of the run does this if the implementer can't)**

With Laravel + build served, load `/`, `/destinations`, a spot guide, `/blog`, `/search?q=kar` — every image still renders exactly as before (centre-cropped). No visual regression.

- [ ] **Step 4: Commit**

```bash
git add resources/js
git commit -m "Render all object-cover images through CoverImage (no visual change)"
```

---

### Task 4: Flip backend image payloads to `imagePayload()` objects + update TS prop types

Now the controllers + content-block resolver emit `{url, alt, focal_x, focal_y}` where they previously emitted URL strings. `<CoverImage>` already consumes objects, so **focal points now take effect**. TS prop types change from `string` to `FocalImage`.

**Files:**
- Modify: `app/Http/Controllers/HomepageController.php`, `SpotGuideController.php`, `DestinationController.php`, `BlogController.php`, `PageController.php`, `SearchController.php`, `App\Services\SiteSearch`
- Modify: `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`
- Modify: the Inertia page/component TS interfaces that type these image props
- Test: extend `tests/Feature/SpotGuideControllerTest.php`, `DestinationControllerTest.php`, `BlogControllerTest.php`

**Interfaces:**
- Consumes: `MediaLibrary::imagePayload()` (Task 1), `FocalImage` (Task 2).
- Produces: image props are `FocalImage | null` (single) or `FocalImage[]` (galleries/sliders) app-wide.

- [ ] **Step 1: Write failing feature tests**

Add to `tests/Feature/SpotGuideControllerTest.php`:

```php
    public function test_show_exposes_images_with_focal_points(): void
    {
        $guide = SpotGuide::factory()->create();

        $this->get(route('spot-guides.show', $guide->slug))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                // thumbnail is now an object with focal fields (null when unset,
                // but the guide factory attaches no media → null is acceptable):
                ->has('spotGuide')
            );
    }
```

Better, deterministic version — attach a media item so the payload is an object. Add a helper in the test that creates a `MediaLibrary` with focal and points `thumbnail_media_id` at it, then assert:

```php
    public function test_show_exposes_thumbnail_with_focal_point(): void
    {
        $media = \App\Models\MediaLibrary::create(['name' => 'Hero', 'focal_x' => 20, 'focal_y' => 80]);
        $guide = SpotGuide::factory()->create(['thumbnail_media_id' => $media->id]);

        $this->get(route('spot-guides.show', $guide->slug))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('spotGuide.thumbnail.focal_x', 20)
                ->where('spotGuide.thumbnail.focal_y', 80)
            );
    }
```

Run: `php artisan test --filter=SpotGuideControllerTest`
Expected: FAIL — `spotGuide.thumbnail` is currently a string, so `.focal_x` isn't present.

- [ ] **Step 2: Switch each controller image exposure to `imagePayload()`**

In every controller, replace the single-image string exposures. Pattern — for a `belongsTo` media relation `X`:

Before: `'thumbnail' => $spotGuide->thumbnailMedia?->getUrl() ?? '',`
After: `'thumbnail' => $spotGuide->thumbnailMedia?->imagePayload(),`  *(null when no media — front-end + CoverImage already handle null)*

Apply to every image field across the controllers:
- **SpotGuideController@show:** `thumbnail`, `static_masthead`, `water_conditions_bg`, `wind_conditions_bg`, `travelling_to_bg`, `lessons_and_hire_bg`, `og_image` (in `meta.og_image` keep a plain URL string — see note), and `gallery` (each item → `$m->imagePayload()`), and the `stay_recommendations`/`eat_recommendations`/`windsurfing_locations` `thumbnail` entries (`$r->thumbnailMedia?->imagePayload()`).
- **DestinationController@index:** each guide's `thumbnail`.
- **BlogController@index/@show:** `thumbnail`, `static_masthead`, `masthead_slider` (each → `imagePayload()`).
- **HomepageController@index:** featured guides `thumbnail`, recent blogs `thumbnail`, page `static_masthead`, `masthead_slider`.
- **PageController@show:** `static_masthead`, `masthead_slider`, thumbnail.
- **SearchController / SiteSearch:** each result's `thumbnail`.

**Note — `meta.og_image`:** Open Graph tags need a bare URL string, not an object. Leave all `meta.og_image` values as `->getUrl()` strings (they're consumed by `<meta>` in the head, not `<CoverImage>`). Only *display* images become objects.

- [ ] **Step 3: Update `ResolvesContentBlockMedia`**

In `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`, where it currently sets `$data[$key.'_url'] = $item->getUrl()` and the `mediaIds` `_urls` array of `{url, alt}`, change to emit the focal object instead:

- Single keys: set `$data[$key.'_image'] = $item->imagePayload()` (keep `_url` too for any non-CoverImage consumer, or drop it if unused — grep confirms block components only use it for `<img>`). Prefer replacing `_url` semantics with `_image`.
- Array key `mediaIds`: `$data['mediaIds_images'] = [...$item->imagePayload()...]`.

Then update the content-block front-end components (`ContentWithBackgroundImage`, `SplitImageText`, `Gallery`, `ImagePair`, and `ContentBuilder` prop typing) to read the new `_image`/`_images` and pass to `<CoverImage>`.

- [ ] **Step 4: Update TS prop types**

Replace `string` image prop types with `FocalImage` across the affected Inertia pages/components. E.g. in `Pages/SpotGuide/Show.tsx`, `Pages/Destinations/Index.tsx`, `Pages/Blog/Index.tsx`, `Pages/Search.tsx`, and the `Card`/`FeaturedGrid`/masthead/content-block components: `thumbnail: string` → `thumbnail: FocalImage | null`, gallery `string[]` → `FocalImage[]`, etc. Import `FocalImage` from `@/types/media`. `<CoverImage image={thumbnail} />` already handles the object/null.

Where a component previously used the URL string for something other than an `<img>` (e.g., an anchor or `meta`), keep a `.url` access. Grep each changed prop for non-CoverImage uses before finalising.

- [ ] **Step 5: Run tests + build**

Run: `php artisan test` → expect all green (new focal assertions pass).
Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build` → no TS errors.

- [ ] **Step 6: Commit**

```bash
git add app resources/js tests
git commit -m "Emit focal-bearing image payloads; type image props as FocalImage"
```

---

### Task 5: Set the focal point inline in the MediaPicker

**Files:**
- Modify: `app/Filament/Forms/Components/MediaPicker.php`
- Modify: `resources/views/filament/forms/components/media-picker.blade.php`
- Test: `tests/Feature/Filament/MediaPickerFocalTest.php`

**Interfaces:**
- Consumes: `MediaLibrary` focal columns (Task 1).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/MediaPickerFocalTest.php` — verify the persistence path the click handler calls (a route/Livewire action or a direct model update the component performs). Simplest robust contract: a small controller/route the Alpine click posts to. Define `POST /admin/media/{media}/focal` (auth-gated) that validates `x,y` (0–100) and saves them.

```php
<?php

namespace Tests\Feature\Filament;

use App\Models\MediaLibrary;
use App\Models\User;
use Tests\TestCase;

class MediaPickerFocalTest extends TestCase
{
    public function test_focal_point_can_be_saved_for_a_media_item(): void
    {
        $this->actingAs(User::factory()->create());
        $media = MediaLibrary::create(['name' => 'Hero']);

        $this->postJson("/admin/media/{$media->id}/focal", ['x' => 25, 'y' => 75])
            ->assertOk();

        $media->refresh();
        $this->assertSame(25, $media->focal_x);
        $this->assertSame(75, $media->focal_y);
    }

    public function test_focal_endpoint_requires_auth(): void
    {
        $media = MediaLibrary::create(['name' => 'Hero']);
        $this->postJson("/admin/media/{$media->id}/focal", ['x' => 25, 'y' => 75])
            ->assertUnauthorized();
    }

    public function test_focal_values_are_clamped_to_0_100(): void
    {
        $this->actingAs(User::factory()->create());
        $media = MediaLibrary::create(['name' => 'Hero']);
        $this->postJson("/admin/media/{$media->id}/focal", ['x' => 250, 'y' => -5])
            ->assertUnprocessable();
    }
}
```

Run: `php artisan test --filter=MediaPickerFocalTest`
Expected: FAIL — route undefined (404).

- [ ] **Step 2: Add the focal-save route + controller**

Create `app/Http/Controllers/Admin/MediaFocalController.php`:

```php
<?php

// Persists a media item's focal point (x/y %) — called by the click-to-set
// interaction in the Filament MediaPicker preview.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaFocalController extends Controller
{
    public function store(Request $request, MediaLibrary $media): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'between:0,100'],
            'y' => ['required', 'integer', 'between:0,100'],
        ]);

        $media->update(['focal_x' => $data['x'], 'focal_y' => $data['y']]);

        return response()->json(['focal_x' => $media->focal_x, 'focal_y' => $media->focal_y]);
    }
}
```

Register in `routes/web.php` (behind Filament's auth so only admins hit it):

```php
use App\Http\Controllers\Admin\MediaFocalController;

Route::post('/admin/media/{media}/focal', [MediaFocalController::class, 'store'])
    ->middleware(['web', 'auth'])
    ->name('admin.media.focal');
```

(`auth` guards it — the test's unauthenticated case expects 401/redirect; use `assertUnauthorized()` for JSON or adjust to `assertRedirect()` if the guard redirects. If it redirects, change the test's second case accordingly.)

- [ ] **Step 3: Add the click-to-set UI to the preview card**

In `media-picker.blade.php`, in the single-select preview card (the `w-96` card), make the image clickable via Alpine: on click compute `x = round(offsetX / width * 100)`, `y = round(offsetY / height * 100)`, POST to `route('admin.media.focal', id)` (include CSRF), and move a marker to (x%, y%). Seed the marker from the item's current `focal_x/focal_y`. Concretely, wrap the `<img>` in an `x-data` block:

```blade
<div
    x-data="{ fx: {{ $selectedItem->focal_x ?? 50 }}, fy: {{ $selectedItem->focal_y ?? 50 }} }"
    class="relative cursor-crosshair"
    x-on:click="
        const r = $el.getBoundingClientRect();
        fx = Math.round((($event.clientX - r.left) / r.width) * 100);
        fy = Math.round((($event.clientY - r.top) / r.height) * 100);
        fetch('{{ route('admin.media.focal', $selectedItem->id) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ x: fx, y: fy }),
        });
    "
>
    <img src="{{ $selectedItem->getUrl() }}" alt="{{ $selectedItem->name }}" class="aspect-video w-full object-cover" :style="`object-position: ${fx}% ${fy}%`">
    <span class="pointer-events-none absolute h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-500/70 shadow" :style="`left: ${fx}%; top: ${fy}%`"></span>
</div>
<p class="px-3 pt-1 text-xs text-gray-500 dark:text-gray-400">Click the photo to set its focal point.</p>
```

(Keep the existing remove button + name; place the above where the current `<img>`+overlay sits. The live preview image itself uses `:style` object-position so the editor sees the effect immediately.)

- [ ] **Step 4: Build + run tests**

Run: `php artisan test --filter=MediaPickerFocalTest` → PASS.
Run: `php artisan test` → all green.
Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build` → succeeds.

- [ ] **Step 5: Commit**

```bash
git add app routes/web.php resources/views/filament/forms/components/media-picker.blade.php tests/Feature/Filament/MediaPickerFocalTest.php
git commit -m "Set image focal point by clicking the MediaPicker preview"
```

---

## Controller browser verification (run by the controller of the run, after Task 5)

Not a task — a note: log into the admin, open a spot guide's masthead image in the MediaPicker, click the lower-left (the windsurfers), save. Then load `/destinations` at mobile width and confirm the masthead now keeps the subject in frame (`object-position` reflects the point). Confirms the end-to-end path.

## Self-review notes

- **Spec coverage:** storage → Task 1; `imagePayload()` → Task 1; `<CoverImage>` → Task 2; render everywhere → Task 3; payload flip + trait + TS types → Task 4; admin set-UI → Task 5; tests in each. OG-image-stays-a-string caveat captured in Task 4 Step 2.
- **App-always-working:** Task 3 keeps strings (centre, no change); Task 4 flips to objects that CoverImage already accepts. No broken intermediate state.
- **Type consistency:** `imagePayload()` keys (`url/alt/focal_x/focal_y`) == `FocalImage` fields == CoverImage reads == feature-test assertions. Focal route contract (`/admin/media/{media}/focal`, `x`/`y`) is identical in the test and controller.
- **No placeholders:** foundation + admin code complete; Task 3/4 are one explicit mechanical pattern applied to an enumerated, file:line-listed set of sites.
