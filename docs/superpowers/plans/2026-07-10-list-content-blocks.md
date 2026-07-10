# List-Content Blocks (+ homepage content-managed) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the `list_content_blogs` / `list_content_spot_guides` builder blocks work (fixing the Filament crash), render them via `FeaturedGrid`, turn the homepage's hardcoded grids into content-managed blocks, and (bundled) rank destinations by gusts.

**Architecture:** Fix the Filament pickers to store IDs (not a bogus relationship); resolve those IDs → published card entries inside the shared `ResolvesContentBlockMedia` trait (so all content controllers get it free); add two `ContentBuilder.tsx` cases rendering `FeaturedGrid`; strip the hardcoded homepage grids; flip the destinations ranking field to `kts_gust`.

**Tech Stack:** Laravel 12 (PHP 8.2), Filament 3.3, Inertia v2 + React 19/TS, PHPUnit 11 (in-memory SQLite), Vite 7 (Node 22).

## Global Constraints

- **Picker lists all records; public renders published-only** in **authored order** (drafts / deleted / later-unpublished IDs silently dropped).
- Resolved keys follow the trait's existing convention: `customBlogEntries_resolved`, `customSpotGuideEntries_resolved` (parallel to `_image` / `_images`). **No controller changes** for resolution — all four content controllers already call `resolveContentBlockMedia`.
- Editable block copy: `blockTitle` (heading), `viewAllUrl` (link target, blank → `/blog` or `/destinations`), `viewAllLabel` (link text, blank → `View all`).
- **Homepage becomes content-managed:** remove the hardcoded `FeaturedGrid` sections; the homepage renders featured/blog grids only if the owner adds the list blocks to the home Page's content builder.
- **Gusts:** the destinations current-month ranking uses `kts_gust` (not `kts_wind`); the note says "gusts".
- **Node 22** for any npm/vite command: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null &&`.
- Test classes do NOT declare `RefreshDatabase` (base `Tests\TestCase` handles it). `php artisan test` stays green.

---

### Task 1: Fix the Filament pickers + add view-all fields

**Files:**
- Modify: `app/Filament/Forms/ContentBuilderBlocks.php`
- Test: `tests/Feature/Filament/ListContentBlockTest.php`

**Interfaces:**
- Produces: blocks `list_content_blogs` (`data.customBlogEntries: int[]`, `blockTitle`, `viewAllUrl`, `viewAllLabel`, `backgroundColour`) and `list_content_spot_guides` (`data.customSpotGuideEntries: int[]`, same extras).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/ListContentBlockTest.php`:
```php
<?php

// The list-content blocks must open in the admin without the empty-relationship
// crash (RelationshipJoiner … null given). We mount an EditPage whose
// content_blocks already contain each block type — on the old code the Select's
// ->relationship('', 'title') threw during render.

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Blog;
use App\Models\Page;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ListContentBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsOwner();
    }

    public function test_spot_guide_list_block_opens_without_the_relationship_crash(): void
    {
        Queue::fake(); // swallow the spot-guide create-hook weather job
        $guide = SpotGuide::factory()->create();
        $page = Page::factory()->create([
            'template' => 'standard',
            'content_blocks' => [[
                'type' => 'list_content_spot_guides',
                'data' => ['blockTitle' => 'Top spots', 'customSpotGuideEntries' => [$guide->id]],
            ]],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('content_blocks');
    }

    public function test_blog_list_block_opens_without_the_relationship_crash(): void
    {
        $blog = Blog::factory()->create();
        $page = Page::factory()->create([
            'template' => 'standard',
            'content_blocks' => [[
                'type' => 'list_content_blogs',
                'data' => ['blockTitle' => 'Latest', 'customBlogEntries' => [$blog->id]],
            ]],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('content_blocks');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ListContentBlockTest`
Expected: FAIL/ERROR — mounting the form with a list block present triggers the block's `Select::make(...)->relationship('', 'title')`, throwing `RelationshipJoiner::prepareQueryForNoConstraints(): Argument #1 ($relationship) must be of type …Relation, null given`.

- [ ] **Step 3: Fix the two block definitions**

In `app/Filament/Forms/ContentBuilderBlocks.php`, add the model imports at the top (after the existing `use` lines):
```php
use App\Models\Blog;
use App\Models\SpotGuide;
```
Replace the `list_content_blogs` block with:
```php
            Builder\Block::make('list_content_blogs')
                ->label('List: Blog Posts')
                ->schema([
                    TextInput::make('blockTitle')
                        ->label('Block Title'),
                    static::backgroundColourSelect(),
                    Select::make('customBlogEntries')
                        ->label('Blog Posts')
                        ->multiple()
                        // Lists ALL posts (drafts included) so one can be lined up
                        // before it's live; the public page renders published only.
                        ->options(fn () => Blog::orderBy('title')->pluck('title', 'id'))
                        ->searchable()
                        ->preload(),
                    TextInput::make('viewAllUrl')
                        ->label('"View all" link URL')
                        ->helperText('Optional — defaults to /blog.'),
                    TextInput::make('viewAllLabel')
                        ->label('"View all" link text')
                        ->default('View all'),
                ]),
```
Replace the `list_content_spot_guides` block with:
```php
            Builder\Block::make('list_content_spot_guides')
                ->label('List: Spot Guides')
                ->schema([
                    TextInput::make('blockTitle')
                        ->label('Block Title'),
                    static::backgroundColourSelect(),
                    Select::make('customSpotGuideEntries')
                        ->label('Spot Guides')
                        ->multiple()
                        ->options(fn () => SpotGuide::orderBy('title')->pluck('title', 'id'))
                        ->searchable()
                        ->preload(),
                    TextInput::make('viewAllUrl')
                        ->label('"View all" link URL')
                        ->helperText('Optional — defaults to /destinations.'),
                    TextInput::make('viewAllLabel')
                        ->label('"View all" link text')
                        ->default('View all'),
                ]),
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=ListContentBlockTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Forms/ContentBuilderBlocks.php tests/Feature/Filament/ListContentBlockTest.php
git commit -m "fix: list-content block pickers store IDs (options, not empty relationship) + view-all fields"
```

---

### Task 2: Server-side resolution of picked entries

**Files:**
- Modify: `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`
- Test: `tests/Feature/PageControllerTest.php` (add cases)

**Interfaces:**
- Consumes: block `data.customBlogEntries` / `data.customSpotGuideEntries` (int[]) from Task 1.
- Produces: `data.customBlogEntries_resolved = [{id,title,slug,thumbnail}]` and `data.customSpotGuideEntries_resolved = [{id,title,slug,thumbnail,subtitle}]` — published-only, authored order. Consumed by Task 3.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/PageControllerTest.php` (add imports `App\Models\SpotGuide`, `App\Models\Blog`, `Illuminate\Support\Facades\Queue` at the top):
```php
    public function test_show_resolves_spot_guide_list_block_to_published_entries_in_order(): void
    {
        Queue::fake();
        $first = SpotGuide::factory()->create(['title' => 'First Spot', 'slug' => 'first-spot']);
        $second = SpotGuide::factory()->create(['title' => 'Second Spot', 'slug' => 'second-spot']);
        $draft = SpotGuide::factory()->unpublished()->create(['title' => 'Draft Spot', 'slug' => 'draft-spot']);

        $page = Page::factory()->create([
            'slug' => 'curated',
            'content_blocks' => [[
                'type' => 'list_content_spot_guides',
                // Authored order second → first → draft; draft drops, order kept.
                'data' => ['customSpotGuideEntries' => [$second->id, $first->id, $draft->id]],
            ]],
        ]);

        $this->get(route('pages.show', 'curated'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('page.content_blocks.0.data.customSpotGuideEntries_resolved', 2)
                ->where('page.content_blocks.0.data.customSpotGuideEntries_resolved.0.slug', 'second-spot')
                ->where('page.content_blocks.0.data.customSpotGuideEntries_resolved.1.slug', 'first-spot')
            );
    }

    public function test_show_resolves_blog_list_block_to_published_entries_only(): void
    {
        $published = Blog::factory()->create(['title' => 'Live Post', 'slug' => 'live-post']);
        Blog::factory()->unpublished()->create(['title' => 'Draft Post', 'slug' => 'draft-post']);

        // Reference the draft too (by a high id that won't exist / or its id) — it must drop.
        $draftId = Blog::where('slug', 'draft-post')->value('id');

        $page = Page::factory()->create([
            'slug' => 'reads',
            'content_blocks' => [[
                'type' => 'list_content_blogs',
                'data' => ['customBlogEntries' => [$published->id, $draftId]],
            ]],
        ]);

        $this->get(route('pages.show', 'reads'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('page.content_blocks.0.data.customBlogEntries_resolved', 1)
                ->where('page.content_blocks.0.data.customBlogEntries_resolved.0.slug', 'live-post')
            );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PageControllerTest`
Expected: FAIL — `customSpotGuideEntries_resolved` / `customBlogEntries_resolved` don't exist yet (the `->has(...)` assertions fail).

- [ ] **Step 3: Extend the trait**

In `app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php`, add imports at the top (after `use App\Models\MediaLibrary;`):
```php
use App\Models\Blog;
use App\Models\SpotGuide;
```
Then, **inside `resolveContentBlockMedia`**, after the `$mediaMap = …` assignment and before the `return array_map(...)`, collect + batch-load the picked entries:
```php
        // Collect list-block entry IDs so we can resolve them published-only in
        // one batched query per type (drafts / deleted IDs drop out; authored
        // order is preserved when mapping back below).
        $blogIds = [];
        $spotGuideIds = [];
        foreach ($blocks as $block) {
            $data = $block['data'] ?? [];
            if (($block['type'] ?? '') === 'list_content_blogs' && is_array($data['customBlogEntries'] ?? null)) {
                $blogIds = array_merge($blogIds, array_map('intval', $data['customBlogEntries']));
            }
            if (($block['type'] ?? '') === 'list_content_spot_guides' && is_array($data['customSpotGuideEntries'] ?? null)) {
                $spotGuideIds = array_merge($spotGuideIds, array_map('intval', $data['customSpotGuideEntries']));
            }
        }

        $blogMap = !empty($blogIds)
            ? Blog::published()->whereIn('id', array_unique($blogIds))->with('thumbnailMedia')->get()->keyBy('id')
            : collect();
        $spotGuideMap = !empty($spotGuideIds)
            ? SpotGuide::published()->whereIn('id', array_unique($spotGuideIds))->with(['country', 'thumbnailMedia'])->get()->keyBy('id')
            : collect();
```
Then change the `return array_map(function (array $block) use (...) {` signature to also capture the two maps:
```php
        return array_map(function (array $block) use ($mediaIdKeys, $mediaArrayKeys, $mediaMap, $blogMap, $spotGuideMap) {
```
and, inside that closure, **after** the existing media-array resolution loop and **before** `$block['data'] = $data;`, add:
```php
            // List-content blocks: resolve picked IDs to published card entries in
            // authored order. FeaturedGrid renders nothing for an empty array.
            if (($block['type'] ?? '') === 'list_content_blogs' && is_array($data['customBlogEntries'] ?? null)) {
                $data['customBlogEntries_resolved'] = collect($data['customBlogEntries'])
                    ->map(fn ($id) => $blogMap->get((int) $id))
                    ->filter()
                    ->map(fn ($blog) => [
                        'id' => $blog->id,
                        'title' => $blog->title,
                        'slug' => $blog->slug,
                        'thumbnail' => $blog->thumbnailMedia?->imagePayload(),
                    ])
                    ->values()
                    ->toArray();
            }
            if (($block['type'] ?? '') === 'list_content_spot_guides' && is_array($data['customSpotGuideEntries'] ?? null)) {
                $data['customSpotGuideEntries_resolved'] = collect($data['customSpotGuideEntries'])
                    ->map(fn ($id) => $spotGuideMap->get((int) $id))
                    ->filter()
                    ->map(fn ($guide) => [
                        'id' => $guide->id,
                        'title' => $guide->title,
                        'slug' => $guide->slug,
                        'thumbnail' => $guide->thumbnailMedia?->imagePayload(),
                        'subtitle' => $guide->country?->name,
                    ])
                    ->values()
                    ->toArray();
            }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=PageControllerTest`
Expected: PASS (existing + 2 new).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Concerns/ResolvesContentBlockMedia.php tests/Feature/PageControllerTest.php
git commit -m "feat: resolve list-content block entries to published cards (authored order)"
```

---

### Task 3: Render the blocks on the front end

**Files:**
- Modify: `resources/js/Components/ContentBuilder.tsx`

**Interfaces:**
- Consumes: `customBlogEntries_resolved` / `customSpotGuideEntries_resolved` (Task 2) + `blockTitle` / `viewAllUrl` / `viewAllLabel` / `backgroundColour` (Task 1). Renders `FeaturedGrid`.

- [ ] **Step 1: Add the imports**

In `resources/js/Components/ContentBuilder.tsx`, add to the imports:
```tsx
import FeaturedGrid from './Common/FeaturedGrid'
```

- [ ] **Step 2: Add the two cases**

In the `switch (block.type)`, before `default:`, add:
```tsx
                    case 'list_content_blogs':
                        return (
                            <FeaturedGrid
                                key={index}
                                title={block.data.blockTitle || 'From the blog'}
                                entries={block.data.customBlogEntries_resolved ?? []}
                                linkHref={block.data.viewAllUrl || '/blog'}
                                linkLabel={block.data.viewAllLabel || 'View all'}
                                backgroundColour={block.data.backgroundColour}
                                buildHref={(entry) => `/blog/${entry.slug}`}
                            />
                        )
                    case 'list_content_spot_guides':
                        return (
                            <FeaturedGrid
                                key={index}
                                title={block.data.blockTitle || 'Destinations'}
                                entries={block.data.customSpotGuideEntries_resolved ?? []}
                                linkHref={block.data.viewAllUrl || '/destinations'}
                                linkLabel={block.data.viewAllLabel || 'View all'}
                                backgroundColour={block.data.backgroundColour}
                                buildHref={(entry) => `/destinations/${entry.slug}`}
                            />
                        )
```

- [ ] **Step 3: Build to verify no type/JSX errors**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Expected: build succeeds. (Live preview is the controller's post-task pass — add a `list_content_spot_guides` block to a page in `/admin`, pick a published guide, and confirm the grid renders with the heading + "View all" link.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/ContentBuilder.tsx
git commit -m "feat: render list-content blocks via FeaturedGrid"
```

---

### Task 4: Homepage becomes content-managed

**Files:**
- Modify: `app/Http/Controllers/HomepageController.php`
- Modify: `resources/js/Pages/Homepage.tsx`
- Test: `tests/Feature/HomepageControllerTest.php`

**Interfaces:**
- Removes the `featuredSpotGuides` and `recentBlogs` props. The homepage's featured/blog grids now come from list blocks on the home Page's content builder (Tasks 1–3).

- [ ] **Step 1: Update the homepage tests (remove the obsolete assertions)**

In `tests/Feature/HomepageControllerTest.php`, **delete** the two methods
`test_index_features_at_most_six_published_spot_guides` and
`test_index_lists_at_most_three_recent_published_blogs`. Keep
`test_index_renders_even_without_a_home_page_record` and
`test_index_enriches_infographic_blocks_with_published_stats`. Remove the now-unused
`use App\Models\Blog;` import if nothing else references it (the infographic test
uses `SpotGuide` + `Recommendation`, not `Blog`).

- [ ] **Step 2: Run the homepage test to confirm the kept tests still pass**

Run: `php artisan test --filter=HomepageControllerTest`
Expected: PASS (2 remaining tests). (They don't assert the removed props.)

- [ ] **Step 3: Strip the hardcoded queries from the controller**

In `app/Http/Controllers/HomepageController.php`:
- Delete the `$featuredSpotGuides = SpotGuide::published()->…->get()->map(…);` block and the `$recentBlogs = Blog::published()->…->get()->map(…);` block.
- Remove `'featuredSpotGuides' => $featuredSpotGuides,` and `'recentBlogs' => $recentBlogs,` from the `Inertia::render('Homepage', [...])` props.
- Remove the now-unused `use App\Models\Blog;` import. (Keep `SpotGuide`, `Recommendation`, `MediaLibrary`, `Page` — still used by the infographic stats + slider.)
- Leave the masthead-slider block, the infographic-stat enrichment, and the `content_blocks` resolution untouched.

- [ ] **Step 4: Strip the hardcoded grids from the page**

In `resources/js/Pages/Homepage.tsx`:
- Remove `import FeaturedGrid from '@/Components/Common/FeaturedGrid'`.
- Remove `featuredSpotGuides: any[]` and `recentBlogs: any[]` from the `Props` interface and from the destructured params (`const Homepage = ({ page, meta }: Props) => {`).
- Delete both `<FeaturedGrid>…</FeaturedGrid>` sections. The return keeps the masthead block and `{page?.content_blocks && <ContentBuilder blocks={page.content_blocks} />}`.

- [ ] **Step 5: Build + run the homepage test**

Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Then: `php artisan test --filter=HomepageControllerTest`
Expected: build succeeds; homepage tests pass. (Live preview: `/` renders masthead + any content blocks, with no hardcoded featured/blog grids.)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/HomepageController.php resources/js/Pages/Homepage.tsx tests/Feature/HomepageControllerTest.php
git commit -m "feat: homepage featured/blog grids are content-managed (remove hardcoded sections)"
```

---

### Task 5: Rank destinations by gusts (bundled from #25)

**Files:**
- Modify: `app/Http/Controllers/DestinationController.php`
- Modify: `resources/js/Pages/Destinations/Index.tsx`
- Test: `tests/Feature/DestinationControllerTest.php`

**Interfaces:** none new — swaps the ranking metric.

- [ ] **Step 1: Update the ordering tests to gusts (RED)**

In `tests/Feature/DestinationControllerTest.php`, in the three ranking tests
(`test_index_orders_guides_windiest_first_for_the_current_month`,
`test_index_sorts_guides_without_current_month_data_last`,
`test_index_ignores_other_years_and_months_when_ranking`), change every
`'kts_wind' => N` in the `WeatherRecord::factory()->…->create([...])` calls to
`'kts_gust' => N` (same numbers). Leave the order assertions unchanged.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=DestinationControllerTest`
Expected: FAIL — the controller still ranks on `kts_wind`, but the records now
carry the discriminating values on `kts_gust` (with `kts_wind` left to the
factory's default), so the asserted order no longer holds.

- [ ] **Step 3: Switch the controller ranking to gusts**

In `app/Http/Controllers/DestinationController.php`, replace the ranking block
(the `$windThisMonth` closure + the `$spotGuides->sort(...)` that uses it) with:
```php
        $currentYear = now()->year;
        $currentMonth = now()->month;

        // Rank "gustiest first" for the CURRENT month using THIS year's reading —
        // gusts are what light up a session. weather_records is unique per
        // year+month, so it's a single value. No current record → sorts last;
        // ties break by title. Read per request so it re-ranks as the month turns.
        $gustThisMonth = fn (SpotGuide $guide) => optional($guide->weatherRecords->first(
            fn ($record) => (int) $record->year === $currentYear && (int) $record->month === $currentMonth
        ))->kts_gust;

        $spotGuides = $spotGuides->sort(function (SpotGuide $first, SpotGuide $second) use ($gustThisMonth) {
            $gustFirst = $gustThisMonth($first);
            $gustSecond = $gustThisMonth($second);

            if ($gustFirst === null && $gustSecond === null) {
                return strcmp($first->title, $second->title);
            }
            if ($gustFirst === null) {
                return 1; // no current-month data → sort last
            }
            if ($gustSecond === null) {
                return -1;
            }

            return ((float) $gustSecond <=> (float) $gustFirst) ?: strcmp($first->title, $second->title);
        })->values();
```

- [ ] **Step 4: Update the note wording**

In `resources/js/Pages/Destinations/Index.tsx`, change the ordering note's first
words from `Ordered by wind for {orderingPeriod}` to `Ordered by gusts for {orderingPeriod}`.

- [ ] **Step 5: Run the tests + build**

Run: `php artisan test --filter=DestinationControllerTest`
Expected: PASS.
Run: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build`
Expected: build succeeds.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/DestinationController.php resources/js/Pages/Destinations/Index.tsx tests/Feature/DestinationControllerTest.php
git commit -m "feat: rank destinations by gusts (not wind) for the current month"
```

---

## Final verification (before PR)

- [ ] `php artisan test` — full suite green (Filament block, resolution, homepage, gusts).
- [ ] `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build` — production build succeeds.
- [ ] Live preview: in `/admin`, add a `list_content_spot_guides` block to a page (e.g. the home Page or About), pick 2–3 published guides + set heading / view-all text + URL, save; view the page and confirm the `FeaturedGrid` renders with the picked guides in order, correct heading, and the "View all" link. Repeat for a blog block. Confirm the homepage no longer shows the old hardcoded grids. Confirm `/destinations` note reads "gusts". Verify at mobile + desktop.
- [ ] Screenshot for the PR.
