# Site-wide editable SEO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make title, description, keywords, and OG image editable in-app for every public page, and fix the never-rendered keywords + doubled title suffix.

**Architecture:** Listing/system controllers read SEO from their existing `Page` record (the pattern the homepage already uses); detail controllers complete their meta; the React `Layout` renders a keywords `<meta>` and every page threads `keywords` + `ogImage` into it. Titles carry no manual brand suffix — the global `app.tsx` callback adds `| Seabound Souls` once.

**Tech Stack:** Laravel 12, Inertia v2, React 19 + TS, Filament 3.3, PHPUnit 11 (in-memory SQLite).

## Global Constraints

- **No manual `| Seabound Souls` suffix** in any controller title — `resources/js/app.tsx`'s Inertia `title` callback is the single source of the suffix.
- **Keywords pass as the `seo_keywords` array**; the Layout joins them comma-separated. Empty array → no `<meta name="keywords">`.
- **Every page provides a fallback** per SEO field so meta is never empty; use elvis (`?:`) so empty strings also fall back.
- **Reuse existing `Page` records** as the system-page SEO source; no new model/resource.
- Frontend `.tsx` changes are verified by build + browser (no JS test runner in this project); backend meta is covered by feature tests asserting Inertia props.

---

## File Structure

- `app/Http/Controllers/BlogController.php` / `DestinationController.php` / `SearchController.php` / `ContactController.php` — listing meta from Page.
- `app/Http/Controllers/BlogController.php` (show) / `PageController.php` (show) / `HomepageController.php` — complete detail meta.
- `database/migrations/*_create_contact_page.php` — seed the `contact` Page (runs on deploy).
- `resources/js/Layouts/Layout.tsx` — keywords `<meta>` + prop.
- `resources/js/Pages/**` — thread `keywords` + `ogImage` into `<Layout>`.
- Tests: `tests/Feature/*ControllerTest.php`.

---

### Task 1: Listing/system controllers read Page SEO (+ seed `contact` Page)

**Files:**
- Create: `database/migrations/2026_07_09_170000_create_contact_page.php`
- Modify: `app/Http/Controllers/BlogController.php` (index ~line 49-55), `DestinationController.php` (index ~71-78), `SearchController.php` (index ~32-42), `ContactController.php` (index ~25-33)
- Test: `tests/Feature/SeoMetaTest.php` (create)

**Interfaces:**
- Produces: each listing route's Inertia `meta` = `{ title, description, keywords: string[], og_image: string }`, title without brand suffix.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SeoMetaTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * SEO meta is editable per page via the matching Page record and always has a
 * single brand suffix (added globally in app.tsx, so controllers emit bare
 * titles). Keywords + OG image flow through for every page.
 */
class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_title_has_no_manual_brand_suffix(): void
    {
        $this->get('/blog')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Blog')
            ->where('meta.keywords', [])
        );
    }

    public function test_blog_index_reads_seo_from_its_page_record(): void
    {
        Page::factory()->create([
            'slug' => 'blog',
            'is_published' => true,
            'seo_title' => 'Windsurf Journal',
            'seo_description' => 'Our latest posts.',
            'seo_keywords' => ['windsurfing', 'blog'],
        ]);

        $this->get('/blog')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Windsurf Journal')
            ->where('meta.description', 'Our latest posts.')
            ->where('meta.keywords', ['windsurfing', 'blog'])
        );
    }

    public function test_contact_page_is_seeded_and_seo_reads_from_it(): void
    {
        $this->assertDatabaseHas('pages', ['slug' => 'contact']);

        Page::where('slug', 'contact')->update([
            'seo_title' => 'Say Hello',
            'seo_keywords' => ['contact'],
        ]);

        $this->get('/contact')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Say Hello')
            ->where('meta.keywords', ['contact'])
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=SeoMetaTest`
Expected: FAIL — blog title is `'Blog | Seabound Souls'`, `meta.keywords` missing, no `contact` page.

- [ ] **Step 3: Create the contact-Page migration**

Create `database/migrations/2026_07_09_170000_create_contact_page.php`:

```php
<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

// Seed a Page record for the contact page so its SEO (title/description/
// keywords/OG image) is editable in the Pages admin like the other system
// pages. The page's content is not rendered — ContactController owns the view;
// this row is an SEO holder. Runs on deploy so production gets it too.
return new class extends Migration
{
    public function up(): void
    {
        Page::firstOrCreate(
            ['slug' => 'contact'],
            ['title' => 'Contact', 'template' => 'standard', 'is_published' => true],
        );
    }

    public function down(): void
    {
        Page::where('slug', 'contact')->where('template', 'standard')->delete();
    }
};
```

- [ ] **Step 4: BlogController@index — meta from Page**

In `app/Http/Controllers/BlogController.php`, the `$page` query already exists (`Page::where('slug', 'blog')…->with('staticMastheadMedia')`). Add `ogImageMedia` to its `->with(...)` so it becomes `->with(['staticMastheadMedia', 'ogImageMedia'])`. Then replace the `'meta' => [...]` block in the `index` render with:

```php
            'meta' => [
                'title' => $page?->seo_title ?: 'Blog',
                'description' => $page?->seo_description ?: 'Windsurfing tips, guides and destination insights.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
```

- [ ] **Step 5: DestinationController@index — meta from Page**

Add `ogImageMedia` to its `$page->with([...])` (`->with(['staticMastheadMedia', 'ogImageMedia'])`). Replace the index `'meta'` block:

```php
            'meta' => [
                'title' => $page?->seo_title ?: 'Destinations',
                'description' => $page?->seo_description ?: 'Explore windsurfing destinations around the world.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
```

- [ ] **Step 6: SearchController@index — meta from Page (keep dynamic query title)**

Add `ogImageMedia` to its `$page->with([...])`. Replace the `'meta'` block:

```php
            'meta' => [
                'title' => $query ? "Search: {$query}" : ($page?->seo_title ?: 'Search'),
                'description' => $page?->seo_description ?: 'Search for windsurfing destinations and articles.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
```

- [ ] **Step 7: ContactController@index — add the Page lookup + meta**

In `app/Http/Controllers/ContactController.php`, add `use App\Models\Page;` at the top (with the other imports). Replace the `index()` body's return with:

```php
    public function index(): Response
    {
        $page = Page::where('slug', 'contact')->with('ogImageMedia')->first();

        return Inertia::render('Contact', [
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'meta' => [
                'title' => $page?->seo_title ?: 'Contact',
                'description' => $page?->seo_description ?: 'Get in touch with the Seabound Souls team.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
        ]);
    }
```

- [ ] **Step 8: Run tests**

Run: `php artisan test --filter=SeoMetaTest`
Expected: PASS.

Run: `php artisan test`
Expected: full suite green.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/BlogController.php app/Http/Controllers/DestinationController.php app/Http/Controllers/SearchController.php app/Http/Controllers/ContactController.php database/migrations/2026_07_09_170000_create_contact_page.php tests/Feature/SeoMetaTest.php
git commit -m "feat: listing/system pages read SEO from their Page record"
```

---

### Task 2: Detail controllers — complete keywords + OG image

**Files:**
- Modify: `app/Http/Controllers/BlogController.php` (show), `app/Http/Controllers/PageController.php` (show), `app/Http/Controllers/HomepageController.php`
- Test: `tests/Feature/SeoMetaTest.php` (add methods)

**Interfaces:**
- Consumes: nothing new.
- Produces: `Blog@show`, `Page@show`, `Homepage` Inertia `meta` now include `keywords: string[]`; homepage/detail `og_image` present.

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/SeoMetaTest.php`:

```php
    public function test_blog_show_exposes_keywords(): void
    {
        $blog = \App\Models\Blog::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
            'seo_keywords' => ['mauritius', 'wave'],
        ]);

        $this->get("/blog/{$blog->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('meta.keywords', ['mauritius', 'wave'])
        );
    }

    public function test_homepage_exposes_keywords_and_bare_fallback_title(): void
    {
        // No home Page → fallback title with no brand baked in (suffix is global).
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Windsurfing Destination Guide')
            ->where('meta.keywords', [])
        );
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SeoMetaTest`
Expected: FAIL — `meta.keywords` absent on blog show; homepage title is `'Seabound Souls - Windsurfing Destination Guide'`.

- [ ] **Step 3: BlogController@show — add keywords**

In the `show` method's `'meta'` array, add the keywords line (keep existing title/description/og_image):

```php
                'keywords' => $blog->seo_keywords ?? [],
```

- [ ] **Step 4: PageController@show — add keywords**

In the `show` method's `'meta'` array, add:

```php
                'keywords' => $page->seo_keywords ?? [],
```

- [ ] **Step 5: HomepageController — keywords + og + bare fallback title**

Ensure the `$page` query eager-loads og: add `ogImageMedia` to its `->with([...])`. Replace the `'meta'` block:

```php
            'meta' => [
                'title' => $page?->seo_title ?: 'Windsurfing Destination Guide',
                'description' => $page?->seo_description ?: 'Discover the best windsurfing destinations around the world.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=SeoMetaTest`
Expected: PASS.

Run: `php artisan test`
Expected: full suite green (note: `HomepageControllerTest`/`BlogControllerTest` may assert the old homepage/blog titles — update those assertions to the new bare titles if they fail).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/BlogController.php app/Http/Controllers/PageController.php app/Http/Controllers/HomepageController.php tests/Feature/SeoMetaTest.php tests/Feature/HomepageControllerTest.php tests/Feature/BlogControllerTest.php
git commit -m "feat: detail pages emit keywords + OG image, homepage bare title"
```

---

### Task 3: Frontend — render keywords + thread props into every page

**Files:**
- Modify: `resources/js/Layouts/Layout.tsx`
- Modify: `resources/js/Pages/Blog/Index.tsx`, `Blog/Show.tsx`, `Destinations/Index.tsx`, `Contact.tsx`, `Search.tsx`, `Homepage.tsx`, `Page/Show.tsx`, `SpotGuide/Show.tsx`

**Interfaces:**
- Consumes: each page's `meta.keywords: string[]` and `meta.og_image: string`.
- Produces: `<meta name="keywords">` + `<meta property="og:image">` in the document head when present.

- [ ] **Step 1: Add keywords support to the Layout**

In `resources/js/Layouts/Layout.tsx`, add `keywords?: string[]` to `LayoutProps` and render it in `<Head>` after the description line:

```tsx
interface LayoutProps {
    children: ReactNode
    title?: string
    description?: string
    keywords?: string[]
    ogImage?: string
}
```

```tsx
                {description && <meta name="description" content={description} />}
                {keywords && keywords.length > 0 && (
                    <meta name="keywords" content={keywords.join(', ')} />
                )}
                {ogImage && <meta property="og:image" content={ogImage} />}
```

- [ ] **Step 2: Thread `keywords` + `ogImage` into every `<Layout>` usage**

In each page component, add `keywords={meta.keywords}` (and `ogImage={meta.og_image}` where not already passed) to the `<Layout …>` element. Also extend each file's `meta` prop TypeScript type to include `keywords?: string[]` and `og_image?: string`. The eight files and their current `<Layout>` calls:

- `Pages/Blog/Index.tsx`: `<Layout title={meta.title} description={meta.description}>` → add `keywords={meta.keywords} ogImage={meta.og_image}`
- `Pages/Blog/Show.tsx`: `<Layout title={meta.title} description={meta.description} ogImage={meta.og_image}>` → add `keywords={meta.keywords}`
- `Pages/Destinations/Index.tsx`: add `keywords={meta.keywords} ogImage={meta.og_image}`
- `Pages/Contact.tsx`: add `keywords={meta.keywords} ogImage={meta.og_image}`
- `Pages/Search.tsx`: add `keywords={meta.keywords} ogImage={meta.og_image}`
- `Pages/Homepage.tsx`: add `keywords={meta.keywords} ogImage={meta.og_image}`
- `Pages/Page/Show.tsx`: `<Layout … ogImage={meta.og_image}>` → add `keywords={meta.keywords}`
- `Pages/SpotGuide/Show.tsx`: `<Layout … ogImage={meta.og_image}>` → add `keywords={meta.keywords}`

For the `meta` type in each file, ensure it includes:

```ts
meta: {
    title: string
    description: string
    keywords?: string[]
    og_image?: string
}
```

(Match each file's existing `meta` type declaration; add the two optional fields if missing.)

- [ ] **Step 3: Build**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: `✓ built` with no TS errors.

- [ ] **Step 4: Verify in the browser (no JS unit runner)**

With the local app served, load a page whose Page record (or model) has keywords set and confirm the head contains `<meta name="keywords" content="…">` and the title has a single ` | Seabound Souls`. Check a blog post, `/blog`, and `/contact`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/Layout.tsx resources/js/Pages/
git commit -m "feat: render keywords meta and thread keywords/og into all pages"
```

---

## Post-implementation

Before the PR: run `reconcile-everything` (folded) — history doc `docs/history/2026-07-09-site-wide-seo.md`, SITREP bullet + roadmap row, marker. Then PR; after merge, dance + tag. The `contact` Page migration runs on the next Cloud deploy, creating the editable contact-SEO row in production.
