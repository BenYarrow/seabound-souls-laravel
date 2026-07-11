# Related Spot Guides Slider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "More Spots in {Country/Continent}" slider to the bottom of the spot-guide page, showing other guides in the same country (falling back to the same continent, then hidden).

**Architecture:** A shared `SpotGuide::sortByGustiestThisMonth()` model method (extracted from `DestinationController`) provides the wind ranking. `SpotGuideController::show` builds a country→continent→none cascade, orders it featured-first then gustiest, and passes a `related_spot_guides` prop. A new `RelatedSpotGuides.tsx` Swiper component renders the cards on the page.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Swiper (already a dependency), Tailwind v3. Tests: PHPUnit on in-memory SQLite.

## Global Constraints

- Branch is `related-spot-guides-slider` (already cut from `main`). No commits to `main`.
- Node 22 for any JS build: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"`.
- `php artisan test` must pass before any task is considered done.
- JSDoc on every TS/TSX function; PHPDoc on non-obvious PHP methods. Module header comment at the top of each new source file.
- No raw `bg-white`/`text-gray-*` that won't theme — but this page is not yet on the token layer, so match the existing sections' styling (`bg-cream`/`bg-white`, `text-secondary`) verbatim. No new dark-mode work.
- `@/…` import case must match tracked case (`Helpers/`, `Components/`).
- Mock external services; tests seed `weather_records` directly.

---

### Task 1: Extract the shared "gustiest this month" sorter

**Files:**
- Modify: `app/Models/SpotGuide.php` (add `sortByGustiestThisMonth` static method + `Collection` import)
- Modify: `app/Http/Controllers/DestinationController.php:42-66` (replace inline comparator with the call)
- Test: `tests/Unit/SpotGuideTest.php` (add sorter tests)

**Interfaces:**
- Produces: `SpotGuide::sortByGustiestThisMonth(\Illuminate\Support\Collection $guides): \Illuminate\Support\Collection` — sorts a collection of `SpotGuide` models (each with `weatherRecords` loaded) gustiest-first for the current year+month; no-current-data sorts last; ties break by `title` via `strcmp`. Returns a re-indexed collection.
- Consumes: nothing from other tasks.

- [ ] **Step 1: Write the failing unit tests**

Add to `tests/Unit/SpotGuideTest.php` (add `use App\Models\WeatherRecord;` to the imports at the top):

```php
public function test_sort_by_gustiest_this_month_orders_by_current_gust_descending(): void
{
    $calm = SpotGuide::factory()->create(['title' => 'Calm Bay']);
    $windy = SpotGuide::factory()->create(['title' => 'Windy Point']);
    WeatherRecord::factory()->for($calm)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 8]);
    WeatherRecord::factory()->for($windy)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 20]);

    $sorted = SpotGuide::sortByGustiestThisMonth(
        SpotGuide::with('weatherRecords')->get()
    );

    $this->assertSame(['Windy Point', 'Calm Bay'], $sorted->pluck('title')->all());
}

public function test_sort_by_gustiest_this_month_puts_guides_without_current_data_last(): void
{
    // "Aaa Bay" is alphabetically first but has no current-month reading.
    SpotGuide::factory()->create(['title' => 'Aaa Bay']);
    $withData = SpotGuide::factory()->create(['title' => 'Zephyr Cove']);
    WeatherRecord::factory()->for($withData)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 5]);

    $sorted = SpotGuide::sortByGustiestThisMonth(
        SpotGuide::with('weatherRecords')->get()
    );

    $this->assertSame(['Zephyr Cove', 'Aaa Bay'], $sorted->pluck('title')->all());
}

public function test_sort_by_gustiest_this_month_breaks_ties_alphabetically(): void
{
    $bravo = SpotGuide::factory()->create(['title' => 'Bravo Bay']);
    $alpha = SpotGuide::factory()->create(['title' => 'Alpha Bay']);
    WeatherRecord::factory()->for($bravo)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 12]);
    WeatherRecord::factory()->for($alpha)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 12]);

    $sorted = SpotGuide::sortByGustiestThisMonth(
        SpotGuide::with('weatherRecords')->get()
    );

    $this->assertSame(['Alpha Bay', 'Bravo Bay'], $sorted->pluck('title')->all());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=sort_by_gustiest_this_month`
Expected: FAIL — `Call to undefined method App\Models\SpotGuide::sortByGustiestThisMonth()`

- [ ] **Step 3: Add the method to the model**

In `app/Models/SpotGuide.php`, add the import near the other `use` statements:

```php
use Illuminate\Support\Collection;
```

Add this method to the `SpotGuide` class (e.g. after the relationship methods):

```php
/**
 * Sort a collection of spot guides "gustiest first" for the current month,
 * using this year's reading. Gusts are what light up a session, so they drive
 * the ranking. Guides with no reading for the current year+month sort last;
 * ties break alphabetically by title. Read per request (via now()) so the
 * order re-ranks as the month turns. Callers must eager-load weatherRecords.
 *
 * @param  Collection<int, SpotGuide>  $guides
 * @return Collection<int, SpotGuide>
 */
public static function sortByGustiestThisMonth(Collection $guides): Collection
{
    $currentYear = now()->year;
    $currentMonth = now()->month;

    $gustThisMonth = fn (SpotGuide $guide) => optional($guide->weatherRecords->first(
        fn ($record) => (int) $record->year === $currentYear && (int) $record->month === $currentMonth
    ))->kts_gust;

    return $guides->sort(function (SpotGuide $first, SpotGuide $second) use ($gustThisMonth) {
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
}
```

- [ ] **Step 4: Run the unit tests to verify they pass**

Run: `php artisan test --filter=sort_by_gustiest_this_month`
Expected: PASS (3 tests)

- [ ] **Step 5: Refactor DestinationController to use the shared method**

In `app/Http/Controllers/DestinationController.php`, replace the block from the `$currentYear = now()->year;` line through the `->values();` end of the `$spotGuides = $spotGuides->sort(...)` assignment (lines ~40-66) with:

```php
        // Rank "gustiest first" for the current month (see SpotGuide::sortByGustiestThisMonth).
        $spotGuides = SpotGuide::sortByGustiestThisMonth($spotGuides);
```

Leave the rest of the method (the `$spotGuidesData` map, `$featured`, `$weatherData`) unchanged.

- [ ] **Step 6: Run the destinations regression tests to verify ordering still holds**

Run: `php artisan test tests/Feature/DestinationControllerTest.php`
Expected: PASS — including `test_index_orders_guides_windiest_first_for_the_current_month`, `test_index_sorts_guides_without_current_month_data_last`, `test_index_ignores_other_years_and_months_when_ranking`.

- [ ] **Step 7: Commit**

```bash
git add app/Models/SpotGuide.php app/Http/Controllers/DestinationController.php tests/Unit/SpotGuideTest.php
git commit -m "Extract shared SpotGuide::sortByGustiestThisMonth sorter

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Build the related-guides payload in SpotGuideController

**Files:**
- Modify: `app/Http/Controllers/SpotGuideController.php` (build the cascade + prop)
- Test: `tests/Feature/SpotGuideControllerTest.php` (add related-guides tests)

**Interfaces:**
- Consumes: `SpotGuide::sortByGustiestThisMonth()` from Task 1.
- Produces: Inertia prop `related_spot_guides` with shape `{ relation: 'country'|'continent'|null, label: string|null, guides: Array<{ id, title, slug, country: {name}|null, thumbnail: FocalImage|null, intro_snippet: string, overview: { wind_conditions: string|null, best_direction: string|null } }> }`. Consumed by Task 3.

- [ ] **Step 1: Write the failing feature tests**

Add to `tests/Feature/SpotGuideControllerTest.php` (add `use App\Models\Country;` to the imports):

```php
public function test_show_includes_other_published_guides_from_the_same_country(): void
{
    $country = Country::factory()->create(['name' => 'Greece']);
    $guide = SpotGuide::factory()->for($country)->create();
    $sibling = SpotGuide::factory()->for($country)->create();

    $this->get(route('spot-guides.show', $guide->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related_spot_guides.relation', 'country')
            ->where('related_spot_guides.label', 'Greece')
            ->has('related_spot_guides.guides', 1)
            ->where('related_spot_guides.guides.0.slug', $sibling->slug)
        );
}

public function test_show_excludes_the_current_guide_and_drafts_from_related(): void
{
    $country = Country::factory()->create();
    $guide = SpotGuide::factory()->for($country)->create();
    SpotGuide::factory()->for($country)->unpublished()->create(); // draft sibling

    $this->get(route('spot-guides.show', $guide->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related_spot_guides.relation', null)
            ->has('related_spot_guides.guides', 0)
        );
}

public function test_show_falls_back_to_continent_when_country_has_no_siblings(): void
{
    $europeA = Country::factory()->create(['continent' => 'europe', 'name' => 'Greece']);
    $europeB = Country::factory()->create(['continent' => 'europe', 'name' => 'Spain']);
    $guide = SpotGuide::factory()->for($europeA)->create();
    $continentSibling = SpotGuide::factory()->for($europeB)->create();

    $this->get(route('spot-guides.show', $guide->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related_spot_guides.relation', 'continent')
            ->where('related_spot_guides.label', 'Europe')
            ->has('related_spot_guides.guides', 1)
            ->where('related_spot_guides.guides.0.slug', $continentSibling->slug)
        );
}

public function test_show_returns_no_related_when_neither_country_nor_continent_has_others(): void
{
    $country = Country::factory()->create(['continent' => 'oceania']);
    $guide = SpotGuide::factory()->for($country)->create();

    $this->get(route('spot-guides.show', $guide->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related_spot_guides.relation', null)
            ->where('related_spot_guides.label', null)
            ->has('related_spot_guides.guides', 0)
        );
}

public function test_show_orders_related_featured_first_then_gustiest(): void
{
    $country = Country::factory()->create();
    $guide = SpotGuide::factory()->for($country)->create();
    $featured = SpotGuide::factory()->for($country)->create(['title' => 'Featured Cove', 'is_featured' => true]);
    $windy = SpotGuide::factory()->for($country)->create(['title' => 'Windy Point']);
    $calm = SpotGuide::factory()->for($country)->create(['title' => 'Calm Bay']);
    WeatherRecord::factory()->for($featured)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 5]);
    WeatherRecord::factory()->for($windy)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 30]);
    WeatherRecord::factory()->for($calm)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 10]);

    $this->get(route('spot-guides.show', $guide->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related_spot_guides.guides.0.title', 'Featured Cove') // featured leads despite low gust
            ->where('related_spot_guides.guides.1.title', 'Windy Point')   // then gustiest
            ->where('related_spot_guides.guides.2.title', 'Calm Bay')
        );
}

public function test_show_related_card_carries_snippet_and_overview(): void
{
    $country = Country::factory()->create();
    $guide = SpotGuide::factory()->for($country)->create();
    SpotGuide::factory()->for($country)->create([
        'introduction_text' => '<p>A <strong>windy</strong> paradise.</p>',
        'spot_overview' => ['wind_conditions' => 'Thermal', 'best_direction' => 'NW'],
    ]);

    $this->get(route('spot-guides.show', $guide->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related_spot_guides.guides.0.intro_snippet', 'A windy paradise.')
            ->where('related_spot_guides.guides.0.overview.wind_conditions', 'Thermal')
            ->where('related_spot_guides.guides.0.overview.best_direction', 'NW')
        );
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=related`
Expected: FAIL — the `related_spot_guides` prop does not exist yet.

- [ ] **Step 3: Build the cascade and prop in the controller**

In `app/Http/Controllers/SpotGuideController.php`, add the import near the top:

```php
use Illuminate\Support\Str;
```

After the `$gallery = ...` block and before `return Inertia::render(...)`, insert:

```php
        // Related guides: other published guides in the SAME COUNTRY, falling
        // back to the same CONTINENT, then hidden. Featured leads (single-featured
        // invariant → at most one), remainder ranked gustiest-this-month.
        $relation = null;
        $label = null;
        $related = collect();

        if ($spotGuide->country_id !== null) {
            $countrySiblings = SpotGuide::published()
                ->where('country_id', $spotGuide->country_id)
                ->where('id', '!=', $spotGuide->id)
                ->with(['country', 'thumbnailMedia', 'weatherRecords'])
                ->get();

            if ($countrySiblings->isNotEmpty()) {
                $related = $countrySiblings;
                $relation = 'country';
                $label = $spotGuide->country?->name;
            } elseif ($spotGuide->country?->continent) {
                $continent = $spotGuide->country->continent;
                $continentGuides = SpotGuide::published()
                    ->where('id', '!=', $spotGuide->id)
                    ->whereHas('country', fn ($query) => $query->where('continent', $continent))
                    ->with(['country', 'thumbnailMedia', 'weatherRecords'])
                    ->get();

                if ($continentGuides->isNotEmpty()) {
                    $related = $continentGuides;
                    $relation = 'continent';
                    // Humanise the enum slug for display: north-america → North America.
                    $label = ucwords(str_replace('-', ' ', $continent));
                }
            }
        }

        // Featured first (stable sort preserves gust order among the rest), then
        // gustiest this month.
        $related = SpotGuide::sortByGustiestThisMonth($related)
            ->sortByDesc('is_featured')
            ->values();

        $relatedGuides = $related->map(fn (SpotGuide $guide) => [
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'country' => $guide->country ? ['name' => $guide->country->name] : null,
            'thumbnail' => $guide->thumbnailMedia?->imagePayload(),
            'intro_snippet' => Str::limit(strip_tags($guide->introduction_text ?? ''), 140),
            'overview' => [
                'wind_conditions' => $guide->spot_overview['wind_conditions'] ?? null,
                'best_direction' => $guide->spot_overview['best_direction'] ?? null,
            ],
        ])->values()->toArray();
```

Then add the prop to the `Inertia::render('SpotGuide/Show', [...])` array, as a sibling of `'spotGuide'` and `'meta'`:

```php
            'related_spot_guides' => [
                'relation' => $relation,
                'label' => $label,
                'guides' => $relatedGuides,
            ],
```

- [ ] **Step 4: Run the feature tests to verify they pass**

Run: `php artisan test --filter=related`
Expected: PASS (6 tests). Then run the full file to confirm nothing regressed: `php artisan test tests/Feature/SpotGuideControllerTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SpotGuideController.php tests/Feature/SpotGuideControllerTest.php
git commit -m "Add related-guides (country then continent) payload to spot guide

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: RelatedSpotGuides slider component + page wiring

**Files:**
- Create: `resources/js/Components/SpotGuide/RelatedSpotGuides.tsx`
- Modify: `resources/js/Pages/SpotGuide/Show.tsx` (import, `Props` type, render at the end)

**Interfaces:**
- Consumes: the `related_spot_guides` prop shape from Task 2.
- Produces: default-exported `RelatedSpotGuides` React component with props `{ relation: 'country'|'continent'|null; label: string|null; guides: RelatedGuide[] }`.

- [ ] **Step 1: Create the component**

Create `resources/js/Components/SpotGuide/RelatedSpotGuides.tsx`:

```tsx
// RelatedSpotGuides — closing "explore more" slider for the spot-guide page.
// Shows other guides in the same country (or continent as a fallback) as a
// Swiper carousel of richer cards. Renders nothing when there are no guides.
// Data shape is produced by SpotGuideController::show (related_spot_guides prop).

import { Link } from '@inertiajs/react'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Navigation } from 'swiper/modules'
import { faChevronLeft, faChevronRight, faWind, faCompass } from '@fortawesome/free-solid-svg-icons'

import CoverImage from '@/Components/Common/CoverImage'
import Icon from '@/Components/Common/Icon'
import AnimateInView from '@/Components/Common/AnimateInView'
import type { FocalImage } from '@/types/media'

import 'swiper/css'
import 'swiper/css/navigation'

/** One related guide, as shaped by the controller. */
interface RelatedGuide {
    id: number
    title: string
    slug: string
    country: { name: string } | null
    thumbnail: FocalImage | null
    intro_snippet: string
    overview: {
        wind_conditions: string | null
        best_direction: string | null
    }
}

interface RelatedSpotGuidesProps {
    /** 'country' | 'continent' — which relation produced the set (unused for display; label carries the text). */
    relation: 'country' | 'continent' | null
    /** Human label for the heading, e.g. "Greece" or "Europe". */
    label: string | null
    guides: RelatedGuide[]
}

/**
 * Render the related-guides slider. Returns null when there is nothing to show
 * so the section disappears entirely (matches the controller's empty case).
 */
const RelatedSpotGuides = ({ label, guides }: RelatedSpotGuidesProps) => {
    if (!guides || guides.length === 0) return null

    return (
        <section className="bg-cream">
            <div className="container mx-auto py-14 lg:py-18">
                {/* Heading — matches the page's SectionHeading treatment. */}
                <div className="flex items-start gap-4 mb-8 lg:mb-10">
                    <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
                    <h2
                        className="font-display leading-none tracking-wide text-secondary"
                        style={{ fontSize: 'clamp(2rem, 4vw, 3.5rem)' }}
                    >
                        {label ? `More Spots in ${label}` : 'More Spots'}
                    </h2>
                </div>

                <AnimateInView tag="div">
                    <Swiper
                        modules={[Navigation]}
                        spaceBetween={20}
                        slidesPerView={1.1}
                        breakpoints={{
                            768: { slidesPerView: 2 },
                            1024: { slidesPerView: 3 },
                        }}
                        navigation={{
                            nextEl: '.swiper-related-next',
                            prevEl: '.swiper-related-prev',
                        }}
                    >
                        {guides.map((guide) => (
                            <SwiperSlide key={guide.id} className="!h-auto">
                                <Link
                                    href={`/destinations/${guide.slug}`}
                                    className="group flex flex-col h-full bg-white overflow-hidden shadow-sm hover:shadow-lg transition-shadow duration-500"
                                >
                                    <div className="relative aspect-[4/3] overflow-hidden bg-primary-darker">
                                        {guide.thumbnail && (
                                            <CoverImage
                                                image={guide.thumbnail}
                                                alt={guide.title}
                                                className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-700 ease-out"
                                            />
                                        )}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                                        {guide.country && (
                                            <span className="absolute top-3 left-3 text-white/90 text-[10px] uppercase tracking-[0.15em]">
                                                {guide.country.name}
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex flex-col flex-1 p-5">
                                        <h3
                                            className="font-display text-secondary leading-none tracking-wide"
                                            style={{ fontSize: 'clamp(1.15rem, 2vw, 1.5rem)' }}
                                        >
                                            {guide.title}
                                        </h3>

                                        {guide.intro_snippet && (
                                            <p className="text-secondary/60 text-sm mt-2 line-clamp-3 leading-relaxed">
                                                {guide.intro_snippet}
                                            </p>
                                        )}

                                        {/* spot_overview badges — render only those present. */}
                                        {(guide.overview.wind_conditions || guide.overview.best_direction) && (
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                {guide.overview.wind_conditions && (
                                                    <span className="inline-flex items-center gap-1.5 bg-primary-lightest text-primary text-[11px] uppercase tracking-wide px-2.5 py-1 rounded-full">
                                                        <Icon icon={faWind} size="size-3" />
                                                        {guide.overview.wind_conditions}
                                                    </span>
                                                )}
                                                {guide.overview.best_direction && (
                                                    <span className="inline-flex items-center gap-1.5 bg-primary-lightest text-primary text-[11px] uppercase tracking-wide px-2.5 py-1 rounded-full">
                                                        <Icon icon={faCompass} size="size-3" />
                                                        {guide.overview.best_direction}
                                                    </span>
                                                )}
                                            </div>
                                        )}

                                        <div className="mt-auto pt-4 flex items-center text-primary text-[10px] uppercase tracking-[0.15em]">
                                            View guide
                                            <span className="ml-2 h-px w-6 bg-primary group-hover:w-10 transition-all duration-500 ease-out" />
                                        </div>
                                    </div>
                                </Link>
                            </SwiperSlide>
                        ))}
                    </Swiper>

                    {/* Nav arrows — hidden when everything fits without scrolling is handled by Swiper. */}
                    <div className="mt-8 flex items-center gap-4">
                        <button
                            type="button"
                            aria-label="Previous"
                            className="swiper-related-prev hover:scale-110 transition-transform duration-300 text-secondary"
                        >
                            <Icon icon={faChevronLeft} />
                        </button>
                        <button
                            type="button"
                            aria-label="Next"
                            className="swiper-related-next hover:scale-110 transition-transform duration-300 text-secondary"
                        >
                            <Icon icon={faChevronRight} />
                        </button>
                    </div>
                </AnimateInView>
            </div>
        </section>
    )
}

export default RelatedSpotGuides
```

- [ ] **Step 2: Wire it into the page**

In `resources/js/Pages/SpotGuide/Show.tsx`:

Add the import alongside the other component imports (after the `SpotGuideNav` import):

```tsx
import RelatedSpotGuides from '@/Components/SpotGuide/RelatedSpotGuides'
```

Add to the `Props` interface, as a sibling of `spotGuide` and `meta`:

```tsx
    related_spot_guides: {
        relation: 'country' | 'continent' | null
        label: string | null
        guides: {
            id: number
            title: string
            slug: string
            country: { name: string } | null
            thumbnail: FocalImage | null
            intro_snippet: string
            overview: { wind_conditions: string | null; best_direction: string | null }
        }[]
    }
```

Update the component signature to destructure it:

```tsx
const Show = ({ spotGuide, related_spot_guides, meta }: Props) => {
```

Add the render as the final child inside `<Layout>`, immediately before the closing `</Layout>` tag (after the "Lessons & Hire" block):

```tsx
            {/* ── Related Spot Guides ── */}
            <RelatedSpotGuides
                relation={related_spot_guides.relation}
                label={related_spot_guides.label}
                guides={related_spot_guides.guides}
            />
```

- [ ] **Step 3: Build the assets to confirm the bundle compiles**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: build succeeds with no TypeScript/Vite errors, and a `RelatedSpotGuides` chunk is emitted.

- [ ] **Step 4: Verify in the browser (both a country with siblings and a lone guide)**

Ensure Postgres and the Vite dev server are running (`composer dev` in a Node-22 shell). Using the preview tools:
- Open a spot guide whose country has other published guides. Confirm the "More Spots in {Country}" section renders at the bottom with cards, the slider drags, and arrows page through.
- Confirm cards show thumbnail, title, country tag, snippet, and any overview badges, and that clicking a card navigates to that guide.
- Open a guide whose country has no siblings but whose continent does → heading reads "More Spots in {Continent}".
- Open a guide that is the only one in its continent → the section is absent.
- Check mobile (375px) and tablet (768px) widths: ~1.1 and 2 cards visible respectively; no horizontal page overflow.
- Take a screenshot of the desktop section to share as proof.

- [ ] **Step 5: Run the full PHP test suite**

Run: `php artisan test`
Expected: PASS (all tests, including Tasks 1 & 2).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/SpotGuide/RelatedSpotGuides.tsx resources/js/Pages/SpotGuide/Show.tsx
git commit -m "Add RelatedSpotGuides slider to spot guide page

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- Cascade country→continent→hidden — Task 2 (controller) + tests.
- Ordering featured-first then gustiest — Task 1 (sorter) + Task 2 (featured layering) + `test_show_orders_related_featured_first_then_gustiest`.
- Adaptive heading (Country/Continent label) — Task 2 (`label`) + Task 3 (heading render) + tests.
- Placement after Lessons & Hire, not in quick-nav — Task 3, Step 2.
- Richer card (thumbnail, title, country, snippet, ≤2 overview badges) — Task 3 component + `test_show_related_card_carries_snippet_and_overview`.
- Shared sorter extraction + destinations regression — Task 1.
- Hidden when empty — component returns null + controller empty case test.
- Swiper responsive peek (1.1/2/3) — Task 3 component.

**Placeholder scan:** No TBD/TODO; all steps carry concrete code or exact commands.

**Type consistency:** `related_spot_guides` shape identical across controller (Task 2), `RelatedGuide`/`Props` (Task 3). `sortByGustiestThisMonth(Collection): Collection` used consistently in Tasks 1 and 2. Prop keys (`relation`, `label`, `guides`, `intro_snippet`, `overview.wind_conditions`, `overview.best_direction`) match between tests, controller, and component.

**Note on frontend testing:** the repo has no React component test harness (Vitest covers helpers/charts only), so Task 3 is verified via the browser preview rather than a unit test, consistent with the existing components.
