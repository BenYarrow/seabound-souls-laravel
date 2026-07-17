# Contributor Profiles + About Roll-up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give each contributor a public profile page (content-builder body, photo, socials, their guides) that goes live once they have a published guide, a clickable attribution byline, and an About page that lists contributors.

**Architecture:** Extend the `users` table with profile columns (no separate model). Visibility is derived (`hasPublicProfile()` = contributor with ≥1 published guide), reusing sub-project 1's gate. A public `/contributors/{slug}` page + an auto-populating "contributor roll-up" content block on the About page. Contributors self-edit via a Filament "My Profile" page; the owner edits via `ContributorResource`.

**Tech Stack:** Laravel 12, Inertia v2 + React 19, Filament v3, PostgreSQL (dev/prod) / SQLite (tests), FontAwesome brand icons (`@fortawesome/free-brands-svg-icons`, already installed).

## Global Constraints

- Branch is `feature/contributor-profiles`. No direct commits to `main`.
- TDD: failing test first, watch it fail, implement, watch it pass, commit. Run `php artisan test` before calling any task done (baseline: **228 passing**).
- Node 22 for any Vite/npm command: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"`.
- Tests run on in-memory SQLite; dev/prod is PostgreSQL. Migration is plain columns + FKs (portable) — still smoke-test on the real Postgres dev DB.
- PHPUnit-style tests: extend `Tests\TestCase`, `use RefreshDatabase`, `test_` prefix. Module-header comment atop every new source file **and every new test file** (repo convention). PHPDoc where non-obvious; no single-letter vars (except trivial loop index); no cryptic abbreviations.
- Front-end: reuse existing primitives (`StaticMasthead`, `BlockWrapper`, `CoverImage`, `ContentBuilder`, `AnimateInView`, the guide-card look); dark-mode-aware + responsive; match tracked `@/…` import case exactly (case-sensitive Linux build).
- Visibility is **derived, never a manual flag**: `hasPublicProfile()` = `isContributor()` && ≥1 published authored guide. Owners have no public profile.
- URL prefix lives in one place: the named route `contributors.show`. All links use `/contributors/{slug}` built from it.
- **Design-model note:** the approved spec's "short intro / short blurb" has no dedicated column — realized as the content-builder body (profile page) + a published-guide count (roll-up card). No new `tagline`/`bio` field (keeps to the approved 5 new columns).

---

### Task 1: Data layer — `users` profile columns, model, factory

**Files:**
- Create: `database/migrations/2026_07_15_100000_add_profile_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/ContributorProfileModelTest.php`

**Interfaces:**
- Produces on `User`:
  - columns `slug`, `profile_image_media_id`, `static_masthead_media_id`, `profile_blocks` (array cast), `socials` (array cast)
  - `profileImageMedia(): BelongsTo`, `staticMastheadMedia(): BelongsTo`
  - `hasPublicProfile(): bool`
  - `publishedAuthoredGuides(): HasMany` (authored guides where `is_published = true`)
  - `scopeWithPublicProfile($query)` — contributors having ≥1 published guide
  - slug auto-generated from first+last on save (contributors only, collision-suffixed)
- Produces on `UserFactory`: `->contributor()` state (role + first/last) and `->withPublishedGuide()` helper pattern used by later tests (see step 1).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Tests contributor profile fields on the User model: slug generation from
// first+last (collision-safe, contributors only), derived public-profile
// visibility, casts, and media relations.

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorProfileModelTest extends TestCase
{
    use RefreshDatabase;

    private function contributor(string $first, string $last): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CONTRIBUTOR,
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    public function test_slug_is_generated_from_first_and_last_name(): void
    {
        $user = $this->contributor('Jane', 'Smith');
        $this->assertSame('jane-smith', $user->slug);
    }

    public function test_slug_collision_gets_a_numeric_suffix(): void
    {
        $this->contributor('Jane', 'Smith');
        $second = $this->contributor('Jane', 'Smith');
        $this->assertSame('jane-smith-2', $second->slug);
    }

    public function test_owner_gets_no_slug(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'first_name' => null, 'last_name' => null]);
        $this->assertNull($owner->slug);
    }

    public function test_has_public_profile_only_when_contributor_has_a_published_guide(): void
    {
        $withPublished = $this->contributor('Ann', 'Long');
        SpotGuide::factory()->create(['user_id' => $withPublished->id, 'is_published' => true]);

        $draftOnly = $this->contributor('Bea', 'Short');
        SpotGuide::factory()->create(['user_id' => $draftOnly->id, 'is_published' => false]);

        $none = $this->contributor('Cass', 'None');

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        SpotGuide::factory()->create(['user_id' => $owner->id, 'is_published' => true]);

        $this->assertTrue($withPublished->hasPublicProfile());
        $this->assertFalse($draftOnly->hasPublicProfile());
        $this->assertFalse($none->hasPublicProfile());
        $this->assertFalse($owner->hasPublicProfile());
    }

    public function test_with_public_profile_scope_matches_has_public_profile(): void
    {
        $live = $this->contributor('Dee', 'Live');
        SpotGuide::factory()->create(['user_id' => $live->id, 'is_published' => true]);
        $this->contributor('Eve', 'Empty');

        $slugs = User::withPublicProfile()->pluck('slug');
        $this->assertTrue($slugs->contains('dee-live'));
        $this->assertFalse($slugs->contains('eve-empty'));
    }

    public function test_socials_and_profile_blocks_cast_to_array_and_media_relations_resolve(): void
    {
        $image = MediaLibrary::create(['name' => 'Portrait']);
        $masthead = MediaLibrary::create(['name' => 'Hero']);
        $user = User::factory()->create([
            'role' => User::ROLE_CONTRIBUTOR,
            'first_name' => 'Fay',
            'last_name' => 'Media',
            'socials' => ['instagram' => 'https://instagram.com/fay'],
            'profile_blocks' => [['type' => 'rich_text', 'data' => []]],
            'profile_image_media_id' => $image->id,
            'static_masthead_media_id' => $masthead->id,
        ]);

        $this->assertIsArray($user->socials);
        $this->assertIsArray($user->profile_blocks);
        $this->assertTrue($user->profileImageMedia->is($image));
        $this->assertTrue($user->staticMastheadMedia->is($masthead));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContributorProfileModelTest`
Expected: FAIL — unknown column `slug` / method `hasPublicProfile` not found.

- [ ] **Step 3: Create the migration**

```php
<?php

// Public profile fields for contributor users (sub-project 2). All nullable and
// only populated for contributors; owners are unaffected. Images reference the
// centralised media_library by FK. profile_blocks is the content builder; socials
// is a small platform→URL map. `users` has no soft-deletes, so slug is a plain
// unique column (the model generates collision-safe values).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->foreignId('profile_image_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->foreignId('static_masthead_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->json('profile_blocks')->nullable();
            $table->json('socials')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_image_media_id');
            $table->dropConstrainedForeignId('static_masthead_media_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'profile_blocks', 'socials']);
        });
    }
};
```

- [ ] **Step 4: Extend the `User` model**

Add imports near the top (alongside the existing `HasMany`):

```php
use App\Support\SlugGenerator; // created in this step, below
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

Add the new columns to `$fillable`:

```php
        'slug',
        'profile_image_media_id',
        'static_masthead_media_id',
        'profile_blocks',
        'socials',
```

Add casts (extend the existing `casts()` return array):

```php
            'profile_blocks' => 'array',
            'socials' => 'array',
```

Extend the existing `booted()` saving hook to also generate the slug (keep the existing name-sync logic; add the slug block inside the same `static::saving` closure, after the name sync):

```php
            // Contributors get a public URL slug from their full name, generated
            // once and kept stable. Collision-suffixed so identical names differ.
            if ($user->role === self::ROLE_CONTRIBUTOR && blank($user->slug) && ($user->first_name || $user->last_name)) {
                $base = \Illuminate\Support\Str::slug(trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
                $user->slug = SlugGenerator::unique($base, static::class, 'slug', $user->id);
            }
```

Add the relations + profile methods (after `authoredSpotGuides()`):

```php
    /** Portrait image — roll-up card thumbnail + profile-page portrait. */
    public function profileImageMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'profile_image_media_id');
    }

    /** Profile-page hero image (null → gradient masthead fallback). */
    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    /**
     * Authored guides that are published — the ones shown on the public profile
     * and the gate for whether the profile exists at all.
     *
     * @return HasMany<SpotGuide>
     */
    public function publishedAuthoredGuides(): HasMany
    {
        return $this->authoredSpotGuides()->where('is_published', true);
    }

    /**
     * A contributor's public profile is live only once they have a published
     * guide — public presence earned by contributing. Owners have no profile.
     */
    public function hasPublicProfile(): bool
    {
        return $this->isContributor() && $this->publishedAuthoredGuides()->exists();
    }

    /** Contributors whose public profile is live (≥1 published guide). */
    public function scopeWithPublicProfile(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_CONTRIBUTOR)
            ->whereHas('authoredSpotGuides', fn (Builder $guideQuery) => $guideQuery->where('is_published', true));
    }
```

Create the slug helper `app/Support/SlugGenerator.php`:

```php
<?php

// Generates a slug unique within a model's table by appending -2, -3, … on
// collision. Shared so slug logic isn't duplicated per model.

namespace App\Support;

class SlugGenerator
{
    /**
     * Return $base, or $base-N, guaranteed unique in $modelClass.$column,
     * ignoring the row with id $ignoreId (for updates).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    public static function unique(string $base, string $modelClass, string $column = 'slug', ?int $ignoreId = null): string
    {
        $candidate = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where($column, $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
```

- [ ] **Step 5: Add factory states**

In `database/factories/UserFactory.php`, add:

```php
    /** An invited contributor with structured first/last names (slug auto-generates). */
    public function contributor(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_CONTRIBUTOR,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ]);
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=ContributorProfileModelTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_15_100000_add_profile_fields_to_users_table.php app/Models/User.php app/Support/SlugGenerator.php database/factories/UserFactory.php tests/Feature/ContributorProfileModelTest.php
git commit -m "feat: contributor profile fields on users (slug, images, blocks, socials)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Public profile page — route + `ContributorController@show`

**Files:**
- Create: `app/Http/Controllers/ContributorController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ContributorProfilePageTest.php`

**Interfaces:**
- Consumes: `User::withPublicProfile()`, `hasPublicProfile()`, `publishedAuthoredGuides()` (Task 1); `MediaLibrary::imagePayload()`; `ResolvesContentBlockMedia` trait.
- Produces: route `contributors.show` at `/contributors/{slug}`; Inertia page `Contributors/Show` with props `{ contributor: { name, profile_image, socials, profile_blocks }, static_masthead, guides[], meta }`. `guides` items: `{ id, title, slug, thumbnail, country }`. `socials` contains only filled entries.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Tests the public contributor profile page: 404 gating (unknown slug, owner,
// contributor without a published guide), the payload shape, filled-only socials,
// and published-only guides.

namespace Tests\Feature;

use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function liveContributor(array $attributes = []): User
    {
        $user = User::factory()->contributor()->create($attributes);
        SpotGuide::factory()->create(['user_id' => $user->id, 'is_published' => true]);

        return $user->refresh();
    }

    public function test_live_profile_renders_with_published_guides(): void
    {
        $user = $this->liveContributor(['first_name' => 'Jane', 'last_name' => 'Smith']);
        SpotGuide::factory()->create(['user_id' => $user->id, 'is_published' => false]); // draft excluded

        $this->get('/contributors/'.$user->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contributors/Show', false)
                ->where('contributor.name', 'Jane Smith')
                ->has('guides', 1));
    }

    public function test_unknown_slug_404s(): void
    {
        $this->get('/contributors/nobody')->assertNotFound();
    }

    public function test_contributor_without_published_guide_404s(): void
    {
        $user = User::factory()->contributor()->create();
        $this->get('/contributors/'.$user->slug)->assertNotFound();
    }

    public function test_owner_has_no_public_profile(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'first_name' => 'The', 'last_name' => 'Owner', 'slug' => 'the-owner']);
        SpotGuide::factory()->create(['user_id' => $owner->id, 'is_published' => true]);

        $this->get('/contributors/the-owner')->assertNotFound();
    }

    public function test_socials_payload_contains_only_filled_entries(): void
    {
        $user = $this->liveContributor([
            'first_name' => 'Sam', 'last_name' => 'Social',
            'socials' => ['instagram' => 'https://instagram.com/sam', 'youtube' => '', 'tiktok' => null],
        ]);

        $this->get('/contributors/'.$user->slug)
            ->assertInertia(fn ($page) => $page
                ->where('contributor.socials', ['instagram' => 'https://instagram.com/sam']));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContributorProfilePageTest`
Expected: FAIL — route/controller missing.

- [ ] **Step 3: Create `ContributorController`**

```php
<?php

// Public contributor profile: GET /contributors/{slug}. Renders a contributor's
// content-builder profile, socials, and published guides. Resolves among users
// WITH a public profile (contributor + ≥1 published guide), so unknown slugs,
// owners, and not-yet-live contributors all 404 — public presence is earned by
// publishing a guide.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ContributorController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render a live contributor profile, or 404 if the profile isn't public.
     *
     * @param  string  $slug  the contributor's URL slug
     */
    public function show(string $slug): Response
    {
        $contributor = User::where('slug', $slug)->firstOrFail();
        abort_unless($contributor->hasPublicProfile(), 404);

        $contributor->load(['profileImageMedia', 'staticMastheadMedia']);

        $guides = $contributor->publishedAuthoredGuides()
            ->with(['thumbnailMedia', 'country'])
            ->latest('published_at')
            ->get()
            ->map(fn ($guide) => [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'thumbnail' => $guide->thumbnailMedia?->imagePayload(),
                'country' => $guide->country?->name,
            ]);

        // Only non-empty socials reach the front end (blank/absent keys hidden).
        $socials = collect($contributor->socials ?? [])
            ->filter(fn ($url) => filled($url))
            ->all();

        return Inertia::render('Contributors/Show', [
            'contributor' => [
                'name' => $contributor->name,
                'profile_image' => $contributor->profileImageMedia?->imagePayload(),
                'socials' => $socials,
                'profile_blocks' => $this->resolveContentBlockMedia($contributor->profile_blocks ?? []),
            ],
            'static_masthead' => $contributor->staticMastheadMedia?->imagePayload(),
            'guides' => $guides,
            'meta' => [
                'title' => "{$contributor->name} — Seabound Souls",
                'description' => "Windsurfing spot guides and story from {$contributor->name}.",
                'keywords' => [],
                'og_image' => $contributor->profileImageMedia?->getUrl() ?: '',
            ],
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add `use App\Http\Controllers\ContributorController;` (alphabetical) and add this line with the other named web routes, before the catch-all `/{slug}` (two segments, so no collision, but keep it grouped):

```php
Route::get('/contributors/{slug}', [ContributorController::class, 'show'])->name('contributors.show');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ContributorProfilePageTest`
Expected: PASS (5 tests). (`Contributors/Show.tsx` is built in Task 7; `->component(..., false)` skips the file-exists check — this repo has `ensure_pages_exist` on.)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ContributorController.php routes/web.php tests/Feature/ContributorProfilePageTest.php
git commit -m "feat: /contributors/{slug} public profile controller + route

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Clickable byline payload — `authorPayload()` gains `slug`

**Files:**
- Modify: `app/Models/SpotGuide.php` (`authorPayload()`)
- Test: `tests/Feature/AuthorPayloadSlugTest.php`

**Interfaces:**
- Consumes: `User::$slug` (Task 1).
- Produces: `SpotGuide::authorPayload()` returns `{ kind, name, slug }` — `slug` is the contributor's slug, or `null` for house guides. Consumed by `DestinationController@index` and `SpotGuideController@show` (both already call `authorPayload()`), and by the frontend byline links in Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Tests that authorPayload() carries the contributor's slug (for linking the
// byline to their profile), and null for house-authored guides.

namespace Tests\Feature;

use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorPayloadSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_guide_payload_includes_the_author_slug(): void
    {
        $contributor = User::factory()->contributor()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        $guide = SpotGuide::factory()->create(['user_id' => $contributor->id]);

        $payload = $guide->authorPayload();

        $this->assertSame('contributor', $payload['kind']);
        $this->assertSame('Jane Smith', $payload['name']);
        $this->assertSame('jane-smith', $payload['slug']);
    }

    public function test_house_guide_payload_has_null_slug(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $guide = SpotGuide::factory()->create(['user_id' => $owner->id]);

        $payload = $guide->authorPayload();

        $this->assertSame('house', $payload['kind']);
        $this->assertNull($payload['slug']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuthorPayloadSlugTest`
Expected: FAIL — `slug` key missing.

- [ ] **Step 3: Add `slug` to `authorPayload()`**

In `app/Models/SpotGuide.php`, update `authorPayload()` and its docblock:

```php
    /**
     * Public attribution shape for this guide: a contributor (named, with a
     * profile slug) or the house. House = owner-authored or no author.
     *
     * @return array{kind: 'house'|'contributor', name: string|null, slug: string|null}
     */
    public function authorPayload(): array
    {
        $isContributor = $this->author && $this->author->isContributor();

        return [
            'kind' => $isContributor ? 'contributor' : 'house',
            'name' => $isContributor ? $this->author->name : null,
            'slug' => $isContributor ? $this->author->slug : null,
        ];
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AuthorPayloadSlugTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/SpotGuide.php tests/Feature/AuthorPayloadSlugTest.php
git commit -m "feat: authorPayload carries contributor slug for byline links

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: About page rename (`about-us` → `about`) + 301

**Files:**
- Create: `database/migrations/2026_07_15_100010_rename_about_us_page_slug.php`
- Modify: `routes/web.php` (301 redirect)
- Modify: `resources/js/Components/Common/NavBar.tsx` (nav href)
- Test: `tests/Feature/AboutRenameTest.php`

**Interfaces:**
- Consumes: the existing `Page` with slug `about-us` (served by `PageController@show` via the catch-all).
- Produces: `/about` serves the page; `/about-us` 301-redirects to `/about`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Tests the about-us → about slug rename: /about serves the page, /about-us
// permanently redirects to it (so old links survive).

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_serves_the_page_and_about_us_redirects(): void
    {
        Page::factory()->create(['slug' => 'about', 'title' => 'About', 'is_published' => true]);

        $this->get('/about')->assertOk();
        $this->get('/about-us')->assertRedirect('/about')->assertStatus(301);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AboutRenameTest`
Expected: FAIL — `/about-us` returns 200/404, not a 301.

- [ ] **Step 3: Create the slug-rename migration**

```php
<?php

// Rename the existing "about-us" Page to "about" (prod holds this row). Idempotent
// and reversible. The nav link + a 301 from /about-us are handled in code/routes.

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Page::withTrashed()->where('slug', 'about-us')->update(['slug' => 'about']);
    }

    public function down(): void
    {
        Page::withTrashed()->where('slug', 'about')->update(['slug' => 'about-us']);
    }
};
```

- [ ] **Step 4: Add the 301 redirect**

In `routes/web.php`, add (before the catch-all `/{slug}`):

```php
// Old About URL kept alive after the about-us → about rename.
Route::redirect('/about-us', '/about', 301);
```

- [ ] **Step 5: Update the nav link**

In `resources/js/Components/Common/NavBar.tsx`, change the About link href:

```tsx
    { title: 'About Us', href: '/about' },
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=AboutRenameTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_15_100010_rename_about_us_page_slug.php routes/web.php resources/js/Components/Common/NavBar.tsx tests/Feature/AboutRenameTest.php
git commit -m "feat: rename about-us page to about (+ 301 redirect + nav)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Contributor roll-up content block (registry + resolver)

**Files:**
- Modify: `app/Filament/Forms/ContentBuilderBlocks.php` (register block)
- Modify: `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php` (resolve block)
- Test: `tests/Feature/ContributorRollupBlockTest.php`

**Interfaces:**
- Consumes: `User::withPublicProfile()` (Task 1); `MediaLibrary::imagePayload()`.
- Produces: a `contributor_roll_up` builder block (fields: `heading`, `intro`); when resolved, its data gains `contributors_resolved` = array of `{ name, slug, profile_image, guides_count }` for every contributor with a public profile (ordered by name). Consumed by `ContentBuilder.tsx` in Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Tests that the contributor_roll_up content block resolves to only the
// contributors with a public profile (published guide), with their card data.

namespace Tests\Feature;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorRollupBlockTest extends TestCase
{
    use RefreshDatabase;

    /** Expose the protected trait method for testing. */
    private function resolver(): object
    {
        return new class
        {
            use ResolvesContentBlockMedia;

            public function run(array $blocks): array
            {
                return $this->resolveContentBlockMedia($blocks);
            }
        };
    }

    public function test_rollup_resolves_only_public_profile_contributors(): void
    {
        $live = User::factory()->contributor()->create(['first_name' => 'Ann', 'last_name' => 'Live']);
        SpotGuide::factory()->create(['user_id' => $live->id, 'is_published' => true]);

        $draftOnly = User::factory()->contributor()->create(['first_name' => 'Bea', 'last_name' => 'Draft']);
        SpotGuide::factory()->create(['user_id' => $draftOnly->id, 'is_published' => false]);

        $blocks = [['type' => 'contributor_roll_up', 'data' => ['heading' => 'Our Crew']]];
        $resolved = $this->resolver()->run($blocks);

        $cards = $resolved[0]['data']['contributors_resolved'];
        $slugs = array_column($cards, 'slug');

        $this->assertContains('ann-live', $slugs);
        $this->assertNotContains('bea-draft', $slugs);
        $this->assertSame(1, $cards[0]['guides_count']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContributorRollupBlockTest`
Expected: FAIL — `contributors_resolved` missing.

- [ ] **Step 3: Register the block**

In `app/Filament/Forms/ContentBuilderBlocks.php`, add `use App\Models\User;` if not present, and add this block inside the array returned by `blocks()` (after `list_content_spot_guides`):

```php
            Builder\Block::make('contributor_roll_up')
                ->label('Contributor roll-up')
                ->schema([
                    TextInput::make('heading')->default('Meet the crew'),
                    TextInput::make('intro')->label('Intro line')->helperText('Optional short line under the heading.'),
                ]),
```

- [ ] **Step 4: Resolve the block**

In `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`, add `use App\Models\User;` to the imports. Inside `resolveContentBlockMedia()`, in the loop that walks `$blocks` to attach resolved data (the same loop that sets `*_resolved` for list blocks), add:

```php
            if (($block['type'] ?? '') === 'contributor_roll_up') {
                $data['contributors_resolved'] = User::withPublicProfile()
                    ->with('profileImageMedia')
                    ->withCount(['authoredSpotGuides as guides_count' => fn ($query) => $query->where('is_published', true)])
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get()
                    ->map(fn (User $contributor) => [
                        'name' => $contributor->name,
                        'slug' => $contributor->slug,
                        'profile_image' => $contributor->profileImageMedia?->imagePayload(),
                        'guides_count' => $contributor->guides_count,
                    ])
                    ->all();
            }
```

Ensure the modified `$data` is written back to the block (the existing loop already reassigns `$block['data'] = $data;` / `$blocks[$index]['data'] = $data;` — follow the existing pattern in the file exactly).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ContributorRollupBlockTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Forms/ContentBuilderBlocks.php app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php tests/Feature/ContributorRollupBlockTest.php
git commit -m "feat: contributor roll-up content block (auto-lists public profiles)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Admin — self-service "My Profile" page + owner editing

**Files:**
- Create: `app/Filament/Pages/MyProfile.php`
- Create: `resources/views/filament/pages/my-profile.blade.php`
- Modify: `app/Filament/Resources/ContributorResource.php` (owner edits profile fields)
- Test: `tests/Feature/MyProfileTest.php`

**Interfaces:**
- Consumes: `User` profile fields (Task 1); `MediaPicker`, `ContentBuilderBlocks` (existing Filament components).
- Produces: a Filament page `/admin/my-profile` editing the authenticated user's own profile; the profile fields also editable on `ContributorResource`'s form. No new public interface.

- [ ] **Step 1: Write the failing test**

The self-service page's security property is that it only ever targets `auth()->id()` (no cross-user write path), so the meaningful assertions are: (a) the page resolves its record to the current user, and (b) saving profile fields persists via the model. Livewire form internals aren't asserted (brittle).

```php
<?php

// Tests the self-service profile editing: the MyProfile page binds to the
// authenticated user, and contributor profile fields persist through a save.

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_profile_page_binds_to_the_authenticated_user(): void
    {
        $contributor = User::factory()->contributor()->create();
        $this->actingAs($contributor);

        $page = new MyProfile();
        $this->assertSame($contributor->id, $page->resolveRecord()->id);
    }

    public function test_profile_fields_persist(): void
    {
        $contributor = User::factory()->contributor()->create();

        $contributor->update([
            'socials' => ['instagram' => 'https://instagram.com/x'],
            'profile_blocks' => [['type' => 'rich_text', 'data' => []]],
        ]);

        $this->assertDatabaseHas('users', ['id' => $contributor->id]);
        $this->assertSame('https://instagram.com/x', $contributor->fresh()->socials['instagram']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MyProfileTest`
Expected: FAIL — `App\Filament\Pages\MyProfile` not found.

- [ ] **Step 3: Create a shared profile form schema**

To avoid duplicating the field set between the page and the resource, add a static schema helper. Create `app/Filament/Forms/ContributorProfileForm.php`:

```php
<?php

// Shared Filament form schema for a contributor's public profile — used by the
// self-service MyProfile page and (owner-facing) ContributorResource, so the
// field set is defined once.

namespace App\Filament\Forms;

use App\Filament\Forms\Components\MediaPicker;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;

class ContributorProfileForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Section::make('Images')
                ->description('A portrait (used on your profile and the crew page) and an optional masthead hero.')
                ->schema([
                    MediaPicker::make('profile_image_media_id')->label('Profile image'),
                    MediaPicker::make('static_masthead_media_id')->label('Masthead image'),
                ]),
            Section::make('Socials')
                ->description('Only the ones you fill in are shown.')
                ->schema([
                    TextInput::make('socials.instagram')->label('Instagram')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('socials.youtube')->label('YouTube')->url(),
                    TextInput::make('socials.tiktok')->label('TikTok')->url(),
                    TextInput::make('socials.facebook')->label('Facebook')->url(),
                    TextInput::make('socials.x')->label('X (Twitter)')->url(),
                    TextInput::make('socials.website')->label('Personal website')->url(),
                ])->columns(2),
            Section::make('Your story')
                ->schema([
                    Builder::make('profile_blocks')
                        ->label('Profile content')
                        ->blocks(ContentBuilderBlocks::blocks())
                        ->collapsible()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
```

- [ ] **Step 4: Create the MyProfile page**

`app/Filament/Pages/MyProfile.php`:

```php
<?php

// Self-service profile editor. A contributor edits ONLY their own public profile
// here (the record is always auth()->user(), so there is no cross-user write
// path). A notice explains the profile goes live only after a published guide —
// public presence earned by contributing.

namespace App\Filament\Pages;

use App\Filament\Forms\ContributorProfileForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'My Profile';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.my-profile';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Only invited contributors have a public profile to edit. */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isContributor() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->resolveRecord()->attributesToArray());
    }

    /** The record edited here is always the current user. */
    public function resolveRecord(): User
    {
        return auth()->user();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(ContributorProfileForm::schema())
            ->statePath('data')
            ->model($this->resolveRecord());
    }

    public function save(): void
    {
        $this->resolveRecord()->update($this->form->getState());

        Notification::make()->title('Profile saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save profile')->submit('save'),
        ];
    }
}
```

- [ ] **Step 5: Create the page view**

`resources/views/filament/pages/my-profile.blade.php`:

```blade
<x-filament-panels::page>
    @if (! auth()->user()->hasPublicProfile())
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Your public profile isn't live yet.</strong>
                It goes live automatically once you have at least one <em>published</em> spot guide —
                so build a guide, get it approved, and your profile (and self-promotion) switches on.
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="text-sm text-green-700 dark:text-green-400">
                Your profile is <strong>live</strong> at
                <a class="underline" href="{{ url('/contributors/'.auth()->user()->slug) }}" target="_blank">/contributors/{{ auth()->user()->slug }}</a>.
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">Save profile</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 6: Add the profile fields to `ContributorResource` (owner editing)**

`ContributorResource::form()` already exists (from sub-project 1). Import the shared schema (`use App\Filament\Forms\ContributorProfileForm;`) and spread its fields into the existing `form()` schema array, after the current identity fields, so the owner can edit any contributor's profile. Read the current `form()` first and integrate cleanly — don't remove existing fields:

```php
            // ...existing identity fields (first_name, last_name, email, …)…
            ...ContributorProfileForm::schema(),
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=MyProfileTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Pages/MyProfile.php app/Filament/Forms/ContributorProfileForm.php resources/views/filament/pages/my-profile.blade.php app/Filament/Resources/ContributorResource.php tests/Feature/MyProfileTest.php
git commit -m "feat: self-service My Profile page + owner profile editing

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Frontend — profile page, roll-up component, social icons, clickable byline

**Files:**
- Create: `resources/js/Pages/Contributors/Show.tsx`
- Create: `resources/js/Components/Common/SocialLinks.tsx`
- Create: `resources/js/Components/Content/ContributorRollUp.tsx`
- Modify: `resources/js/Components/ContentBuilder.tsx` (route the new block)
- Modify: `resources/js/Pages/Destinations/Index.tsx` (clickable byline)
- Modify: `resources/js/Pages/SpotGuide/Show.tsx` (clickable byline)

**Interfaces:**
- Consumes: `Contributors/Show` props (Task 2); `contributors_resolved` block data (Task 5); `author.slug` (Task 3).
- Produces: no downstream consumers (final task).

- [ ] **Step 1: Create `SocialLinks.tsx`**

A small component mapping the socials map to FontAwesome brand icons; renders nothing for empty maps.

```tsx
/**
 * SocialLinks — a horizontal row of tappable brand icons for a contributor's
 * socials. Only platforms with a URL render; unknown keys are ignored.
 */
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faInstagram, faYoutube, faTiktok, faFacebook, faXTwitter } from '@fortawesome/free-brands-svg-icons'
import { faGlobe } from '@fortawesome/free-solid-svg-icons'
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core'

/** Map of platform key → its brand icon (website uses a globe). */
const ICONS: Record<string, IconDefinition> = {
    instagram: faInstagram,
    youtube: faYoutube,
    tiktok: faTiktok,
    facebook: faFacebook,
    x: faXTwitter,
    website: faGlobe,
}

interface Props {
    socials: Record<string, string>
    className?: string
}

/**
 * Render the filled socials as a row of teal icon buttons.
 *
 * @param socials platform→URL map (only filled entries are passed from the server)
 */
const SocialLinks = ({ socials, className = '' }: Props) => {
    const entries = Object.entries(socials).filter(([key, url]) => ICONS[key] && url)
    if (entries.length === 0) return null

    return (
        <div className={`flex items-center gap-3 ${className}`}>
            {entries.map(([key, url]) => (
                <a
                    key={key}
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={key}
                    className="w-10 h-10 flex items-center justify-center rounded-full bg-primary text-white hover:bg-primary-darker transition-colors duration-200"
                >
                    <FontAwesomeIcon icon={ICONS[key]} className="w-4 h-4" />
                </a>
            ))}
        </div>
    )
}

export default SocialLinks
```

- [ ] **Step 2: Create `Contributors/Show.tsx`**

Full page: masthead (their image or gradient fallback), portrait overlapping the masthead's bottom edge, name + socials, content-builder body, guides grid. Mirror `Blog/Tag.tsx` structure and the guide-card look.

```tsx
/**
 * Contributors/Show — a public contributor profile: masthead, portrait, socials,
 * their content-builder story, and a grid of their published guides.
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import CoverImage from '@/Components/Common/CoverImage'
import SocialLinks from '@/Components/Common/SocialLinks'
import { Link } from '@inertiajs/react'
import type { FocalImage } from '@/types/media'

interface Guide {
    id: number
    title: string
    slug: string
    thumbnail: FocalImage | null
    country: string | null
}

interface Props {
    contributor: {
        name: string
        profile_image: FocalImage | null
        socials: Record<string, string>
        profile_blocks: any[]
    }
    static_masthead: FocalImage | null
    guides: Guide[]
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const Show = ({ contributor, static_masthead, guides, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <StaticMasthead imageUrl={static_masthead} eyebrow="Contributor" title={contributor.name} />

            {/* Intro band — portrait overlaps the masthead's bottom edge */}
            <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
                <div className="flex flex-col items-center text-center -mt-28 md:-mt-32">
                    {contributor.profile_image && (
                        <div className="w-40 h-40 md:w-48 md:h-48 rounded-full overflow-hidden ring-4 ring-cream shadow-xl">
                            <CoverImage image={contributor.profile_image} alt={contributor.name} className="w-full h-full" />
                        </div>
                    )}
                    <h2 className="font-title text-secondary uppercase mt-5" style={{ fontSize: 'clamp(1.75rem, 4vw, 2.75rem)' }}>
                        {contributor.name}
                    </h2>
                    <SocialLinks socials={contributor.socials} className="mt-4 justify-center" />
                </div>

                {/* Their story */}
                {contributor.profile_blocks?.length > 0 && (
                    <div className="mt-12">
                        <ContentBuilder blocks={contributor.profile_blocks} />
                    </div>
                )}
            </BlockWrapper>

            {/* Their guides */}
            {guides.length > 0 && (
                <BlockWrapper options={{ bgColourClass: 'bg-white' }}>
                    <h3 className="font-title text-secondary uppercase text-center mb-10" style={{ fontSize: 'clamp(1.5rem, 3vw, 2.25rem)' }}>
                        Guides by {contributor.name}
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {guides.map((guide) => (
                            <Link
                                key={guide.id}
                                href={`/destinations/${guide.slug}`}
                                className="group relative flex flex-col justify-end aspect-[16/10] rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300"
                            >
                                {guide.thumbnail ? (
                                    <CoverImage image={guide.thumbnail} alt={guide.title} className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-500" />
                                ) : (
                                    <div className="absolute inset-0 bg-primary-lighter" />
                                )}
                                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
                                <div className="relative z-10 p-5">
                                    {guide.country && <span className="text-[10px] uppercase tracking-[0.35em] text-primary-lighter">{guide.country}</span>}
                                    <h4 className="font-title text-white uppercase leading-[1.05]" style={{ fontSize: 'clamp(1.1rem, 2vw, 1.4rem)' }}>{guide.title}</h4>
                                </div>
                            </Link>
                        ))}
                    </div>
                </BlockWrapper>
            )}
        </Layout>
    )
}

export default Show
```

- [ ] **Step 3: Create `ContributorRollUp.tsx`**

```tsx
/**
 * ContributorRollUp — content block rendering the crew: a card per contributor
 * with a public profile (portrait + name + guide count), linking to their page.
 */
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Contributor {
    name: string
    slug: string
    profile_image: FocalImage | null
    guides_count: number
}

interface Props {
    heading?: string
    intro?: string
    contributors: Contributor[]
}

/** Pluralise the guide count for a crew card. */
const guideLabel = (count: number): string => `${count} ${count === 1 ? 'guide' : 'guides'}`

const ContributorRollUp = ({ heading, intro, contributors }: Props) => {
    if (!contributors || contributors.length === 0) return null

    return (
        <div className="py-4">
            {heading && (
                <h2 className="font-title text-secondary uppercase text-center" style={{ fontSize: 'clamp(1.75rem, 4vw, 2.75rem)' }}>
                    {heading}
                </h2>
            )}
            {intro && <p className="text-center text-gray-500 mt-3 max-w-2xl mx-auto">{intro}</p>}

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-10">
                {contributors.map((contributor) => (
                    <Link key={contributor.slug} href={`/contributors/${contributor.slug}`} className="group flex flex-col items-center text-center">
                        <div className="w-32 h-32 rounded-full overflow-hidden ring-4 ring-white shadow-lg group-hover:shadow-xl transition-shadow duration-300">
                            {contributor.profile_image ? (
                                <CoverImage image={contributor.profile_image} alt={contributor.name} className="w-full h-full group-hover:scale-105 transition-transform duration-500" />
                            ) : (
                                <div className="w-full h-full bg-primary-lighter" />
                            )}
                        </div>
                        <h3 className="font-title text-secondary uppercase mt-4 group-hover:text-primary transition-colors duration-200" style={{ fontSize: 'clamp(1.1rem, 2vw, 1.35rem)' }}>
                            {contributor.name}
                        </h3>
                        <span className="text-[11px] uppercase tracking-[0.3em] text-primary/60 mt-1">{guideLabel(contributor.guides_count)}</span>
                    </Link>
                ))}
            </div>
        </div>
    )
}

export default ContributorRollUp
```

- [ ] **Step 4: Route the block in `ContentBuilder.tsx`**

Add the import (`import ContributorRollUp from '@/Components/Content/ContributorRollUp'`) and a case in the `switch (block.type)` (mirror the `list_content_*` cases, which read a resolved key):

```tsx
                    case 'contributor_roll_up':
                        return (
                            <ContributorRollUp
                                key={index}
                                heading={block.data.heading}
                                intro={block.data.intro}
                                contributors={block.data.contributors_resolved ?? []}
                            />
                        )
```

- [ ] **Step 5: Make the byline clickable — Destinations + SpotGuide**

In `resources/js/Pages/Destinations/Index.tsx`: the `Author` type gains `slug: string | null`. Replace the plain `bylineFor(guide.author)` text render with a link when `author.kind === 'contributor' && author.slug`:

```tsx
{guide.author.kind === 'contributor' && guide.author.slug ? (
    <Link href={`/contributors/${guide.author.slug}`} className="hover:text-primary transition-colors duration-200">
        By {guide.author.name}
    </Link>
) : (
    'Seabound Souls'
)}
```

(Keep the existing `showProvenance` gate around it. Ensure `Link` from `@inertiajs/react` is imported — it already is.)

In `resources/js/Pages/SpotGuide/Show.tsx`: the author type gains `slug: string | null`; where the byline renders (gated by `showProvenance`), wrap the contributor name in the same `<Link href={`/contributors/${author.slug}`}>` when `kind === 'contributor' && slug`. Read the current byline markup and adapt in place; import `Link` if not already.

- [ ] **Step 6: Build to verify no TypeScript/Vite errors**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: build succeeds, no type errors.

- [ ] **Step 7: Verify in the browser / via HTTP**

Ensure the dev site serves the new build (Herd serves built assets; or run `npm run dev`). Seed a contributor with a published guide + a profile image + socials, add the roll-up block to the About page, then check: `/contributors/{slug}` (masthead/portrait/socials/story/guides), the About page roll-up, and a clickable byline on `/destinations` and a spot-guide page. If the browser tooling is unavailable, verify server-side via `curl` + decoding the Inertia `data-page` blob (component + props), per `project-preview-verification-limits` memory. Capture a screenshot if possible; otherwise flag the visual eyeball for the owner.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: all pass (228 baseline + the new tests).

- [ ] **Step 9: Commit**

```bash
git add resources/js/Pages/Contributors/Show.tsx resources/js/Components/Common/SocialLinks.tsx resources/js/Components/Content/ContributorRollUp.tsx resources/js/Components/ContentBuilder.tsx resources/js/Pages/Destinations/Index.tsx resources/js/Pages/SpotGuide/Show.tsx
git commit -m "feat: contributor profile page, roll-up, social icons, clickable byline

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Post-implementation

- Smoke-test on the real Postgres dev DB: run the migrations, create a contributor + published guide, confirm `/contributors/{slug}` renders and `/about-us` 301s to `/about`.
- Fold `reconcile-everything` into this branch before the PR merges (project rule), then open the PR.
- Owner follow-up (post-deploy): drop the "Contributor roll-up" block onto the About page on Cloud; the `about-us`→`about` rename runs via migration.

## Self-review notes (author)

- **Spec coverage:** data model (T1), derived visibility (T1 `hasPublicProfile`), profile page + 404 rules (T2), clickable byline (T3 payload, T7 links), About rename + 301 (T4), roll-up block auto-listing (T5 + T7), self-service editing + owner editing + incentive notice (T6), socials filled-only + icons (T2 filter, T7 `SocialLinks`), slug from first+last collision-safe (T1). All spec sections mapped.
- **Deviation flagged for the handoff:** the spec's "short intro / short blurb" has no column — realized as the content-builder body + guide count (no new field), keeping to the approved 5 new columns. Confirm this is acceptable or add a `tagline` column.
- **Out-of-scope honoured:** no owner profiles, no manual visibility toggle, no per-contributor SEO overrides, no contributor blog authorship, no roll-up hand-picking.
- **Type consistency:** `hasPublicProfile()`, `scopeWithPublicProfile()`, `publishedAuthoredGuides()`, `authorPayload().slug`, `contributors_resolved` card shape (`name`/`slug`/`profile_image`/`guides_count`), and the `Contributors/Show` prop shapes are used identically across tasks.
