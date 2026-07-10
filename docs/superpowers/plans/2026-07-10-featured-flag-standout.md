# Featured Flag + Standout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the owner flag a single featured blog and a single featured spot guide in-app, each shown as a standout hero on its listing page (no fallback — a hero is always a deliberate choice).

**Architecture:** Add an `is_featured` boolean to `blogs` and `spot_guides`; enforce "only one featured per type" via a shared model trait; expose a Filament toggle + list column; drive the blog-index hero from the flag and add a new featured hero to `/destinations`, both via one shared `FeaturedHero` React component.

**Tech Stack:** Laravel 12 (PHP 8.2), Filament 3.3, Inertia v2 + React 19 + TypeScript, PHPUnit 11 (in-memory SQLite), Tailwind v3, Vite 7 (Node 22).

## Global Constraints

- **No fallback:** the featured item is *only* ever the flagged one. Nothing flagged → controller prop is `null` → no hero renders. Do NOT fall back to "latest published".
- **Single featured per type:** saving a row with `is_featured = true` clears `is_featured` on every *other* row of that model. Enforced at the model layer (a shared trait), so it holds for Filament, seeders, and tinker alike.
- **Public hero requires published:** controllers query `published()->where('is_featured', true)`, so an unpublished featured item yields `null` (never leaks a draft).
- **Grid behaviour differs by page, deliberately:** the blog grid EXCLUDES the featured post and shows the hero on page 1 only; the destinations continent grids are unchanged (the featured guide still appears in its continent — the hero is a spotlight, not a filter).
- **Node 22 for any npm/vite command:** prefix with `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null &&`. The shell default v14 cannot run Vite.
- **Tests:** `php artisan test` (in-memory SQLite) must stay green. Match the existing suite's style (`assertInertia(fn (Assert $page) => …)`, `Livewire::test(...)`, `$this->actingAsOwner()`); test classes do NOT declare `RefreshDatabase` (the base `Tests\TestCase` handles DB refresh).

---

### Task 1: Schema + single-featured trait + model/factory wiring

**Files:**
- Create: `database/migrations/2026_07_10_120000_add_is_featured_to_blogs_and_spot_guides.php`
- Create: `app/Models/Concerns/HasSingleFeatured.php`
- Modify: `app/Models/Blog.php` (add trait, fillable, cast)
- Modify: `app/Models/SpotGuide.php` (add trait, fillable, cast)
- Modify: `database/factories/BlogFactory.php`, `database/factories/SpotGuideFactory.php` (default `is_featured => false`)
- Test: `tests/Feature/SingleFeaturedTest.php`

**Interfaces:**
- Produces: `is_featured` boolean attribute on `Blog` and `SpotGuide`; the invariant "at most one row per model has `is_featured = true`".

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SingleFeaturedTest.php`:
```php
<?php

// Enforces the single-featured invariant: featuring one Blog / SpotGuide
// clears the flag on any previously featured row of the same model.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\SpotGuide;
use Tests\TestCase;

class SingleFeaturedTest extends TestCase
{
    public function test_featuring_a_blog_unfeatures_the_previous_one(): void
    {
        $first = Blog::factory()->create(['is_featured' => true]);
        $second = Blog::factory()->create(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    public function test_updating_is_featured_enforces_single_on_blogs(): void
    {
        $first = Blog::factory()->create(['is_featured' => true]);
        $second = Blog::factory()->create();

        $second->update(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    public function test_featuring_a_spot_guide_unfeatures_the_previous_one(): void
    {
        $first = SpotGuide::factory()->create(['is_featured' => true]);
        $second = SpotGuide::factory()->create(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SingleFeaturedTest`
Expected: FAIL — the `is_featured` column / attribute doesn't exist yet (SQL error or unknown attribute).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_10_120000_add_is_featured_to_blogs_and_spot_guides.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds a per-type "featured" flag to blogs and spot guides. Single-featured is
// enforced at the model layer (HasSingleFeatured), not by a DB constraint.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
        });

        Schema::table('spot_guides', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', fn (Blueprint $table) => $table->dropColumn('is_featured'));
        Schema::table('spot_guides', fn (Blueprint $table) => $table->dropColumn('is_featured'));
    }
};
```

- [ ] **Step 4: Create the shared trait**

Create `app/Models/Concerns/HasSingleFeatured.php`:
```php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Enforces a single featured row per model. When a row is saved with
 * is_featured = true, every OTHER row of the same model is un-featured. Uses a
 * query-builder update() (no model events) so it never recurses.
 */
trait HasSingleFeatured
{
    public static function bootHasSingleFeatured(): void
    {
        static::saved(function (Model $model) {
            if ($model->is_featured) {
                static::query()
                    ->whereKeyNot($model->getKey())
                    ->where('is_featured', true)
                    ->update(['is_featured' => false]);
            }
        });
    }
}
```

- [ ] **Step 5: Wire the trait, fillable and cast into both models**

In `app/Models/Blog.php`: add the import `use App\Models\Concerns\HasSingleFeatured;`, add `HasSingleFeatured` to the `use` inside the class (`use HasFactory, SoftDeletes, Searchable, HasSingleFeatured;`), add `'is_featured'` to `$fillable` (next to `'is_published'`), and add `'is_featured' => 'boolean',` to `$casts`.

In `app/Models/SpotGuide.php`: same — import + add `HasSingleFeatured` to the class `use` list, add `'is_featured'` to `$fillable` (next to `'is_published'`), and `'is_featured' => 'boolean',` to `$casts`. (The existing `booted()` method is unaffected — the trait's `bootHasSingleFeatured()` runs alongside it.)

- [ ] **Step 6: Default the flag in both factories**

In `database/factories/BlogFactory.php` and `database/factories/SpotGuideFactory.php`, add `'is_featured' => false,` to the `definition()` array.

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=SingleFeaturedTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models/Concerns/HasSingleFeatured.php app/Models/Blog.php app/Models/SpotGuide.php database/factories/BlogFactory.php database/factories/SpotGuideFactory.php tests/Feature/SingleFeaturedTest.php
git commit -m "feat: is_featured flag + single-featured enforcement on blogs & spot guides"
```

---

### Task 2: Filament toggle + list column (Blog & SpotGuide)

**Files:**
- Modify: `app/Filament/Resources/BlogResource.php` (General tab toggle + table column)
- Modify: `app/Filament/Resources/SpotGuideResource.php` (General tab toggle + table column)
- Test: `tests/Feature/Filament/FeaturedToggleTest.php`

**Interfaces:**
- Consumes: `is_featured` attribute + enforcement from Task 1.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/FeaturedToggleTest.php`:
```php
<?php

// The Filament Featured toggle persists and routes through the single-featured
// enforcement — featuring one record via the admin form un-features another.

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\SpotGuideResource\Pages\EditSpotGuide;
use App\Models\Blog;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FeaturedToggleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsOwner();
    }

    public function test_featuring_a_blog_via_the_admin_unfeatures_the_previous(): void
    {
        $current = Blog::factory()->create(['is_featured' => true]);
        $target = Blog::factory()->create();

        Livewire::test(EditBlog::class, ['record' => $target->getRouteKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->is_featured);
        $this->assertFalse($current->fresh()->is_featured);
    }

    public function test_featuring_a_spot_guide_via_the_admin_unfeatures_the_previous(): void
    {
        Queue::fake(); // swallow any weather job

        $current = SpotGuide::factory()->create(['is_featured' => true]);
        $target = SpotGuide::factory()->create();

        Livewire::test(EditSpotGuide::class, ['record' => $target->getRouteKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->is_featured);
        $this->assertFalse($current->fresh()->is_featured);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=FeaturedToggleTest`
Expected: FAIL — the form has no `is_featured` field, so `fillForm(['is_featured' => true])` sets nothing and `$target->fresh()->is_featured` stays false.

- [ ] **Step 3: Add the toggle + column to BlogResource**

In `app/Filament/Resources/BlogResource.php`, inside the `General` tab schema, immediately after `Toggle::make('is_published')->label('Published'),` add:
```php
                            Toggle::make('is_featured')
                                ->label('Featured')
                                ->helperText('Only one blog can be featured — turning this on clears it from any other post.'),
```
In the same file's `table()` columns, after the published `IconColumn`, add (import `Filament\Tables\Columns\ToggleColumn` at the top if not present):
```php
                ToggleColumn::make('is_featured')->label('Featured'),
```

- [ ] **Step 4: Add the toggle + column to SpotGuideResource**

In `app/Filament/Resources/SpotGuideResource.php`, inside the `General` tab, immediately after `Toggle::make('is_published')->label('Published'),` add:
```php
                            Toggle::make('is_featured')
                                ->label('Featured')
                                ->helperText('Only one spot guide can be featured — turning this on clears it from any other guide.'),
```
In its `table()` columns, after the published `IconColumn`, add (import `Filament\Tables\Columns\ToggleColumn` if not present):
```php
                ToggleColumn::make('is_featured')->label('Featured'),
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=FeaturedToggleTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/BlogResource.php app/Filament/Resources/SpotGuideResource.php tests/Feature/Filament/FeaturedToggleTest.php
git commit -m "feat: Featured toggle + list column on Blog and SpotGuide resources"
```

---

### Task 3: Shared FeaturedHero + flag-driven blog index hero

**Files:**
- Create: `resources/js/Components/Common/FeaturedHero.tsx`
- Modify: `app/Http/Controllers/BlogController.php` (index: featured query + prop + grid exclusion)
- Modify: `resources/js/Pages/Blog/Index.tsx` (use `featured` prop + FeaturedHero)
- Test: `tests/Feature/BlogControllerTest.php` (add cases)

**Interfaces:**
- Consumes: `is_featured` from Task 1.
- Produces: `FeaturedHero` React component with props
  `{ image: FocalImage | null; eyebrow: string; title: string; description?: string | null; metaLabel?: string | null; href: string; ctaLabel: string }`
  (consumed again by Task 4). Blog index gains a `featured: Blog | null` Inertia prop.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BlogControllerTest.php` (inside the class):
```php
    public function test_index_passes_the_featured_blog_and_excludes_it_from_the_grid(): void
    {
        Blog::factory()->create(['title' => 'Featured Star', 'slug' => 'featured-star', 'is_featured' => true]);
        Blog::factory()->count(2)->create();

        $this->get(route('blog.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('featured.title', 'Featured Star')
                ->has('blogs.data', 2) // 3 published, featured excluded from the grid
            );
    }

    public function test_index_featured_is_null_when_none_flagged(): void
    {
        Blog::factory()->count(2)->create();

        $this->get(route('blog.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('featured', null)
                ->has('blogs.data', 2)
            );
    }

    public function test_index_featured_is_null_when_the_flagged_blog_is_unpublished(): void
    {
        Blog::factory()->create(['is_published' => false, 'is_featured' => true]);

        $this->get(route('blog.index'))
            ->assertInertia(fn (Assert $page) => $page->where('featured', null));
    }
```
If `Blog` and `Inertia\Testing\AssertableInertia as Assert` aren't already imported at the top of the file, add them.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=BlogControllerTest`
Expected: FAIL — there is no `featured` prop yet (it's undefined, and the grid still includes all 3).

- [ ] **Step 3: Update BlogController@index**

In `app/Http/Controllers/BlogController.php`, replace the `$blogs = Blog::published()...` assignment and the `return Inertia::render('Blog/Index', [...])` with:
```php
        // The featured post is an explicit, owner-set choice — no fallback to
        // "latest". Null when nothing is flagged (or the flagged post is a draft),
        // in which case the index shows no hero. Excluded from the grid so it
        // only ever appears once, as the hero.
        $featured = Blog::published()
            ->where('is_featured', true)
            ->with(['thumbnailMedia'])
            ->first();

        $blogs = Blog::published()
            ->when($featured, fn ($query) => $query->whereKeyNot($featured->id))
            ->with(['thumbnailMedia'])
            ->latest('published_at')
            ->paginate(12)
            ->through(fn ($blog) => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'published_at' => $blog->published_at?->toDateString(),
                'thumbnail' => $blog->thumbnailMedia?->imagePayload(),
                'seo_description' => $blog->seo_description,
            ]);

        return Inertia::render('Blog/Index', [
            'blogs' => $blogs,
            'featured' => $featured ? [
                'id' => $featured->id,
                'title' => $featured->title,
                'slug' => $featured->slug,
                'published_at' => $featured->published_at?->toDateString(),
                'thumbnail' => $featured->thumbnailMedia?->imagePayload(),
                'seo_description' => $featured->seo_description,
            ] : null,
            'static_masthead' => $page?->staticMastheadMedia?->imagePayload(),
            'meta' => [
                'title' => $page?->seo_title ?: 'Blog',
                'description' => $page?->seo_description ?: 'Windsurfing tips, guides and destination insights.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
        ]);
```

- [ ] **Step 4: Run the controller test to verify it passes**

Run: `php artisan test --filter=BlogControllerTest`
Expected: PASS (existing cases + the 3 new ones).

- [ ] **Step 5: Create the shared FeaturedHero component**

Create `resources/js/Components/Common/FeaturedHero.tsx`:
```tsx
// A large "featured" standout card used at the top of listing pages (blog index,
// destinations). Image on the left, editorial content on the right. Purely
// presentational — the caller decides what is featured and formats the meta line.

import { Link } from '@inertiajs/react'
import AnimateInView from '@/Components/Common/AnimateInView'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Props {
    /** Focal-bearing image, or null to render a plain brand-colour panel. */
    image: FocalImage | null
    /** Small uppercase kicker above the title, e.g. "Featured". */
    eyebrow: string
    title: string
    /** Optional supporting paragraph (blog description). */
    description?: string | null
    /** Optional small uppercase meta line (blog date, or a guide's country). */
    metaLabel?: string | null
    href: string
    /** Call-to-action text, e.g. "Read article" / "Explore guide". */
    ctaLabel: string
}

/** Right-pointing arrow used in the CTA. */
const ArrowIcon = ({ className }: { className?: string }) => (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
    </svg>
)

/**
 * Render the featured standout card.
 * @param props - See {@link Props}.
 */
const FeaturedHero = ({ image, eyebrow, title, description, metaLabel, href, ctaLabel }: Props) => (
    <AnimateInView classes="mb-12 md:mb-16" outViewClasses="translate-y-8 opacity-0" delayClasses="delay-0" durationClasses="duration-700">
        <Link
            href={href}
            className="group block md:grid md:grid-cols-5 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-500 bg-white"
        >
            <div className="md:col-span-3 aspect-[16/10] md:aspect-auto overflow-hidden relative min-h-[280px]">
                {image ? (
                    <CoverImage
                        image={image}
                        alt={title}
                        className="w-full h-full group-hover:scale-105 transition-transform duration-700"
                    />
                ) : (
                    <div className="w-full h-full bg-primary-lighter" />
                )}
                <div className="absolute inset-0 bg-gradient-to-r from-transparent to-black/15 hidden md:block pointer-events-none" />
            </div>

            <div className="md:col-span-2 flex flex-col justify-center p-8 md:p-10 lg:p-14 border-l-[3px] border-l-transparent group-hover:border-l-primary transition-all duration-500">
                <span className="text-[10px] uppercase tracking-[0.4em] text-orange font-semibold mb-4">
                    {eyebrow}
                </span>
                <h2
                    className="font-title text-secondary uppercase leading-[1.0] group-hover:text-primary transition-colors duration-300"
                    style={{ fontSize: 'clamp(1.75rem, 3vw, 2.75rem)' }}
                >
                    {title}
                </h2>
                {description && (
                    <p className="text-gray-500 mt-5 text-sm leading-relaxed line-clamp-3">
                        {description}
                    </p>
                )}
                {metaLabel && (
                    <p className="text-primary/60 text-[10px] uppercase tracking-[0.35em] mt-6">
                        {metaLabel}
                    </p>
                )}
                <span className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary group-hover:gap-4 transition-all duration-300">
                    {ctaLabel} <ArrowIcon className="w-4 h-4" />
                </span>
            </div>
        </Link>
    </AnimateInView>
)

export default FeaturedHero
```

- [ ] **Step 6: Refactor Blog/Index.tsx onto the `featured` prop + FeaturedHero**

In `resources/js/Pages/Blog/Index.tsx`:

(a) Add the import: `import FeaturedHero from '@/Components/Common/FeaturedHero'`.

(b) Add `featured: Blog | null` to the `Props` interface and destructure it: `const Index = ({ blogs, featured, static_masthead, meta }: Props) => {`.

(c) Delete the line `const [featured, ...rest] = blogs.data`. (Only the inline hero JSX is replaced, below. Keep the local `ArrowIcon` const and `formatDate` — both are still used by the grid cards.)

(d) Replace the entire `{/* Featured post */} {featured && ( … )}` block (the `<AnimateInView>…</AnimateInView>` hero) with:
```tsx
                {/* Featured post — owner-flagged, shown on page 1 only */}
                {featured && blogs.meta.current_page === 1 && (
                    <FeaturedHero
                        image={featured.thumbnail}
                        eyebrow="Featured"
                        title={featured.title}
                        description={featured.seo_description}
                        metaLabel={featured.published_at ? formatDate(featured.published_at) : null}
                        href={`/blog/${featured.slug}`}
                        ctaLabel="Read article"
                    />
                )}
```

(e) In the article grid, change the guard and the map source from `rest` to `blogs.data`:
```tsx
                {blogs.data.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {blogs.data.map((blog, i) => (
```

(f) Do NOT remove the local `ArrowIcon` — the grid cards ("Read more", ~line 167) still use it. `FeaturedHero` carries its own private copy; this tiny duplication is deliberate (extracting a shared arrow icon is out of scope). Leave `formatDate` in place too.

- [ ] **Step 7: Build to verify no type/JSX errors**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Expected: build succeeds. (Live visual verification is the controller's post-task pass.)

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Common/FeaturedHero.tsx app/Http/Controllers/BlogController.php resources/js/Pages/Blog/Index.tsx tests/Feature/BlogControllerTest.php
git commit -m "feat: flag-driven blog featured hero via shared FeaturedHero"
```

---

### Task 4: Destinations featured hero

**Files:**
- Modify: `app/Http/Controllers/DestinationController.php` (featured query + prop)
- Modify: `resources/js/Pages/Destinations/Index.tsx` (render FeaturedHero)
- Test: `tests/Feature/DestinationControllerTest.php` (add cases)

**Interfaces:**
- Consumes: `is_featured` (Task 1) and `FeaturedHero` (Task 3). Adds a `featuredSpotGuide` Inertia prop `{ id, title, slug, country: string | null, thumbnail: FocalImage | null } | null`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/DestinationControllerTest.php` (inside the class):
```php
    public function test_index_passes_the_featured_spot_guide_and_keeps_it_in_the_grid(): void
    {
        SpotGuide::factory()->create(['title' => 'Featured Bay', 'slug' => 'featured-bay', 'is_featured' => true]);
        SpotGuide::factory()->create();

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('featuredSpotGuide.title', 'Featured Bay')
                ->has('spotGuides', 2) // the featured guide is still listed in its continent grid
            );
    }

    public function test_index_featured_spot_guide_is_null_when_none_flagged(): void
    {
        SpotGuide::factory()->count(2)->create();

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('featuredSpotGuide', null));
    }

    public function test_index_featured_spot_guide_is_null_when_unpublished(): void
    {
        SpotGuide::factory()->create(['is_published' => false, 'is_featured' => true]);

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('featuredSpotGuide', null));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DestinationControllerTest`
Expected: FAIL — no `featuredSpotGuide` prop yet.

- [ ] **Step 3: Update DestinationController@index**

In `app/Http/Controllers/DestinationController.php`, after the `$spotGuidesData = …` mapping (before `$weatherData`), add:
```php
        // The featured guide is an explicit, owner-set choice (no fallback). Null
        // when nothing is flagged or the flagged guide is a draft. It is NOT
        // removed from $spotGuides — the hero is a spotlight, the grids remain the
        // complete directory.
        $featured = SpotGuide::published()
            ->where('is_featured', true)
            ->with(['country', 'thumbnailMedia'])
            ->first();
```
Then add to the `Inertia::render('Destinations/Index', [...])` props array (e.g. after `'spotGuides' => $spotGuidesData,`):
```php
            'featuredSpotGuide' => $featured ? [
                'id' => $featured->id,
                'title' => $featured->title,
                'slug' => $featured->slug,
                'country' => $featured->country?->name,
                'thumbnail' => $featured->thumbnailMedia?->imagePayload(),
            ] : null,
```

- [ ] **Step 4: Run the controller test to verify it passes**

Run: `php artisan test --filter=DestinationControllerTest`
Expected: PASS (existing + 3 new cases).

- [ ] **Step 5: Render the hero on the destinations page**

In `resources/js/Pages/Destinations/Index.tsx`:

(a) Add the import: `import FeaturedHero from '@/Components/Common/FeaturedHero'`.

(b) Add a `featuredSpotGuide` field to the `Props` interface:
```tsx
    featuredSpotGuide: {
        id: number
        title: string
        slug: string
        country: string | null
        thumbnail: FocalImage | null
    } | null
```
and destructure it: `const Index = ({ spotGuides, weatherData, static_masthead, featuredSpotGuide, meta }: Props) => {`.

(c) Between the editorial intro `</section>` and the `{/* ─── Map ─── */}` comment, insert:
```tsx
            {/* ─── Featured destination ─── */}
            {featuredSpotGuide && (
                <section className="bg-white">
                    <div className="container mx-auto py-14 lg:py-16">
                        <FeaturedHero
                            image={featuredSpotGuide.thumbnail}
                            eyebrow="Featured Destination"
                            title={featuredSpotGuide.title}
                            metaLabel={featuredSpotGuide.country}
                            href={`/destinations/${featuredSpotGuide.slug}`}
                            ctaLabel="Explore guide"
                        />
                    </div>
                </section>
            )}
```

- [ ] **Step 6: Build to verify no type/JSX errors**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Expected: build succeeds.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DestinationController.php resources/js/Pages/Destinations/Index.tsx tests/Feature/DestinationControllerTest.php
git commit -m "feat: featured spot-guide hero on destinations page"
```

---

## Final verification (before PR)

- [ ] `php artisan test` — full suite green (existing + new SingleFeatured / FeaturedToggle / Blog / Destination cases).
- [ ] `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build` — production build succeeds.
- [ ] Live preview (controller's pass): in `/admin`, toggle Featured on a blog and a spot guide, confirm the previous one clears. On `/blog` confirm the featured hero shows (page 1) and isn't duplicated in the grid; with nothing featured, no hero. On `/destinations` confirm the featured hero appears between the intro and map, and the guide still shows in its continent grid. Verify both at mobile + desktop; confirm the refactored blog hero looks unchanged.
