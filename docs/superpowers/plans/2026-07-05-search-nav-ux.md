# Search & Navigation UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make site search feel live and responsive (dropdown as you type), and replace the jarring desktop search open + awkward mobile menu swoop with smooth, on-brand animations.

**Architecture:** Extract the existing web-search logic into a `SiteSearch` service; add a `GET /api/search` JSON endpoint for live suggestions. On the frontend, extract a `SearchPanel` component (animated slide-down + debounced live dropdown + keyboard nav) and rework the NavBar mobile menu into a staggered slide-down.

**Tech Stack:** Laravel 12, Laravel Scout (database driver in dev, `collection` in tests), Inertia v2 + React 19, Tailwind v3, axios, PHPUnit 11.

## Global Constraints

- Node 22+ for any `npm`/vite command: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"` (shell default v14 breaks Vite 7).
- Run `php artisan test` before considering any backend task done; all green.
- Tests mock external deps; Scout tests set `config(['scout.driver' => 'collection'])` in `setUp()`.
- JSDoc/PHPDoc + module header comments per repo `CLAUDE.md`; comment the *why*.
- No JS test runner exists — frontend tasks are verified in the browser via preview tools at mobile + desktop breakpoints.
- Frontend paths are hardcoded (e.g. `/api/search`), matching the existing convention (the codebase does not call Ziggy `route()` in JS).

---

### Task 1: `SiteSearch` service + refactor web `SearchController`

**Files:**
- Create: `app/Services/SiteSearch.php`
- Modify: `app/Http/Controllers/SearchController.php`
- Test (safety net, already exists): `tests/Feature/SearchControllerTest.php`

**Interfaces:**
- Produces: `App\Services\SiteSearch::search(string $query, ?int $limit = null): array` — returns a list of `['type' => 'spot_guide'|'blog', 'title' => string, 'slug' => string, 'url' => string, 'description' => ?string, 'thumbnail' => string]`. Empty array when `strlen(trim($query)) < 2`. `$limit` caps each content type (null = uncapped).
- Consumes: `SpotGuide`, `Blog` Scout `search()`.

- [ ] **Step 1: Run the existing search test to confirm the green baseline**

Run: `php artisan test --filter=SearchControllerTest`
Expected: PASS (4 tests) — this is the refactor safety net.

- [ ] **Step 2: Create the `SiteSearch` service**

Create `app/Services/SiteSearch.php`:

```php
<?php

// Shared site-search logic: a published Scout search across spot guides and
// blogs, normalised into a single typed results list. Used by both the full
// search page (SearchController) and the live-suggestions API (Api\SearchController).

namespace App\Services;

use App\Models\Blog;
use App\Models\SpotGuide;

class SiteSearch
{
    /** Minimum query length before we search — avoids noisy single-letter hits. */
    public const MIN_QUERY_LENGTH = 2;

    /**
     * Search published spot guides and blogs for the query, newest matches first
     * within each type. Returns a flat, view-ready array.
     *
     * @param  string    $query  raw user query (trimmed here)
     * @param  int|null  $limit  optional cap applied to each content type
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?int $limit = null): array
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $spotGuides = SpotGuide::search($query)
            ->query(fn ($builder) => $builder->where('is_published', true)->with(['country', 'thumbnailMedia']))
            ->get()
            ->map(fn ($guide) => [
                'type' => 'spot_guide',
                'title' => $guide->title,
                'slug' => $guide->slug,
                'url' => route('spot-guides.show', $guide->slug),
                'description' => $guide->country?->name,
                'thumbnail' => $guide->thumbnailMedia?->getUrl() ?? '',
            ]);

        $blogs = Blog::search($query)
            ->query(fn ($builder) => $builder->where('is_published', true)->with('thumbnailMedia'))
            ->get()
            ->map(fn ($blog) => [
                'type' => 'blog',
                'title' => $blog->title,
                'slug' => $blog->slug,
                'url' => route('blog.show', $blog->slug),
                'description' => $blog->seo_description,
                'thumbnail' => $blog->thumbnailMedia?->getUrl() ?? '',
            ]);

        if ($limit !== null) {
            $spotGuides = $spotGuides->take($limit);
            $blogs = $blogs->take($limit);
        }

        return $spotGuides->concat($blogs)->values()->toArray();
    }
}
```

- [ ] **Step 3: Refactor `SearchController@index` to use the service**

Replace the body of `app/Http/Controllers/SearchController.php` with:

```php
<?php

// Public search page:
//   GET /search?q=… — search
// Delegates the actual searching to App\Services\SiteSearch (shared with the
// live-suggestions API) and renders the full results page.

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SiteSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * Render the search page with all published matches for the query
     * (uncapped — the results page shows everything; the dropdown API caps).
     */
    public function index(Request $request, SiteSearch $search): Response
    {
        $query = $request->input('q', '');
        $results = $search->search($query);

        $page = Page::where('slug', 'search')
            ->where('is_published', true)
            ->with('staticMastheadMedia')
            ->first();

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $results,
            'static_masthead' => $page?->staticMastheadMedia?->getUrl() ?? '',
            'meta' => [
                'title' => $query ? "Search: {$query} | Seabound Souls" : 'Search | Seabound Souls',
                'description' => 'Search for windsurfing destinations and articles.',
            ],
        ]);
    }
}
```

- [ ] **Step 4: Run the existing search test — must stay green**

Run: `php artisan test --filter=SearchControllerTest`
Expected: PASS (4 tests) — behaviour unchanged by the extraction.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SiteSearch.php app/Http/Controllers/SearchController.php
git commit -m "Extract SiteSearch service from SearchController"
```

---

### Task 2: `GET /api/search` live-suggestions endpoint

**Files:**
- Create: `app/Http/Controllers/Api/SearchController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/SearchControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\SiteSearch::search()` from Task 1.
- Produces: `GET /api/search?q=…` (route name `api.search`) → `200 {"results": [...]}`, capped at 6 per type.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/SearchControllerTest.php`:

```php
<?php

// Feature tests for the live-suggestions endpoint GET /api/search. Opts into
// Scout's collection engine (suite runs SCOUT_DRIVER=null) so matching runs
// in-process without an external service.

namespace Tests\Feature\Api;

use App\Models\Blog;
use App\Models\SpotGuide;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);
    }

    public function test_returns_matching_published_spot_guides(): void
    {
        SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);
        SpotGuide::factory()->unpublished()->create(['title' => 'Tarifa Draft', 'slug' => 'tarifa-draft']);

        $response = $this->getJson('/api/search?q=Tarifa');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'spot_guide')
            ->assertJsonPath('results.0.title', 'Tarifa');
    }

    public function test_returns_empty_results_for_short_queries(): void
    {
        SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);

        $this->getJson('/api/search?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_returns_both_blogs_and_spot_guides(): void
    {
        SpotGuide::factory()->create(['title' => 'Windy Bay', 'slug' => 'windy-bay']);
        Blog::factory()->create(['title' => 'Windy days ahead', 'slug' => 'windy-days']);

        $this->getJson('/api/search?q=Windy')
            ->assertOk()
            ->assertJsonCount(2, 'results');
    }

    public function test_caps_results_at_six_per_type(): void
    {
        for ($index = 1; $index <= 8; $index++) {
            SpotGuide::factory()->create(['title' => "Sylt Spot {$index}", 'slug' => "sylt-spot-{$index}"]);
        }

        $this->getJson('/api/search?q=Sylt')
            ->assertOk()
            ->assertJsonCount(6, 'results');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=Api\\\\SearchControllerTest`
Expected: FAIL — route `/api/search` returns 404 (not yet defined).

- [ ] **Step 3: Create the API controller**

Create `app/Http/Controllers/Api/SearchController.php`:

```php
<?php

// Live search-suggestions endpoint:
//   GET /api/search?q=… — api.search
// Returns a small JSON list (capped per type) for the nav search dropdown,
// via the shared SiteSearch service.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Number of results returned per content type for the dropdown. */
    private const SUGGESTION_LIMIT = 6;

    public function index(Request $request, SiteSearch $search): JsonResponse
    {
        $results = $search->search($request->input('q', ''), self::SUGGESTION_LIMIT);

        return response()->json(['results' => $results]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, add the import and route:

```php
use App\Http\Controllers\Api\SearchController;
```

```php
Route::get('/search', [SearchController::class, 'index'])->name('api.search');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=Api\\\\SearchControllerTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS (all previous + 4 new).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/SearchController.php routes/api.php tests/Feature/Api/SearchControllerTest.php
git commit -m "Add GET /api/search live-suggestions endpoint"
```

---

### Task 3: `SearchPanel` component (animated slide-down + live dropdown)

**Files:**
- Create: `resources/js/Components/Common/SearchPanel.tsx`

**Interfaces:**
- Produces: default-exported `SearchPanel` React component with props `{ open: boolean; onClose: () => void; transparent?: boolean }`.
- Consumes: `GET /api/search` (Task 2) via `axios`; `@inertiajs/react` `router`.

- [ ] **Step 1: Create the component**

Create `resources/js/Components/Common/SearchPanel.tsx`:

```tsx
/**
 * SearchPanel — the site search UI used inside the NavBar. Renders an animated
 * slide-down bar with a debounced live-results dropdown (queries /api/search),
 * keyboard navigation, and Enter-to-full-results-page. The panel is always
 * mounted and animates between open/closed so the open is smooth (no pop-in).
 */
import { useState, useEffect, useRef, useCallback } from 'react'
import { router } from '@inertiajs/react'
import axios from 'axios'
import { faSpinner } from '@fortawesome/free-solid-svg-icons'
import Icon from './Icon'

interface SearchResult {
    type: string
    title: string
    slug: string
    url: string
    description?: string
    thumbnail?: string
}

interface Props {
    open: boolean
    onClose: () => void
    transparent?: boolean
}

const DEBOUNCE_MS = 250
const MIN_CHARS = 2

const SearchPanel = ({ open, onClose, transparent }: Props) => {
    const [value, setValue] = useState('')
    const [results, setResults] = useState<SearchResult[]>([])
    const [loading, setLoading] = useState(false)
    const [highlighted, setHighlighted] = useState(-1)
    const inputRef = useRef<HTMLInputElement>(null)

    // Focus the input when the panel opens; clear everything when it closes.
    useEffect(() => {
        if (open) {
            inputRef.current?.focus()
        } else {
            setValue('')
            setResults([])
            setHighlighted(-1)
            setLoading(false)
        }
    }, [open])

    // Debounced live search. Cancels the in-flight timer on every keystroke so
    // we only hit /api/search once the user pauses.
    useEffect(() => {
        const query = value.trim()
        if (query.length < MIN_CHARS) {
            setResults([])
            setLoading(false)
            return
        }
        setLoading(true)
        const handle = setTimeout(() => {
            axios
                .get('/api/search', { params: { q: query } })
                .then(({ data }) => {
                    setResults(data.results ?? [])
                    setHighlighted(-1)
                })
                .catch(() => setResults([]))
                .finally(() => setLoading(false))
        }, DEBOUNCE_MS)
        return () => clearTimeout(handle)
    }, [value])

    /** Navigate to the full /search results page for the current query. */
    const goToFullResults = useCallback(() => {
        const query = value.trim()
        if (!query) return
        router.get('/search', { q: query })
        onClose()
    }, [value, onClose])

    /** Navigate straight to a chosen result. */
    const selectResult = useCallback((result: SearchResult) => {
        router.visit(result.url)
        onClose()
    }, [onClose])

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault()
            setHighlighted(prev => Math.min(prev + 1, results.length - 1))
        } else if (event.key === 'ArrowUp') {
            event.preventDefault()
            setHighlighted(prev => Math.max(prev - 1, -1))
        } else if (event.key === 'Enter') {
            event.preventDefault()
            if (highlighted >= 0 && results[highlighted]) {
                selectResult(results[highlighted])
            } else {
                goToFullResults()
            }
        } else if (event.key === 'Escape') {
            onClose()
        }
    }

    const showDropdown = open && value.trim().length >= MIN_CHARS

    return (
        <div
            className={[
                'overflow-hidden transition-all duration-300 ease-out',
                open ? 'max-h-[75vh] opacity-100' : 'max-h-0 opacity-0',
                transparent ? 'bg-primary/95 backdrop-blur-sm' : 'bg-primary',
            ].join(' ')}
        >
            <div className="container mx-auto py-3">
                <label htmlFor="site-search" className="sr-only">Search</label>
                <input
                    ref={inputRef}
                    id="site-search"
                    name="q"
                    type="text"
                    value={value}
                    onChange={event => setValue(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Search destinations, articles..."
                    autoComplete="off"
                    className="w-full px-4 py-2 rounded-md text-sm text-gray-900 placeholder:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-lighter shadow-sm"
                />

                {showDropdown && (
                    <div className="mt-2 rounded-md bg-white shadow-lg overflow-hidden max-h-[60vh] overflow-y-auto">
                        {loading && (
                            <div className="flex items-center gap-x-2 px-4 py-3 text-sm text-gray-500">
                                <Icon icon={faSpinner} size="size-4" className="animate-spin" />
                                Searching…
                            </div>
                        )}

                        {!loading && results.length === 0 && (
                            <p className="px-4 py-3 text-sm text-gray-500">No results for "{value.trim()}"</p>
                        )}

                        {!loading && results.map((result, index) => (
                            <button
                                key={`${result.type}-${result.slug}`}
                                type="button"
                                onClick={() => selectResult(result)}
                                onMouseEnter={() => setHighlighted(index)}
                                className={[
                                    'w-full flex items-center gap-x-3 px-4 py-2 text-left transition-colors',
                                    highlighted === index ? 'bg-primary-lightest' : 'hover:bg-gray-50',
                                ].join(' ')}
                            >
                                {result.thumbnail
                                    ? <img src={result.thumbnail} alt="" className="w-10 h-10 rounded object-cover flex-shrink-0" />
                                    : <span className="w-10 h-10 rounded bg-gray-100 flex-shrink-0" />}
                                <span className="min-w-0">
                                    <span className={`block text-[0.65rem] font-semibold uppercase tracking-wide ${result.type === 'spot_guide' ? 'text-primary' : 'text-orange'}`}>
                                        {result.type === 'spot_guide' ? 'Destination' : 'Blog'}
                                    </span>
                                    <span className="block text-sm font-medium text-secondary truncate">{result.title}</span>
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    )
}

export default SearchPanel
```

- [ ] **Step 2: Type-check / build**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: build succeeds (no TypeScript/import errors). If `Icon` doesn't accept a `className` prop, check `resources/js/Components/Common/Icon.tsx` and pass the class the way that component expects.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Common/SearchPanel.tsx
git commit -m "Add SearchPanel: animated slide-down search with live dropdown"
```

*(Verified in the browser after Task 4 wires it in.)*

---

### Task 4: Wire SearchPanel into NavBar + staggered mobile menu

**Files:**
- Modify: `resources/js/Components/Common/NavBar.tsx`

**Interfaces:**
- Consumes: `SearchPanel` (Task 3).

- [ ] **Step 1: Replace the inline search block and remove `handleSearch`**

In `resources/js/Components/Common/NavBar.tsx`:
- Add the import: `import SearchPanel from './SearchPanel'`
- Delete the `handleSearch` function (lines defining it).
- Replace the whole `{showSearch && ( <div…><form…/></div> )}` block at the top of the wrapper with:

```tsx
<SearchPanel open={showSearch} onClose={() => setShowSearch(false)} transparent={isTransparent} />
```

- [ ] **Step 2: Add the mobile backdrop + staggered slide-down menu**

Replace the `<nav>` block with the version below (adds per-link stagger; changes the closed state from parked-below to a small slide-down-from-header), and add a dim backdrop directly before `<header>`:

```tsx
{/* Mobile backdrop — dims the page behind the open menu; click to close. */}
{showMobileNav && (
    <div
        className="lg:hidden fixed inset-x-0 bottom-0 top-[5rem] bg-black/40 z-0"
        onClick={() => setShowMobileNav(false)}
        aria-hidden="true"
    />
)}
```

```tsx
<nav className={[
    'max-lg:absolute max-lg:top-[5rem] max-lg:left-0 max-lg:w-full max-lg:bg-primary max-lg:container max-lg:mx-auto max-lg:z-10',
    'max-lg:transition-all max-lg:duration-300 max-lg:ease-out',
    showMobileNav
        ? 'max-lg:translate-y-0 max-lg:opacity-100'
        : 'max-lg:-translate-y-3 max-lg:opacity-0 max-lg:pointer-events-none',
].join(' ')}>
    <ul className="max-lg:pt-6 max-lg:pb-8 flex flex-col lg:flex-row gap-y-3 lg:gap-x-6">
        {navItems.map(({ href, title }, index) => (
            <li
                key={title}
                className={[
                    'transition-all duration-300 ease-out',
                    'max-lg:opacity-0 max-lg:translate-y-3',
                    showMobileNav ? 'max-lg:opacity-100 max-lg:translate-y-0' : '',
                ].join(' ')}
                // Stagger only matters on mobile (menu open); harmless on desktop
                // where the li has no opacity/transform change to delay.
                style={{ transitionDelay: showMobileNav ? `${index * 60}ms` : '0ms' }}
            >
                <Link
                    href={href}
                    className={[
                        'text-base lg:text-sm uppercase tracking-wide font-medium whitespace-nowrap transition-opacity duration-200',
                        url === href ? 'text-white opacity-100' : 'text-white/70 hover:text-white hover:opacity-100',
                    ].join(' ')}
                    onClick={() => setShowMobileNav(false)}
                >
                    {title}
                </Link>
            </li>
        ))}
    </ul>
</nav>
```

- [ ] **Step 3: Build**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: build succeeds.

- [ ] **Step 4: Browser-verify (desktop)** with the preview tools (Laravel `:8000` + Vite running):
  - Load `/`. Click the search icon → the bar **slides down smoothly** (no pop-in).
  - Type `kar` → after ~250ms a dropdown shows **Karpathos** (Destination badge + thumbnail).
  - ArrowDown highlights it; Enter navigates to the guide. Reopen; type + Enter with nothing highlighted → `/search?q=…` page. Esc closes.

- [ ] **Step 5: Browser-verify (mobile)** via `preview_resize` to ~375px wide:
  - Open the hamburger → menu **slides down from the header** with links **cascading in**; a dim backdrop covers the page; clicking the backdrop or a link closes it. Confirm no upward swoop.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Common/NavBar.tsx
git commit -m "Wire SearchPanel into NavBar; staggered slide-down mobile menu"
```

---

## Self-review notes

- **Spec coverage:** A→Task 1, B→Task 2, C→Tasks 3+4, D(mobile)→Task 4, cleanup(SearchPanel extraction)→Tasks 3+4. Testing: backend TDD in Tasks 1–2; frontend browser-verify in Task 4.
- **Type consistency:** `SiteSearch::search(string, ?int): array` used identically in `SearchController` (no limit) and `Api\SearchController` (limit 6). `SearchPanel` props `{open,onClose,transparent}` match the NavBar call site. Result shape (`type/title/slug/url/description/thumbnail`) is identical across service, API test, and `SearchPanel`/`Search.tsx`.
- **No placeholders:** all steps carry concrete code/commands.
