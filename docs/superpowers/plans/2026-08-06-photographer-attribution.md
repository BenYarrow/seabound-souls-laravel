# Photographer Attribution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Credit supplied photography wherever an image appears on the site, managed entirely from the admin, with a public photographer profile page that can be switched on later without any code changes.

**Architecture:** A standalone `photographers` table (no auth) referenced by `media_library.photographer_id`. Credit data enters the front end through the single existing serialisation choke point, `MediaLibrary::imagePayload()`, and is rendered by the single existing image renderer, `CoverImage`. Page visibility is derived from whether the record has content, so no empty page can go live.

**Tech Stack:** Laravel 12, Filament v3, Inertia v2 + React 19, Tailwind v3, PHPUnit (SQLite in-memory), Vitest (node environment).

**Spec:** [`docs/superpowers/specs/2026-08-06-photographer-attribution-design.md`](../specs/2026-08-06-photographer-attribution-design.md)

## Global Constraints

- **Never use anything from IFP** — no Company Memory, no IFP skills, no IFP packages. See `CLAUDE.md` "STRICT: nothing from IFP, ever".
- **Node 22 for all npm/vite commands:** `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"`. The shell default is Node v14 and cannot run Vite 7.
- **TDD:** write the failing test, run it, see it fail, then implement. Every task below is ordered this way.
- **`php artisan test` must pass before any task is considered done.** Tests run on in-memory SQLite; no Postgres needed.
- **Dark mode + theme tokens:** no raw `bg-white` / `text-gray-*` / palette-specific utilities in new UI. Use the project's semantic classes (`bg-cream`, `text-secondary`, `text-primary`, etc.).
- **Responsive:** verify new UI at mobile, tablet and desktop.
- **Documentation:** module header comment at the top of every new file; JSDoc on every `.ts`/`.tsx` function; PHPDoc where non-obvious. No single-letter variables, no cryptic abbreviations.
- **Branch:** work continues on `feature/photographer-attribution`. Commit after every task.
- **Migrations are additive only** — never edit a migration that has already run.

---

## File Structure

**Created**

| File | Responsibility |
|---|---|
| `database/migrations/2026_08_06_100000_create_photographers_table.php` | The table + partial-unique slug index |
| `database/migrations/2026_08_06_100010_add_photographer_id_to_media_library_table.php` | The credit FK |
| `app/Models/Photographer.php` | Slug generation, `hasPublicPage()`, `creditPayload()` |
| `database/factories/PhotographerFactory.php` | Test data |
| `app/Policies/PhotographerPolicy.php` | Owner-only gate |
| `app/Filament/Forms/PhotographerProfileForm.php` | Shared form schema (the future self-edit page reuses this) |
| `app/Filament/Resources/PhotographerResource.php` + `Pages/` | Admin CRUD |
| `app/Http/Controllers/PhotographerController.php` | Public profile page |
| `resources/js/Pages/Photographers/Show.tsx` | Public profile page view |
| `resources/js/Helpers/imageCredit.ts` | Pure credit-link resolution (unit-tested) |
| `resources/js/Helpers/imageCredit.test.ts` | Its tests |
| `resources/js/Components/Common/ImageCredit.tsx` | The credit badge |
| `resources/js/Components/Content/PhotographerRollUp.tsx` | `list_photographers` block |
| `tests/Feature/Photographer*.php`, `tests/Feature/MediaLibraryScopingTest.php` | Coverage |

**Modified**

| File | Change |
|---|---|
| `app/Models/MediaLibrary.php` | `photographer()` relation, `$with`, `credit` in `imagePayload()` |
| `app/Filament/Resources/MediaLibraryResource.php` | Photographer select/column/filter/bulk action + scoping hardening |
| `resources/js/Components/Common/CoverImage.tsx` | Wrapper + credit badge + `showCredit` opt-out |
| `resources/js/types/media.ts` | `credit` on `FocalImage` |
| `resources/js/Components/Content/SingleImage.tsx` | Raw `<img>` → `CoverImage` |
| `resources/js/Components/Content/Gallery.tsx` | Lightbox credit via custom sources |
| `resources/js/Components/Map/DestinationsMap.tsx`, `SpotGuide/SpotGuideMap.tsx` | `showCredit={false}` |
| `routes/web.php` | `/photographers/{slug}` above the catch-all |
| `app/Support/SitemapBuilder.php` | Live photographer pages |
| `app/Filament/Forms/ContentBuilderBlocks.php` | `list_photographers` block |
| `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php` | `list_photographers` resolver |
| `resources/js/Components/ContentBuilder.tsx` | Route the new block |

### Deviation from the spec, and why

The spec calls for Vitest **component** tests on `ImageCredit`. This repo has no React testing setup — `vitest.config.ts` uses `environment: 'node'`, only includes `*.test.ts` (not `.tsx`), and neither `jsdom` nor `@testing-library/react` is installed. Adding them means three new dev dependencies and a config change.

Instead, **the credit-link decision logic is extracted into a pure helper** (`resources/js/Helpers/imageCredit.ts`) and unit-tested under the existing node environment, exactly as every other Vitest test in this repo works (all six live in `Helpers/`). `ImageCredit.tsx` becomes a thin switch over the helper's result with no logic of its own, verified in the browser.

This gets the same coverage of the part that can actually be wrong, adds no dependencies, and matches the established pattern. If Ben wants true component tests later, that's a separate, deliberate piece of tooling work.

---

## Task 1: Photographer model, migration and factory

**Files:**
- Create: `database/migrations/2026_08_06_100000_create_photographers_table.php`
- Create: `app/Models/Photographer.php`
- Create: `database/factories/PhotographerFactory.php`
- Test: `tests/Feature/PhotographerModelTest.php`

**Interfaces:**
- Consumes: nothing (first task)
- Produces:
  - `Photographer` model with `$fillable` covering `name, slug, socials, credit_link, bio, thumbnail_media_id, static_masthead_media_id, profile_blocks, seo_title, seo_description, user_id`
  - `Photographer::CREDIT_LINK_OPTIONS: array<string, string>` — key → human label
  - `Photographer::hasPublicPage(): bool`
  - `Photographer::creditPayload(): array{name: string, url: string|null}`
  - `Photographer::thumbnailMedia()`, `staticMastheadMedia()`, `user()` relations
  - `Photographer::scopeWithPublicPage(Builder $query): Builder`
  - `PhotographerFactory` with a `withPublicPage()` state

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotographerModelTest.php`:

```php
<?php

// Photographer model behaviour: slug auto-generation, the derived public-page
// gate, and credit-link resolution. creditPayload() must never produce a URL it
// cannot stand behind — every failure path degrades to a name with no link.

namespace Tests\Feature;

use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_name_when_blank(): void
    {
        $photographer = Photographer::create(['name' => 'Hamish McTavish']);

        $this->assertSame('hamish-mctavish', $photographer->slug);
    }

    public function test_explicit_slug_is_not_overwritten(): void
    {
        $photographer = Photographer::create(['name' => 'Hamish McTavish', 'slug' => 'hamish']);

        $this->assertSame('hamish', $photographer->slug);
    }

    public function test_slug_is_reusable_after_soft_delete(): void
    {
        Photographer::create(['name' => 'Hamish'])->delete();

        $replacement = Photographer::create(['name' => 'Hamish']);

        $this->assertSame('hamish', $replacement->slug);
    }

    public function test_has_public_page_is_false_without_profile_blocks(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => null]);

        $this->assertFalse($photographer->hasPublicPage());
    }

    public function test_has_public_page_is_false_with_empty_profile_blocks(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => []]);

        $this->assertFalse($photographer->hasPublicPage());
    }

    public function test_has_public_page_is_true_with_profile_blocks(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create();

        $this->assertTrue($photographer->hasPublicPage());
    }

    public function test_with_public_page_scope_returns_only_live_records(): void
    {
        $live = Photographer::factory()->withPublicPage()->create();
        Photographer::factory()->create(['profile_blocks' => null]);

        $results = Photographer::withPublicPage()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($live));
    }

    public function test_credit_payload_resolves_the_active_social_url(): void
    {
        $photographer = Photographer::factory()->create([
            'name' => 'Hamish',
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'instagram',
        ]);

        $this->assertSame(
            ['name' => 'Hamish', 'url' => 'https://instagram.com/hamish'],
            $photographer->creditPayload()
        );
    }

    public function test_credit_payload_has_no_url_when_the_target_key_is_empty(): void
    {
        $photographer = Photographer::factory()->create([
            'socials' => ['instagram' => ''],
            'credit_link' => 'instagram',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }

    public function test_credit_payload_has_no_url_for_an_unrecognised_key(): void
    {
        $photographer = Photographer::factory()->create([
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'myspace',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }

    public function test_credit_payload_has_no_url_when_set_to_none(): void
    {
        $photographer = Photographer::factory()->create([
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'none',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }

    public function test_credit_payload_resolves_profile_to_the_public_page(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create([
            'slug' => 'hamish',
            'credit_link' => 'profile',
        ]);

        $this->assertSame('/photographers/hamish', $photographer->creditPayload()['url']);
    }

    public function test_credit_payload_refuses_profile_when_the_page_is_not_live(): void
    {
        // Guards the case where credits were pointed at the profile and the
        // owner later emptied the content builder — never link to a 404.
        $photographer = Photographer::factory()->create([
            'slug' => 'hamish',
            'profile_blocks' => null,
            'credit_link' => 'profile',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
php artisan test --filter=PhotographerModelTest
```

Expected: FAIL — `Class "App\Models\Photographer" not found`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_06_100000_create_photographers_table.php`:

```php
<?php

// Photographers credited for supplied imagery. Standalone (no auth): a credit is
// not an account. Slug uniqueness is enforced only among live rows via a PARTIAL
// unique index so a soft-deleted photographer's slug can be reused — mirrors the
// tags/spot_guides pattern. `CREATE UNIQUE INDEX ... WHERE` works on both
// Postgres (dev/prod) and SQLite (tests).
//
// user_id is reserved for a future login and is not read by anything today; it
// exists so granting a photographer an account later needs no migration.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->json('socials')->nullable();
            $table->string('credit_link')->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->foreignId('static_masthead_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->json('profile_blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX photographers_slug_active_unique ON photographers (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX photographers_slug_active_unique');
        Schema::dropIfExists('photographers');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/Photographer.php`:

```php
<?php

// A photographer credited for supplied imagery. Standalone by design — a credit
// is not an account (see the design spec). The record carries everything needed
// for a public profile page, but that page only goes live once profile_blocks
// has content: visibility is DERIVED, so no empty page can be published by
// accident and there is no manual flag to forget.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Photographer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Selectable targets for the image credit link, as key => admin label.
     *
     * The stored value is the KEY, never a copy of the URL — the URL is resolved
     * from `socials` at read time so changing a handle in one field updates every
     * credit on the site. 'profile' is only offered once the page is live.
     */
    public const CREDIT_LINK_OPTIONS = [
        'none' => 'No link',
        'profile' => 'Their page on this site',
        'website' => 'Personal website',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook',
        'x' => 'X (Twitter)',
    ];

    protected $fillable = [
        'name', 'slug', 'socials', 'credit_link', 'bio',
        'thumbnail_media_id', 'static_masthead_media_id',
        'profile_blocks', 'seo_title', 'seo_description', 'user_id',
    ];

    protected $casts = [
        'socials' => 'array',
        'profile_blocks' => 'array',
    ];

    /**
     * Auto-fill the slug from the name when left blank, so the model is usable
     * without the admin form (tests, factories, seeders).
     */
    protected static function booted(): void
    {
        static::saving(function (Photographer $photographer) {
            if (blank($photographer->slug) && filled($photographer->name)) {
                $photographer->slug = Str::slug($photographer->name);
            }
        });
    }

    /** Card image for the list_photographers roll-up block. */
    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'thumbnail_media_id');
    }

    /** Hero image at the top of their profile page. */
    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    /**
     * Reserved for a future login. Nothing populates or reads this today; it
     * exists so granting an account later is a feature addition, not a migration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this photographer has a live public page. Derived from content:
     * filling in the content builder IS the decision to publish, so there is no
     * separate switch and an untouched photographer never gets an empty page.
     */
    public function hasPublicPage(): bool
    {
        return filled($this->slug) && filled($this->profile_blocks);
    }

    /**
     * Constrain to photographers with a live page. Used by every public surface
     * (the roll-up block, the sitemap) so gated records never leak.
     */
    public function scopeWithPublicPage(Builder $query): Builder
    {
        return $query
            ->whereNotNull('slug')
            ->whereNotNull('profile_blocks')
            ->whereNot('profile_blocks', '[]');
    }

    /**
     * The credit shown against every image this photographer supplied.
     *
     * `url` is null whenever the target cannot be honoured — unset, 'none', an
     * unrecognised key, a socials entry with no value, or 'profile' while the
     * page is not live. Callers render a plain name in that case, so a dead link
     * is never produced.
     *
     * @return array{name: string, url: string|null}
     */
    public function creditPayload(): array
    {
        return ['name' => $this->name, 'url' => $this->resolveCreditUrl()];
    }

    /** Resolve the active credit target to a URL, or null if it cannot be honoured. */
    private function resolveCreditUrl(): ?string
    {
        $target = $this->credit_link;

        if (blank($target) || $target === 'none' || ! array_key_exists($target, self::CREDIT_LINK_OPTIONS)) {
            return null;
        }

        if ($target === 'profile') {
            return $this->hasPublicPage() ? "/photographers/{$this->slug}" : null;
        }

        $url = data_get($this->socials, $target);

        return filled($url) ? $url : null;
    }
}
```

- [ ] **Step 5: Write the factory**

Create `database/factories/PhotographerFactory.php`:

```php
<?php

// Test data for photographers. The default record has NO profile_blocks, so it
// deliberately has no public page — matching the common real case of a
// photographer who only wants a credit.

namespace Database\Factories;

use App\Models\Photographer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photographer>
 */
class PhotographerFactory extends Factory
{
    protected $model = Photographer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'socials' => ['instagram' => 'https://instagram.com/'.$this->faker->userName()],
            'credit_link' => 'instagram',
            'profile_blocks' => null,
        ];
    }

    /** A photographer whose profile page is live (has content-builder content). */
    public function withPublicPage(): static
    {
        return $this->state(fn (): array => [
            'profile_blocks' => [
                ['type' => 'rich_text', 'data' => ['content' => '<p>Shoots water shots in Tarifa.</p>']],
            ],
        ]);
    }
}
```

- [ ] **Step 6: Run the test and verify it passes**

```bash
php artisan test --filter=PhotographerModelTest
```

Expected: PASS, 13 tests.

- [ ] **Step 7: Run the full suite**

```bash
php artisan test
```

Expected: PASS, no regressions.

- [ ] **Step 8: Commit**

```bash
git add app/Models/Photographer.php database/factories/PhotographerFactory.php database/migrations/2026_08_06_100000_create_photographers_table.php tests/Feature/PhotographerModelTest.php
git commit -m "Add Photographer model with derived public-page gate"
```

---

## Task 2: Attach photographers to media and emit credits

**Files:**
- Create: `database/migrations/2026_08_06_100010_add_photographer_id_to_media_library_table.php`
- Modify: `app/Models/MediaLibrary.php`
- Test: `tests/Feature/PhotographerCreditPayloadTest.php`

**Interfaces:**
- Consumes: `Photographer::creditPayload()` from Task 1
- Produces: `MediaLibrary::photographer()` relation; `imagePayload()` gains a `credit` key of `array{name: string, url: string|null}|null`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotographerCreditPayloadTest.php`:

```php
<?php

// The credit reaches the front end through imagePayload() — the single
// serialisation choke point every image on the site flows through. A soft-deleted
// photographer's credit must disappear: note this works via the SoftDeletes
// global scope on the relation, NOT via the FK's nullOnDelete (a database-level
// action that only fires on a hard delete).

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerCreditPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_payload_carries_no_credit_without_a_photographer(): void
    {
        $media = MediaLibrary::create(['name' => 'House shot']);

        $this->assertNull($media->imagePayload()['credit']);
    }

    public function test_image_payload_carries_the_photographer_credit(): void
    {
        $photographer = Photographer::factory()->create([
            'name' => 'Hamish',
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'instagram',
        ]);
        $media = MediaLibrary::create(['name' => 'Tarifa', 'photographer_id' => $photographer->id]);

        $this->assertSame(
            ['name' => 'Hamish', 'url' => 'https://instagram.com/hamish'],
            $media->fresh()->imagePayload()['credit']
        );
    }

    public function test_credit_disappears_when_the_photographer_is_soft_deleted(): void
    {
        $photographer = Photographer::factory()->create();
        $media = MediaLibrary::create(['name' => 'Tarifa', 'photographer_id' => $photographer->id]);

        $photographer->delete();

        $this->assertNull($media->fresh()->imagePayload()['credit']);
    }

    public function test_credit_returns_when_the_photographer_is_restored(): void
    {
        $photographer = Photographer::factory()->create(['name' => 'Hamish']);
        $media = MediaLibrary::create(['name' => 'Tarifa', 'photographer_id' => $photographer->id]);
        $photographer->delete();

        $photographer->restore();

        $this->assertSame('Hamish', $media->fresh()->imagePayload()['credit']['name']);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
php artisan test --filter=PhotographerCreditPayloadTest
```

Expected: FAIL — no `photographer_id` column.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_06_100010_add_photographer_id_to_media_library_table.php`:

```php
<?php

// Credits an image to a photographer. Null — the default and the overwhelming
// majority — means the image is the site's own and renders with no credit.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->foreignId('photographer_id')->nullable()->constrained('photographers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photographer_id');
        });
    }
};
```

- [ ] **Step 4: Update the MediaLibrary model**

In `app/Models/MediaLibrary.php`, add `photographer_id` to `$fillable`:

```php
    protected $fillable = ['name', 'folder', 'focal_x', 'focal_y', 'user_id', 'photographer_id'];
```

Add this property directly beneath `$casts`:

```php
    /**
     * Always eager-load the photographer.
     *
     * imagePayload() is called from 45+ sites across every controller and the
     * content-block resolver; adding `.photographer` to each eager-load call
     * individually would be one missed site away from an N+1 on a page of cards.
     * One relation, loaded once per parent query, is both cheaper and impossible
     * to forget.
     */
    protected $with = ['photographer'];
```

Add the relation beneath `user()`:

```php
    /**
     * The photographer credited for this image. Null means it is the site's own
     * work, which is the default and the majority case.
     *
     * @return BelongsTo<Photographer, MediaLibrary>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(Photographer::class);
    }
```

Update `imagePayload()` — both the docblock and the return:

```php
    /**
     * The canonical image shape consumed across the app (controllers, content
     * blocks, front-end <CoverImage>). Focal values drive CSS object-position;
     * `credit` is null for the site's own images.
     *
     * @return array{url: string, alt: string, focal_x: int, focal_y: int, credit: array{name: string, url: string|null}|null}
     */
    public function imagePayload(): array
    {
        return [
            'url' => $this->getUrl(),
            'alt' => $this->name,
            'focal_x' => $this->focal_x ?? 50,
            'focal_y' => $this->focal_y ?? 50,
            'credit' => $this->photographer?->creditPayload(),
        ];
    }
```

- [ ] **Step 5: Run the test and verify it passes**

```bash
php artisan test --filter=PhotographerCreditPayloadTest
```

Expected: PASS, 4 tests.

- [ ] **Step 6: Run the full suite**

```bash
php artisan test
```

Expected: PASS. Several existing tests assert on `imagePayload()` output; if any assert an exact array shape they will now fail on the added key — update those assertions to include `'credit' => null` rather than removing the key.

- [ ] **Step 7: Commit**

```bash
git add app/Models/MediaLibrary.php database/migrations/2026_08_06_100010_add_photographer_id_to_media_library_table.php tests/Feature/PhotographerCreditPayloadTest.php
git commit -m "Emit photographer credit through imagePayload"
```

---

## Task 3: Photographer admin resource

**Files:**
- Create: `app/Policies/PhotographerPolicy.php`
- Create: `app/Filament/Forms/PhotographerProfileForm.php`
- Create: `app/Filament/Resources/PhotographerResource.php`
- Create: `app/Filament/Resources/PhotographerResource/Pages/ListPhotographers.php`
- Create: `app/Filament/Resources/PhotographerResource/Pages/CreatePhotographer.php`
- Create: `app/Filament/Resources/PhotographerResource/Pages/EditPhotographer.php`
- Test: `tests/Feature/PhotographerAdminTest.php`

**Interfaces:**
- Consumes: `Photographer::CREDIT_LINK_OPTIONS`, `hasPublicPage()` from Task 1
- Produces: `PhotographerProfileForm::schema(): array<int, Component>` — reused verbatim by a future self-edit page

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotographerAdminTest.php`:

```php
<?php

// The photographer admin is owner-only: contributors author guides, they do not
// manage the site's photography credits.

namespace Tests\Feature;

use App\Models\Photographer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_photographers(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/admin/photographers')->assertOk();
    }

    public function test_contributor_cannot_list_photographers(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);

        $this->actingAs($contributor)->get('/admin/photographers')->assertForbidden();
    }

    public function test_credit_link_options_exclude_profile_until_the_page_is_live(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => null]);

        $this->assertArrayNotHasKey('profile', \App\Filament\Forms\PhotographerProfileForm::creditLinkOptions($photographer));
    }

    public function test_credit_link_options_include_profile_once_the_page_is_live(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create();

        $this->assertArrayHasKey('profile', \App\Filament\Forms\PhotographerProfileForm::creditLinkOptions($photographer));
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
php artisan test --filter=PhotographerAdminTest
```

Expected: FAIL — route `/admin/photographers` not found.

- [ ] **Step 3: Write the policy**

Create `app/Policies/PhotographerPolicy.php`:

```php
<?php

// Photography credits are the owner's editorial responsibility — contributors
// author spot guides, they never manage photographers.

namespace App\Policies;

use App\Models\Photographer;
use App\Models\User;

class PhotographerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }
}
```

- [ ] **Step 4: Write the shared form schema**

Create `app/Filament/Forms/PhotographerProfileForm.php`:

```php
<?php

// Shared Filament form schema for a photographer's record — used by the
// owner-facing PhotographerResource today, and reusable verbatim by a
// photographer self-edit page if logins are ever added. Defining it once here is
// what makes that future handover free; it mirrors ContributorProfileForm.

namespace App\Filament\Forms;

use App\Filament\Forms\Components\MediaPicker;
use App\Models\Photographer;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class PhotographerProfileForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            Section::make('Identity')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)
                        ->helperText('Shown as the credit on every image assigned to them.'),
                    TextInput::make('slug')->maxLength(255)
                        ->helperText('Leave blank to generate from the name. Used for their page URL.'),
                    Textarea::make('bio')->rows(3)
                        ->helperText('Short intro shown on their page.'),
                ])->columns(2),

            Section::make('Socials')
                ->description('Only the ones you fill in can be used as the credit link.')
                ->schema([
                    TextInput::make('socials.instagram')->label('Instagram')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('socials.youtube')->label('YouTube')->url(),
                    TextInput::make('socials.tiktok')->label('TikTok')->url(),
                    TextInput::make('socials.facebook')->label('Facebook')->url(),
                    TextInput::make('socials.x')->label('X (Twitter)')->url(),
                    TextInput::make('socials.website')->label('Personal website')->url(),
                ])->columns(2),

            Section::make('Credit link')
                ->description('Where a click on their image credit goes. Only filled-in socials are offered.')
                ->schema([
                    Select::make('credit_link')
                        ->label('Link image credits to')
                        ->options(fn (?Photographer $record): array => static::creditLinkOptions($record))
                        ->default('none'),
                ]),

            Section::make('Page images')
                ->description('Only used once their page is live.')
                ->schema([
                    MediaPicker::make('thumbnail_media_id')->label('Card image'),
                    MediaPicker::make('static_masthead_media_id')->label('Masthead image'),
                ]),

            Section::make('Their page')
                ->description('Adding content here publishes their page at /photographers/{slug}. Leave empty and they have no page — just image credits.')
                ->schema([
                    Builder::make('profile_blocks')
                        ->label('Page content')
                        ->blocks(ContentBuilderBlocks::blocks())
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            Section::make('SEO')
                ->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    Textarea::make('seo_description')->rows(2),
                ])->collapsed(),
        ];
    }

    /**
     * Credit-link targets selectable for this record: 'none', every socials key
     * with a URL in it, and 'profile' only once the page is actually live. A
     * target that would resolve to nothing is never offered.
     *
     * @return array<string, string>
     */
    public static function creditLinkOptions(?Photographer $record): array
    {
        $options = ['none' => Photographer::CREDIT_LINK_OPTIONS['none']];

        if ($record?->hasPublicPage()) {
            $options['profile'] = Photographer::CREDIT_LINK_OPTIONS['profile'];
        }

        foreach ($record?->socials ?? [] as $platform => $url) {
            if (filled($url) && isset(Photographer::CREDIT_LINK_OPTIONS[$platform])) {
                $options[$platform] = Photographer::CREDIT_LINK_OPTIONS[$platform];
            }
        }

        return $options;
    }
}
```

- [ ] **Step 5: Write the resource**

Create `app/Filament/Resources/PhotographerResource.php`:

```php
<?php

// Owner-only admin for photographers credited on site imagery. The form schema
// lives in PhotographerProfileForm so it can be reused unchanged by a
// photographer self-edit page if logins are ever added.

namespace App\Filament\Resources;

use App\Filament\Forms\PhotographerProfileForm;
use App\Filament\Resources\PhotographerResource\Pages;
use App\Models\Photographer;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhotographerResource extends Resource
{
    protected static ?string $model = Photographer::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema(PhotographerProfileForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('media_count')->label('Images')->counts('media')->sortable(),
                TextColumn::make('credit_link')->label('Credit links to')->placeholder('—'),
                IconColumn::make('has_public_page')
                    ->label('Page live')
                    ->boolean()
                    ->state(fn (Photographer $record): bool => $record->hasPublicPage()),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhotographers::route('/'),
            'create' => Pages\CreatePhotographer::route('/create'),
            'edit' => Pages\EditPhotographer::route('/{record}/edit'),
        ];
    }
}
```

The `media_count` column needs a `media` relation on `Photographer`. Add it to `app/Models/Photographer.php` beneath `staticMastheadMedia()`:

```php
    /**
     * Every library image credited to this photographer.
     *
     * @return HasMany<MediaLibrary>
     */
    public function media(): HasMany
    {
        return $this->hasMany(MediaLibrary::class);
    }
```

…with `use Illuminate\Database\Eloquent\Relations\HasMany;` added to the imports.

- [ ] **Step 6: Write the resource pages**

Create `app/Filament/Resources/PhotographerResource/Pages/ListPhotographers.php`:

```php
<?php

namespace App\Filament\Resources\PhotographerResource\Pages;

use App\Filament\Resources\PhotographerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhotographers extends ListRecords
{
    protected static string $resource = PhotographerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

Create `app/Filament/Resources/PhotographerResource/Pages/CreatePhotographer.php`:

```php
<?php

namespace App\Filament\Resources\PhotographerResource\Pages;

use App\Filament\Resources\PhotographerResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePhotographer extends CreateRecord
{
    protected static string $resource = PhotographerResource::class;
}
```

Create `app/Filament/Resources/PhotographerResource/Pages/EditPhotographer.php`:

```php
<?php

namespace App\Filament\Resources\PhotographerResource\Pages;

use App\Filament\Resources\PhotographerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhotographer extends EditRecord
{
    protected static string $resource = PhotographerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
```

- [ ] **Step 7: Run the test and verify it passes**

```bash
php artisan test --filter=PhotographerAdminTest
```

Expected: PASS, 4 tests. Filament auto-discovers both the resource and the policy (`app/Policies` follows Laravel's naming convention), so no registration is needed.

- [ ] **Step 8: Run the full suite and commit**

```bash
php artisan test
git add app/Policies/PhotographerPolicy.php app/Filament/Forms/PhotographerProfileForm.php app/Filament/Resources/PhotographerResource.php app/Filament/Resources/PhotographerResource app/Models/Photographer.php tests/Feature/PhotographerAdminTest.php
git commit -m "Add owner-only photographer admin resource"
```

---

## Task 4: Assign photographers to media in the admin

**Files:**
- Modify: `app/Filament/Resources/MediaLibraryResource.php`
- Test: `tests/Feature/MediaLibraryScopingTest.php`

**Interfaces:**
- Consumes: `Photographer` from Task 1, `photographer_id` from Task 2
- Produces: no new public API

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MediaLibraryScopingTest.php`:

```php
<?php

// Media list scoping must be an opt-OUT for the owner, not an opt-IN for
// contributors: a role added later (e.g. a photographer login) must not fall
// through the check and see the whole library including house media.

namespace Tests\Feature;

use App\Filament\Resources\MediaLibraryResource;
use App\Models\MediaLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_all_media(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        MediaLibrary::create(['name' => 'House shot']);
        MediaLibrary::create(['name' => 'Contributor shot', 'user_id' => $owner->id]);

        $this->actingAs($owner);

        $this->assertSame(2, MediaLibraryResource::getEloquentQuery()->count());
    }

    public function test_non_owner_sees_only_their_own_media(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        MediaLibrary::create(['name' => 'House shot']);
        MediaLibrary::create(['name' => 'Theirs', 'user_id' => $contributor->id]);

        $this->actingAs($contributor);

        $results = MediaLibraryResource::getEloquentQuery()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Theirs', $results->first()->name);
    }

    public function test_folder_options_exclude_house_folders_for_non_owners(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        MediaLibrary::create(['name' => 'House shot', 'folder' => 'House']);
        MediaLibrary::create(['name' => 'Theirs', 'folder' => 'Theirs', 'user_id' => $contributor->id]);

        $this->actingAs($contributor);

        $this->assertSame(['Theirs' => 'Theirs'], MediaLibraryResource::folderOptions());
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
php artisan test --filter=MediaLibraryScopingTest
```

Expected: the two `non_owner` tests PASS incidentally (a contributor *is* currently scoped), and `test_owner_sees_all_media` PASSes. This test locks in current behaviour so the hardening in Step 3 provably does not change it. If all three pass immediately, that is the correct starting state — proceed.

- [ ] **Step 3: Harden the scoping**

In `app/Filament/Resources/MediaLibraryResource.php`, in `folderOptions()` replace:

```php
            ->when($user && $user->isContributor(), fn ($query) => $query->where('user_id', $user->id))
```

with:

```php
            // Opt-OUT for the owner rather than opt-IN for contributors: any role
            // added later is scoped by default instead of falling through to the
            // full library, house media included.
            ->when($user && ! $user->isOwner(), fn ($query) => $query->where('user_id', $user->id))
```

And in `getEloquentQuery()` replace:

```php
        if ($user && $user->isContributor()) {
            $query->where('user_id', $user->id);
        }
```

with:

```php
        // See folderOptions(): scoped unless you are the owner.
        if ($user && ! $user->isOwner()) {
            $query->where('user_id', $user->id);
        }
```

- [ ] **Step 4: Add the photographer field, column, filter and bulk action**

Add these imports to `app/Filament/Resources/MediaLibraryResource.php`:

```php
use App\Models\Photographer;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
```

In `form()`, add after the `folder` Select:

```php
            // Retro-assignment path: any existing image can be credited later by
            // editing it here, not only at upload time.
            FormSelect::make('photographer_id')
                ->label('Photographer')
                ->relationship('photographer', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Our own image')
                ->helperText('Leave blank for the site\'s own photography.'),
```

In `table()`, add after the `folder` column:

```php
                TextColumn::make('photographer.name')
                    ->label('Photographer')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),
```

In `filters()`, add after the folder filter:

```php
                SelectFilter::make('photographer_id')
                    ->label('Photographer')
                    ->options(fn (): array => Photographer::orderBy('name')->pluck('name', 'id')->toArray()),
```

In `bulkActions()`, add inside the `BulkActionGroup` before `DeleteBulkAction`:

```php
                    // A batch of images typically arrives from one photographer;
                    // assigning them one at a time would be miserable.
                    BulkAction::make('assignPhotographer')
                        ->label('Assign photographer')
                        ->icon('heroicon-o-camera')
                        ->form([
                            FormSelect::make('photographer_id')
                                ->label('Photographer')
                                ->options(fn (): array => Photographer::orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->placeholder('Our own image (clear the credit)'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['photographer_id' => $data['photographer_id'] ?? null]);
                        })
                        ->deselectRecordsAfterCompletion(),
```

- [ ] **Step 5: Run the tests and verify they pass**

```bash
php artisan test --filter=MediaLibraryScopingTest
```

Expected: PASS, 3 tests — proving the hardening changed no behaviour.

- [ ] **Step 6: Verify in the browser**

Start the dev environment (`composer dev`, with the Node 22 PATH exported), sign in at `/admin`, and confirm at `/admin/media-libraries`:

- The Photographer column shows "—" for existing images
- The Photographer filter lists any photographers created
- Selecting rows offers "Assign photographer" and applies it

- [ ] **Step 7: Run the full suite and commit**

```bash
php artisan test
git add app/Filament/Resources/MediaLibraryResource.php tests/Feature/MediaLibraryScopingTest.php
git commit -m "Assign photographers to media; harden media list scoping"
```

---

## Task 5: Render credits on every image

**Files:**
- Create: `resources/js/Helpers/imageCredit.ts`
- Create: `resources/js/Helpers/imageCredit.test.ts`
- Create: `resources/js/Components/Common/ImageCredit.tsx`
- Modify: `resources/js/types/media.ts`
- Modify: `resources/js/Components/Common/CoverImage.tsx`
- Modify: `resources/js/Components/Map/DestinationsMap.tsx`, `resources/js/Components/SpotGuide/SpotGuideMap.tsx`

**Interfaces:**
- Consumes: the `credit` key emitted in Task 2
- Produces:
  - `ImageCreditData = { name: string; url: string | null }`
  - `FocalImage.credit?: ImageCreditData | null`
  - `resolveCredit(credit): { kind: 'none' | 'text' | 'external' | 'internal'; name: string; href: string | null; label: string }`
  - `CoverImage` gains `showCredit?: boolean` (default `true`)

- [ ] **Step 1: Write the failing helper test**

Create `resources/js/Helpers/imageCredit.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { resolveCredit } from '@/Helpers/imageCredit'

describe('resolveCredit', () => {
    it('returns none when there is no credit', () => {
        expect(resolveCredit(null).kind).toBe('none')
        expect(resolveCredit(undefined).kind).toBe('none')
    })

    it('returns text when the photographer has no link', () => {
        const result = resolveCredit({ name: 'Hamish', url: null })

        expect(result.kind).toBe('text')
        expect(result.name).toBe('Hamish')
        expect(result.href).toBeNull()
    })

    it('treats an absolute URL as external', () => {
        const result = resolveCredit({ name: 'Hamish', url: 'https://instagram.com/hamish' })

        expect(result.kind).toBe('external')
        expect(result.href).toBe('https://instagram.com/hamish')
        expect(result.label).toBe('Photo by Hamish, opens in a new tab')
    })

    it('treats a relative URL as an internal profile link', () => {
        const result = resolveCredit({ name: 'Hamish', url: '/photographers/hamish' })

        expect(result.kind).toBe('internal')
        expect(result.href).toBe('/photographers/hamish')
        expect(result.label).toBe('Photo by Hamish')
    })

    it('falls back to text for a blank name', () => {
        expect(resolveCredit({ name: '', url: 'https://example.com' }).kind).toBe('none')
    })
})
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js
```

Expected: FAIL — cannot resolve `@/Helpers/imageCredit`.

- [ ] **Step 3: Write the helper**

Create `resources/js/Helpers/imageCredit.ts`:

```ts
/**
 * imageCredit — decides how a photographer credit should be rendered.
 *
 * Kept as a pure function (rather than logic inside ImageCredit.tsx) so it is
 * testable under this project's node-environment Vitest setup, which has no DOM
 * or React testing library. The component is a thin switch over the result.
 */

/** A photographer credit as emitted by MediaLibrary::imagePayload(). */
export interface ImageCreditData {
    name: string
    url: string | null
}

/** How the credit should render: nothing, plain text, or one of two link kinds. */
export type CreditKind = 'none' | 'text' | 'external' | 'internal'

export interface ResolvedCredit {
    kind: CreditKind
    name: string
    href: string | null
    /** Accessible label for the anchor, or the plain name when unlinked. */
    label: string
}

/**
 * Resolve a raw credit into everything the badge needs to render.
 *
 * A relative URL means the photographer's own page on this site and gets an
 * Inertia link; an absolute URL is somebody else's site and opens in a new tab.
 * Anything unusable degrades to `none` so a dead link is never rendered.
 *
 * @param credit the credit from the image payload, if any
 */
export const resolveCredit = (credit?: ImageCreditData | null): ResolvedCredit => {
    const name = credit?.name?.trim() ?? ''

    if (!name) {
        return { kind: 'none', name: '', href: null, label: '' }
    }

    const href = credit?.url?.trim() || null

    if (!href) {
        return { kind: 'text', name, href: null, label: `Photo by ${name}` }
    }

    const isInternal = href.startsWith('/')

    return {
        kind: isInternal ? 'internal' : 'external',
        name,
        href,
        label: isInternal ? `Photo by ${name}` : `Photo by ${name}, opens in a new tab`,
    }
}
```

- [ ] **Step 4: Run the test and verify it passes**

```bash
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js
```

Expected: PASS, 5 new tests plus the existing suites.

- [ ] **Step 5: Extend the image type**

Replace `resources/js/types/media.ts` with:

```ts
import type { ImageCreditData } from '@/Helpers/imageCredit'

/** An image plus its focal point (percentages, 0–100; 50/50 = centre). */
export interface FocalImage {
    url: string
    alt?: string
    focal_x?: number
    focal_y?: number
    /** Photographer credit; null/absent for the site's own photography. */
    credit?: ImageCreditData | null
}
```

- [ ] **Step 6: Write the badge component**

Create `resources/js/Components/Common/ImageCredit.tsx`:

```tsx
/**
 * ImageCredit — the small photographer attribution badge shown over an image.
 *
 * Always visible rather than hover-only: hover reveals nothing on touch devices,
 * which is where most of the site is read. Sits over arbitrary photography, so it
 * carries its own scrim and reads the same in light and dark — the photo is its
 * background, not the page.
 */
import { Link } from '@inertiajs/react'
import { resolveCredit, type ImageCreditData } from '@/Helpers/imageCredit'

interface Props {
    credit?: ImageCreditData | null
}

/** Shared visual treatment for all three rendered forms. */
const BADGE_CLASSES =
    'absolute bottom-0 right-0 z-10 px-2 py-1 text-[10px] leading-none tracking-wide ' +
    'text-white/90 bg-secondary/70 backdrop-blur-sm rounded-tl pointer-events-auto'

const ImageCredit = ({ credit }: Props) => {
    const resolved = resolveCredit(credit)

    if (resolved.kind === 'none') return null

    if (resolved.kind === 'text') {
        return <span className={BADGE_CLASSES}>{`© ${resolved.name}`}</span>
    }

    if (resolved.kind === 'internal') {
        return (
            <Link
                href={resolved.href as string}
                aria-label={resolved.label}
                className={`${BADGE_CLASSES} hover:bg-secondary/90 transition-colors duration-200`}
            >
                {`© ${resolved.name}`}
            </Link>
        )
    }

    return (
        <a
            href={resolved.href as string}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={resolved.label}
            className={`${BADGE_CLASSES} hover:bg-secondary/90 transition-colors duration-200`}
        >
            {`© ${resolved.name}`}
        </a>
    )
}

export default ImageCredit
```

- [ ] **Step 7: Wrap CoverImage**

Replace `resources/js/Components/Common/CoverImage.tsx` with:

```tsx
/**
 * CoverImage — the single renderer for every object-cover image. Applies the
 * image's focal point as CSS object-position so the subject stays in frame when
 * the image is cropped, and renders the photographer credit when there is one.
 * Tolerant of a plain URL string (focal defaults to centre, no credit) so
 * components can adopt it before the backend emits focal objects.
 *
 * The wrapper is rendered whether or not there is a credit, so layout can never
 * differ between a credited and an uncredited image. Callers' classNames size and
 * position the WRAPPER; the img always fills it.
 */
import type { FocalImage } from '@/types/media'
import ImageCredit from '@/Components/Common/ImageCredit'

interface Props {
    image?: FocalImage | string | null
    alt?: string
    className?: string
    /**
     * Suppress the credit badge. Used where an image is UI chrome rather than
     * displayed photography — map pins are ~40px and a badge is illegible there.
     */
    showCredit?: boolean
}

const CoverImage = ({ image, alt, className = '', showCredit = true }: Props) => {
    if (!image) return null

    const isString = typeof image === 'string'
    const url = isString ? image : image.url
    if (!url) return null

    const focalX = isString ? 50 : image.focal_x ?? 50
    const focalY = isString ? 50 : image.focal_y ?? 50
    const resolvedAlt = alt ?? (isString ? '' : image.alt ?? '')
    const credit = !isString && showCredit ? image.credit : null

    return (
        <span className={`relative block overflow-hidden ${className}`}>
            <img
                src={url}
                alt={resolvedAlt}
                loading="lazy"
                className="object-cover w-full h-full"
                style={{ objectPosition: `${focalX}% ${focalY}%` }}
            />
            <ImageCredit credit={credit} />
        </span>
    )
}

export default CoverImage
```

- [ ] **Step 8: Opt the maps out**

In `resources/js/Components/Map/DestinationsMap.tsx` and `resources/js/Components/SpotGuide/SpotGuideMap.tsx`, add `showCredit={false}` to every `<CoverImage ... />`. Find them with:

```bash
grep -n "CoverImage" resources/js/Components/Map/DestinationsMap.tsx resources/js/Components/SpotGuide/SpotGuideMap.tsx
```

- [ ] **Step 9: Build and verify every consuming component**

```bash
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build
```

Expected: builds with no TypeScript errors.

Then, with `composer dev` running, walk these pages at **mobile (375px), tablet (768px) and desktop (1280px)** and confirm no layout has shifted:

| Page | What to check |
|---|---|
| `/` | Masthead slider, featured hero, featured grid |
| `/destinations` | Masthead, destination card grid, map pins |
| `/destinations/{slug}` | Static masthead + SpotOverview sidebar, gallery, split-image-text, content-with-background-image, related guides slider, map |
| `/blog`, `/blog/{slug}` | Cards, masthead |
| `/blog/tags`, `/blog/tags/{slug}` | Tag cards, masthead |
| `/contributors/{slug}` | Round portrait (checks `overflow-hidden` + `rounded-full` on the wrapper), guide cards |
| `/search` | Result thumbnails |

The round portrait on a contributor profile is the highest-risk case — `rounded-full` now applies to the wrapper, which must clip the img. If any image loses its shape or sizing, the caller's className needs splitting rather than reverting the wrapper.

- [ ] **Step 10: Assign a photographer to one image and confirm the badge**

In `/admin/media-libraries`, create a photographer with an Instagram URL and `credit_link` = Instagram, assign them to a spot guide's masthead image, then load that guide. Expect a small `© Name` badge bottom-right that opens Instagram in a new tab. Check it in both light and dark.

- [ ] **Step 11: Commit**

```bash
git add resources/js/Helpers/imageCredit.ts resources/js/Helpers/imageCredit.test.ts resources/js/Components/Common/ImageCredit.tsx resources/js/Components/Common/CoverImage.tsx resources/js/types/media.ts resources/js/Components/Map/DestinationsMap.tsx resources/js/Components/SpotGuide/SpotGuideMap.tsx
git commit -m "Render photographer credits on every displayed image"
```

---

## Task 6: Guard against N+1 on credited pages

**Files:**
- Test: `tests/Feature/PhotographerQueryCountTest.php`

**Interfaces:**
- Consumes: `MediaLibrary::$with` from Task 2
- Produces: nothing — a regression guard

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotographerQueryCountTest.php`:

```php
<?php

// Credits are read from a relation on every image, so a page of cards could
// trivially become one query per card. MediaLibrary declares $with =
// ['photographer'], which batches the load; this test fails if that is removed.

namespace Tests\Feature;

use App\Models\Country;
use App\Models\MediaLibrary;
use App\Models\Photographer;
use App\Models\SpotGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhotographerQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_destinations_page_does_not_issue_a_query_per_credited_image(): void
    {
        $country = Country::factory()->create();

        foreach (range(1, 8) as $index) {
            $photographer = Photographer::factory()->create();
            $thumbnail = MediaLibrary::create([
                'name' => "Shot {$index}",
                'photographer_id' => $photographer->id,
            ]);

            SpotGuide::withoutEvents(fn () => SpotGuide::create([
                'title' => "Spot {$index}",
                'slug' => "spot-{$index}",
                'country_id' => $country->id,
                'thumbnail_media_id' => $thumbnail->id,
                'is_published' => true,
                'published_at' => now(),
            ]));
        }

        DB::enableQueryLog();
        $this->get('/destinations')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Generous ceiling: the point is that adding more credited spots must not
        // move this number. Without batching it would exceed 8 extra queries.
        $this->assertLessThan(35, $queryCount, "Destinations issued {$queryCount} queries — check MediaLibrary::\$with.");
    }
}
```

- [ ] **Step 2: Run the test**

```bash
php artisan test --filter=PhotographerQueryCountTest
```

Expected: PASS (the `$with` from Task 2 already batches it). If it FAILS, `$with` is missing or a controller is re-querying media — fix before continuing.

- [ ] **Step 3: Verify it actually guards**

Temporarily comment out `protected $with = ['photographer'];` in `app/Models/MediaLibrary.php`, re-run the test, and confirm it now FAILS. Restore the line and confirm it passes again. A guard that cannot fail is not a guard.

- [ ] **Step 4: Commit**

```bash
php artisan test
git add tests/Feature/PhotographerQueryCountTest.php
git commit -m "Add N+1 guard for photographer credits"
```

---

## Task 7: Public photographer profile page

**Files:**
- Create: `app/Http/Controllers/PhotographerController.php`
- Create: `resources/js/Pages/Photographers/Show.tsx`
- Modify: `routes/web.php`
- Modify: `app/Support/SitemapBuilder.php`
- Test: `tests/Feature/PhotographerPageTest.php`

**Interfaces:**
- Consumes: `Photographer::hasPublicPage()`, `scopeWithPublicPage()` from Task 1
- Produces: route `photographers.show`; Inertia component `Photographers/Show`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotographerPageTest.php`:

```php
<?php

// The public photographer profile (GET /photographers/{slug}). Visibility is
// derived from profile_blocks, so a photographer who only wanted a credit never
// gets a thin, empty page — and the sitemap must never advertise one.

namespace Tests\Feature;

use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_for_a_photographer_with_content(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create([
            'name' => 'Hamish McTavish',
            'bio' => 'Water shots in Tarifa.',
        ]);

        $this->get('/photographers/'.$photographer->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Second arg `false` skips the page-file-exists check — the
                // component is built later in this same task.
                ->component('Photographers/Show', false)
                ->where('photographer.name', 'Hamish McTavish')
                ->where('photographer.bio', 'Water shots in Tarifa.'));
    }

    public function test_page_404s_without_profile_content(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => null]);

        $this->get('/photographers/'.$photographer->slug)->assertNotFound();
    }

    public function test_unknown_slug_404s(): void
    {
        $this->get('/photographers/nobody-here')->assertNotFound();
    }

    public function test_soft_deleted_photographer_404s(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create();
        $photographer->delete();

        $this->get('/photographers/'.$photographer->slug)->assertNotFound();
    }

    public function test_sitemap_lists_live_photographer_pages_only(): void
    {
        $live = Photographer::factory()->withPublicPage()->create(['name' => 'Live One']);
        $gated = Photographer::factory()->create(['name' => 'Gated One', 'profile_blocks' => null]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee('/photographers/'.$live->slug);
        $response->assertDontSee('/photographers/'.$gated->slug);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
php artisan test --filter=PhotographerPageTest
```

Expected: FAIL — the route does not exist, so requests hit the `/{slug}` catch-all and 404 with the wrong reason; the sitemap test fails on the missing URL.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/PhotographerController.php`:

```php
<?php

// Public photographer profile: GET /photographers/{slug}. Renders the
// photographer's content-builder page, bio and socials. Visibility is DERIVED
// from having profile content — a photographer who only wanted an image credit
// has no page, so unknown slugs and content-free records both 404.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\Photographer;
use Inertia\Inertia;
use Inertia\Response;

class PhotographerController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render a live photographer profile, or 404 if the page isn't public.
     *
     * @param  string  $slug  the photographer's URL slug
     */
    public function show(string $slug): Response
    {
        $photographer = Photographer::where('slug', $slug)->firstOrFail();
        abort_unless($photographer->hasPublicPage(), 404);

        $photographer->load(['thumbnailMedia', 'staticMastheadMedia']);

        // Only non-empty socials reach the front end (blank/absent keys hidden).
        $socials = collect($photographer->socials ?? [])
            ->filter(fn ($url) => filled($url))
            ->all();

        return Inertia::render('Photographers/Show', [
            'photographer' => [
                'name' => $photographer->name,
                'bio' => $photographer->bio,
                'thumbnail' => $photographer->thumbnailMedia?->imagePayload(),
                'socials' => $socials,
                'profile_blocks' => $this->resolveContentBlockMedia($photographer->profile_blocks ?? []),
            ],
            'static_masthead' => $photographer->staticMastheadMedia?->imagePayload(),
            'meta' => [
                'title' => $photographer->seo_title ?: "{$photographer->name} — Seabound Sessions",
                'description' => $photographer->seo_description ?: "Photography by {$photographer->name}.",
                'keywords' => [],
                'og_image' => $photographer->thumbnailMedia?->getUrl() ?: '',
            ],
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\PhotographerController;
```

And add this line immediately after the `/contributors/{slug}` route:

```php
Route::get('/photographers/{slug}', [PhotographerController::class, 'show'])->name('photographers.show');
```

It must sit **above** the `/{slug}` catch-all at the bottom of the file, exactly as `/contributors/{slug}` does.

- [ ] **Step 5: Add photographers to the sitemap**

In `app/Support/SitemapBuilder.php`, add the import:

```php
use App\Models\Photographer;
```

And add this block immediately before `return $sitemap;`:

```php
        // Photographer profiles that are actually live. Records without profile
        // content have no page (they 404), so must not be advertised.
        Photographer::withPublicPage()->each(fn (Photographer $photographer) => $sitemap->add(
            Url::create("/photographers/{$photographer->slug}")
                ->setLastModificationDate($photographer->updated_at)
                ->setPriority(0.6)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
        ));
```

- [ ] **Step 6: Write the page component**

Create `resources/js/Pages/Photographers/Show.tsx`:

```tsx
/**
 * Photographers/Show — a public photographer profile: masthead, portrait, bio,
 * socials and their content-builder page body. Deliberately mirrors
 * Contributors/Show so the two profile types read as one system.
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import CoverImage from '@/Components/Common/CoverImage'
import SocialLinks from '@/Components/Common/SocialLinks'
import type { FocalImage } from '@/types/media'

interface Props {
    photographer: {
        name: string
        bio: string | null
        thumbnail: FocalImage | null
        socials: Record<string, string>
        profile_blocks: any[]
    }
    static_masthead: FocalImage | null
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const Show = ({ photographer, static_masthead, meta }: Props) => (
    <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
        <StaticMasthead imageUrl={static_masthead} eyebrow="Photographer" title={photographer.name} />

        <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
            <div className="flex flex-col items-center text-center">
                {photographer.thumbnail && (
                    <div className="w-40 h-40 md:w-48 md:h-48 rounded-full overflow-hidden ring-4 ring-white shadow-xl">
                        {/* Their own portrait needs no credit badge over it. */}
                        <CoverImage
                            image={photographer.thumbnail}
                            alt={photographer.name}
                            className="w-full h-full"
                            showCredit={false}
                        />
                    </div>
                )}

                {photographer.bio && (
                    <p className="mt-6 max-w-2xl text-secondary/80 leading-relaxed">{photographer.bio}</p>
                )}

                <SocialLinks socials={photographer.socials} className="mt-6 justify-center" />
            </div>

            {photographer.profile_blocks?.length > 0 && (
                <div className="mt-12">
                    <ContentBuilder blocks={photographer.profile_blocks} />
                </div>
            )}
        </BlockWrapper>
    </Layout>
)

export default Show
```

- [ ] **Step 7: Run the test and verify it passes**

```bash
php artisan test --filter=PhotographerPageTest
```

Expected: PASS, 5 tests.

- [ ] **Step 8: Verify in the browser**

Build, then in the admin add content-builder content to a photographer and visit `/photographers/{slug}`. Confirm the page renders at mobile, tablet and desktop, and that a photographer with no content still 404s. Then set that photographer's `credit_link` to "Their page on this site" and confirm their image credits now link internally.

```bash
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build
```

- [ ] **Step 9: Run the full suite and commit**

```bash
php artisan test
git add app/Http/Controllers/PhotographerController.php resources/js/Pages/Photographers/Show.tsx routes/web.php app/Support/SitemapBuilder.php tests/Feature/PhotographerPageTest.php
git commit -m "Add public photographer profile page"
```

---

## Task 8: list_photographers content block

**Files:**
- Modify: `app/Filament/Forms/ContentBuilderBlocks.php`
- Modify: `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`
- Create: `resources/js/Components/Content/PhotographerRollUp.tsx`
- Modify: `resources/js/Components/ContentBuilder.tsx`
- Test: `tests/Feature/PhotographerRollupBlockTest.php`

**Interfaces:**
- Consumes: `Photographer::scopeWithPublicPage()` from Task 1
- Produces: block type `list_photographers`, resolved into `data.photographers_resolved` as `array{name, slug, bio, thumbnail}`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotographerRollupBlockTest.php`:

```php
<?php

// The list_photographers block auto-populates from photographers with a live
// page — the owner never hand-picks. Photographers without a page must not
// appear, since their card would link to a 404.

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerRollupBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_lists_only_photographers_with_a_live_page(): void
    {
        Photographer::factory()->withPublicPage()->create(['name' => 'Live One']);
        Photographer::factory()->create(['name' => 'No Page', 'profile_blocks' => null]);

        Page::create([
            'title' => 'About',
            'slug' => 'about-photographers-test',
            'is_published' => true,
            'content_blocks' => [
                ['type' => 'list_photographers', 'data' => ['heading' => 'Our photographers']],
            ],
        ]);

        $this->get('/about-photographers-test')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('page.content_blocks.0.data.photographers_resolved', 1)
                ->where('page.content_blocks.0.data.photographers_resolved.0.name', 'Live One'));
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

```bash
php artisan test --filter=PhotographerRollupBlockTest
```

Expected: FAIL — `photographers_resolved` is missing.

- [ ] **Step 3: Add the Filament block**

In `app/Filament/Forms/ContentBuilderBlocks.php`, add immediately after the `contributor_roll_up` block (around line 159):

```php
            Builder\Block::make('list_photographers')
                ->label('Photographer roll-up')
                ->schema([
                    TextInput::make('heading')->default('Our photographers'),
                    TextInput::make('intro')->label('Intro line')->helperText('Optional short line under the heading.'),
                ]),
```

- [ ] **Step 4: Add the resolver**

In `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`, add `use App\Models\Photographer;` to the imports, then add this immediately after the `contributor_roll_up` block (before `$block['data'] = $data;`):

```php
            // Photographer roll-up: auto-populate with every photographer whose
            // page is live. Records without profile content are excluded — their
            // card would link to a 404.
            if (($block['type'] ?? '') === 'list_photographers') {
                $data['photographers_resolved'] = Photographer::withPublicPage()
                    ->with('thumbnailMedia')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Photographer $photographer) => [
                        'name' => $photographer->name,
                        'slug' => $photographer->slug,
                        'bio' => $photographer->bio,
                        'thumbnail' => $photographer->thumbnailMedia?->imagePayload(),
                    ])
                    ->all();
            }
```

- [ ] **Step 5: Write the component**

Create `resources/js/Components/Content/PhotographerRollUp.tsx`:

```tsx
/**
 * PhotographerRollUp — content block listing photographers with a live page:
 * a portrait, name and short bio per card, linking to their profile. Mirrors
 * ContributorRollUp so the About page reads as one system.
 */
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Photographer {
    name: string
    slug: string
    bio: string | null
    thumbnail: FocalImage | null
}

interface Props {
    heading?: string
    intro?: string
    photographers: Photographer[]
}

const PhotographerRollUp = ({ heading, intro, photographers }: Props) => {
    // Defense-in-depth: the server already excludes slugless records, but never
    // render a `/photographers/null` link if one slips through.
    const validPhotographers = (photographers ?? []).filter((photographer) => photographer.slug)

    if (validPhotographers.length === 0) return null

    return (
        <div className="py-4">
            {heading && (
                <h2
                    className="font-title text-secondary uppercase text-center"
                    style={{ fontSize: 'clamp(1.75rem, 4vw, 2.75rem)' }}
                >
                    {heading}
                </h2>
            )}
            {intro && <p className="text-center text-gray-500 mt-3 max-w-2xl mx-auto">{intro}</p>}

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-10">
                {validPhotographers.map((photographer) => (
                    <Link
                        key={photographer.slug}
                        href={`/photographers/${photographer.slug}`}
                        className="group flex flex-col items-center text-center"
                    >
                        <div className="w-32 h-32 rounded-full overflow-hidden ring-4 ring-white shadow-lg group-hover:shadow-xl transition-shadow duration-300">
                            {photographer.thumbnail ? (
                                <CoverImage
                                    image={photographer.thumbnail}
                                    alt={photographer.name}
                                    className="w-full h-full group-hover:scale-105 transition-transform duration-500"
                                    showCredit={false}
                                />
                            ) : (
                                <div className="w-full h-full bg-primary-lighter" />
                            )}
                        </div>
                        <h3
                            className="font-title text-secondary uppercase mt-4 group-hover:text-primary transition-colors duration-200"
                            style={{ fontSize: 'clamp(1.1rem, 2vw, 1.35rem)' }}
                        >
                            {photographer.name}
                        </h3>
                        {photographer.bio && (
                            <p className="text-sm text-secondary/70 mt-2 max-w-xs">{photographer.bio}</p>
                        )}
                    </Link>
                ))}
            </div>
        </div>
    )
}

export default PhotographerRollUp
```

- [ ] **Step 6: Route the block**

In `resources/js/Components/ContentBuilder.tsx`, add the import alongside `ContributorRollUp`:

```tsx
import PhotographerRollUp from '@/Components/Content/PhotographerRollUp'
```

And add this case immediately after the `contributor_roll_up` case (around line 116):

```tsx
                    case 'list_photographers':
                        return (
                            <PhotographerRollUp
                                key={index}
                                heading={block.data.heading}
                                intro={block.data.intro}
                                photographers={block.data.photographers_resolved ?? []}
                            />
                        )
```

- [ ] **Step 7: Run the test and verify it passes**

```bash
php artisan test --filter=PhotographerRollupBlockTest
```

Expected: PASS, 1 test.

- [ ] **Step 8: Verify in the browser**

```bash
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build
```

Add a "Photographer roll-up" block to the About page in the admin, save, and load `/about`. Confirm the card grid renders at all three breakpoints and that a photographer with no page is absent.

- [ ] **Step 9: Run the full suite and commit**

```bash
php artisan test
git add app/Filament/Forms/ContentBuilderBlocks.php app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php resources/js/Components/Content/PhotographerRollUp.tsx resources/js/Components/ContentBuilder.tsx tests/Feature/PhotographerRollupBlockTest.php
git commit -m "Add list_photographers content block"
```

---

## Task 9: SingleImage conversion and gallery lightbox credit

**Files:**
- Modify: `resources/js/Components/Content/SingleImage.tsx`
- Modify: `resources/js/Components/Content/Gallery.tsx`

**Interfaces:**
- Consumes: `CoverImage`, `ImageCredit` from Task 5
- Produces: nothing new

**Risk note:** the lightbox half of this task is the least certain thing in the plan. `fslightbox-react@2.0.3` has no caption support and no `onSlideChange` callback (verified against the installed package), so the only mechanism is **custom sources** — passing JSX elements instead of URL strings. If it proves fiddly or looks wrong, **drop the lightbox change and keep the `SingleImage` conversion**; the gallery tile itself already shows the credit from Task 5, so this is a refinement rather than a gap.

- [ ] **Step 1: Convert SingleImage**

Replace the raw `<img>` at `resources/js/Components/Content/SingleImage.tsx:24`. Read the file first to match its surrounding props, then replace:

```tsx
                <img src={url} alt={alt} className="w-full rounded-lg" />
```

with:

```tsx
                <CoverImage image={image} alt={alt} className="w-full rounded-lg" />
```

Add the import at the top:

```tsx
import CoverImage from '@/Components/Common/CoverImage'
```

If the component currently destructures a plain `url` out of the block data, pass the whole focal image object through instead — `ResolvesContentBlockMedia` already emits `{key}_image` as a full `imagePayload()`, so the credit is present in the prop.

- [ ] **Step 2: Verify SingleImage in the browser**

```bash
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build
```

Load a page with a Single Image block and confirm it still renders at full width with rounded corners, and shows a credit when the image has a photographer.

- [ ] **Step 3: Commit the safe half**

```bash
git add resources/js/Components/Content/SingleImage.tsx
git commit -m "Render SingleImage through CoverImage so it carries credits"
```

- [ ] **Step 4: Add the lightbox credit via custom sources**

In `resources/js/Components/Content/Gallery.tsx`, replace:

```tsx
    // FsLightbox needs raw URL strings — extract from the focal-bearing objects.
    const lightboxSources = images.map((img) => img.url)
```

with:

```tsx
    // fslightbox-react@2 has no caption support, so a credited image is passed as
    // a CUSTOM SOURCE (a JSX element) rather than a URL string. Uncredited images
    // stay plain strings so the library's own sizing keeps handling the common case.
    const lightboxSources = images.map((image) =>
        image.credit ? (
            <div className="relative flex items-center justify-center">
                <img
                    src={image.url}
                    alt={image.alt ?? ''}
                    className="max-h-[85vh] max-w-[90vw] object-contain"
                />
                <ImageCredit credit={image.credit} />
            </div>
        ) : (
            image.url
        )
    )
```

And add the import:

```tsx
import ImageCredit from '@/Components/Common/ImageCredit'
```

- [ ] **Step 5: Verify the lightbox**

Rebuild, open a spot guide gallery containing at least one credited image, and click through slides. Confirm:

- Credited slides show the image at a sensible size with the badge over it
- Uncredited slides are unchanged
- Navigation between the two kinds works

If credited slides render at the wrong size or the badge is misplaced, revert this step:

```bash
git checkout resources/js/Components/Content/Gallery.tsx
```

The gallery tiles already carry credits, so nothing is lost.

- [ ] **Step 6: Run everything and commit**

```bash
php artisan test
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js && npm run build
git add resources/js/Components/Content/Gallery.tsx
git commit -m "Show photographer credit in the gallery lightbox"
```

---

## Task 10: Documentation and reconcile

**Files:**
- Create: `docs/history/2026-08-06-photographer-attribution.md`
- Modify: `CLAUDE.md`, `SITREP.md`, `docs/TODO.md`

- [ ] **Step 1: Write the history document**

Create `docs/history/2026-08-06-photographer-attribution.md` covering: why a standalone model rather than a user role, why credit_link stores a key, the derived page-visibility rule, the `$with` batching decision, the media-scoping hardening, and the deferred login with its upgrade path.

- [ ] **Step 2: Update CLAUDE.md**

Add `photographers` to the Database Schema section, `Photographer` to Models & Relationships, `PhotographerResource` to the Filament resources table, `/photographers/{slug}` to the Routes table, and `Photographers/Show` to the Inertia Pages table.

- [ ] **Step 3: Fold in the reconcile**

Per the project's git workflow, run the `reconcile-everything` skill on this branch **before** the PR merges, so reconcile docs ride in the same PR as the code.

- [ ] **Step 4: Final verification**

```bash
php artisan test
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js && npm run build
```

Expected: all green.

- [ ] **Step 5: Commit and open the PR**

```bash
git add docs CLAUDE.md SITREP.md
git commit -m "Document photographer attribution"
```

---

## Self-Review

**Spec coverage**

| Spec requirement | Task |
|---|---|
| `photographers` table incl. slug, socials, credit_link, profile fields, user_id | 1 |
| Partial-unique slug index | 1 |
| `creditPayload()` resolution matrix | 1 |
| Derived `hasPublicPage()` gate | 1 |
| `media_library.photographer_id` | 2 |
| `credit` in `imagePayload()` | 2 |
| Soft-delete behaviour | 2 |
| `PhotographerResource` + owner-only policy | 3 |
| Shared `PhotographerProfileForm::schema()` | 3 |
| `credit_link` options from filled socials, `profile` gated | 3 |
| Media library select / column / filter | 4 |
| Bulk assign action | 4 |
| Media scoping hardening | 4 |
| `ImageCredit` component, always visible, a11y label | 5 |
| External vs internal link handling | 5 |
| `CoverImage` consistent wrapper | 5 |
| Map `showCredit={false}` exclusion | 5 |
| N+1 guard | 6 |
| Public page + derived 404s | 7 |
| Sitemap inclusion | 7 |
| `list_photographers` block | 8 |
| `SingleImage` conversion | 9 |
| Lightbox credit | 9 |
| Login out of scope | — (not built, documented in Task 10) |

No spec requirement is unimplemented.

**Placeholder scan:** none — every code step contains complete code, every command has an expected result.

**Type consistency:** `ImageCreditData` is defined in Task 5's helper and imported by `types/media.ts` and `ImageCredit.tsx`. `creditPayload()` returns `array{name, url}` in Task 1 and is consumed as `{name, url}` in Task 2 and as `ImageCreditData` in Task 5 — consistent. `withPublicPage()` is defined in Task 1 and used in Tasks 7 and 8. `showCredit` is introduced in Task 5 and used in Tasks 7, 8 and 9.

**One risk flagged in place:** Task 9's lightbox step, with an explicit revert path.
