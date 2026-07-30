# Seabound Souls — Laravel/Inertia Project

## Project Overview

A full rebuild of the **Seabound Souls** windsurfing destination guide website. The original was built with **Next.js 15 + Sanity CMS**. This project is a complete port to **Laravel 12 + Inertia.js + React + Filament admin**. The design reference and component logic should always be compared against the original at `../seabound-souls-sanity-next-js/`.

**What the site is:** A windsurfing destination guide featuring spot guides with maps, weather data, recommendations, galleries, a blog, and contact form.

**Status & intent:** This is Ben's **personal** project (NOT an IFP repo). It began as a test to see whether Claude could rebuild the old Next.js/Sanity site in Laravel — it could. It is now being polished, at a relaxed pace, toward an actual launch, and is deliberately held to the **same working standard Ben uses on IFP projects day-to-day** (TDD, dark mode, full responsiveness, documented code). The rules below encode that standard.

---

## Working standard — read this first

### Scope — stay in this directory
All work happens within this repo (or one of its git worktrees). Do **not** read, write, or reference files outside this directory tree — including the sibling design reference `../seabound-souls-sanity-next-js/` — **unless Ben explicitly instructs it in the moment**. The original Next.js/Sanity build is documented throughout this file; that documentation is sufficient for most work. Only navigate to the sibling repo when told to for a specific comparison.

### Session start
- **Node:** The shell default is Node **v14.16.0** (nvm) which **cannot run Vite 7** (`Cannot find module 'node:path'`). Use v22+ — `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"` before `npm run dev` / `npm run build`. (No `.nvmrc` committed yet — pending follow-up.)
- **Database:** Local dev runs on **PostgreSQL** (matches Laravel Cloud), DB `seabound_souls_dev` on `127.0.0.1:5432`. It's served by the Homebrew `postgresql@16` service (`brew services list` to check; `brew services start postgresql@16` if down) — the app 500s on a DB connection error if it isn't running. The **test suite** runs on in-memory SQLite (`phpunit.xml`), so `php artisan test` needs no Postgres.
- **Sub-agents must source nvm.** A spawned sub-agent (Agent tool) starts in a fresh shell where nvm is not sourced, so `node` falls back to v14 and npm/vite/npx fail with misleading errors — often the sub-agent wrongly concludes the right Node isn't installed. When delegating any task that may run node/npm, include in the prompt: `source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && <command>`, and state explicitly that the system `node` is NOT the project version.
- **One-command dev startup:** `composer dev` runs Vite HMR + the queue worker (`queue:listen`) + logs (`pail`) together via `concurrently` — this is the recommended way to work locally, since it guarantees a worker is running for the weather-fetch triggers. It does **not** start `php artisan serve` (Herd serves the app at `seaboundsouls.test`). Prefix with the Node 22 PATH export (Vite runs inside it). Stop with Ctrl-C (kills all three).
- **Dev servers (manual alt.):** Herd serves the app; Vite HMR `:5173` (`npm run dev`) must run or the app 500s with "Unable to locate file in Vite manifest" (Vite down / `public/hot` missing). Without Herd, `php artisan serve` on `:8000` also works.
- **Queue worker:** the weather-fetch triggers (auto-on-create + dashboard "Fetch all") dispatch to the `database` queue. `composer dev` runs one for you; otherwise run `php artisan queue:work` (or `queue:listen`) yourself, or jobs sit unprocessed in the `jobs` table. In production a supervised worker process must run.
- **Project status — read at session start:** [`SITREP.md`](SITREP.md) (current state, next actions, roadmap) and [`docs/TODO.md`](docs/TODO.md) (backlog). Per-feature history lives in `docs/history/`.

### Git workflow
- Branch stays **`main`** (not `master`). **No direct commits to `main`** — every change reaches trunk via a feature branch + PR.
- **Pull `main` before cutting a branch:** `git checkout main && git fetch && git pull` before every `git checkout -b`, even mid-session.
- **After a merge, verify it landed before any cleanup**, then run the `git-dance` skill. Never trust an unverified "it's merged" claim.
- **Reconcile — fold into the feature PR:** run `reconcile-everything` on the feature branch *before* its PR merges, so reconcile docs ride in the same PR as the code. If asked to merge/dance/push un-reconciled work, stop and fold reconcile in first.

### Testing (TDD is the default)
- **Write the test first.** Follow the `test-driven-development` skill for any feature or bugfix — red, green, refactor.
- **Run `php artisan test` before calling any task done.** All tests must pass; don't dismiss pre-existing failures — fix or flag them.
- **Mock external dependencies** so tests need no network or live DB: OpenWeatherMap, reCAPTCHA, Mapbox, mail.
- **Current baseline:** only the two stock Laravel example tests exist — real coverage is a standing backlog item to backfill as areas are touched.

### Code clarity & documentation
- **JSDoc on every function** (exported or internal) in `.ts`/`.tsx`; PHPDoc equivalent on PHP methods where non-obvious. Explain *why* where the logic isn't self-evident. To be enforced via a husky + lint-staged + eslint-plugin-jsdoc pre-commit hook (setup is a pending follow-up).
- **No single-letter variables** (except `i` in a trivial numeric loop) and **no cryptic abbreviations** — `fieldName` not `f`, `iterationRows` not `iters`.
- **Comment the *why*, not the *what*** — business rules, surprising behaviour, non-trivial queries get an inline reason.
- **Module header comment** at the top of each source file describing its role.

### Web UI
- **Dark mode is required** alongside light. Use semantic theme tokens (CSS variables on `:root`, overridden under `html.dark`) — never raw `bg-white`/`text-gray-*`/palette-specific utilities that won't flip. Inline-style/SVG colours must reference `var(--…)`. Ship a no-flash theme switch (apply persisted choice before paint). Add a CI regression guard against banned raw colour utilities. Verify every changed screen in both themes before merge. *(Greenfield — the token layer is a pending follow-up.)*
- **Fully responsive** on all devices — verify layouts at mobile / tablet / desktop breakpoints before merge, not just desktop.
- Avoid ad-blocker trigger words (`ad`, `banner`, `promo`, `sponsor`, `tracker`) in routes/asset/chunk names — they get silently blocked. Prefer neutral synonyms.

### Database
- **Local dev + production: PostgreSQL** (`pgsql`). Tests: in-memory SQLite. The pre-Postgres SQLite file was migrated once via `php artisan db:import-from-sqlite` (reads the `sqlite_legacy` connection). Don't reintroduce SQLite as the app's default connection.
- Schema changes go through Laravel migrations (`php artisan make:migration`). **Never edit a migration that has already run** — add a new migration for the change.
- **Refresh local data from production:** `php artisan db:pull-from-production` overwrites the local dev DB (`seabound_souls_dev`) with a fresh read-only copy of prod (Laravel Cloud / Neon, DB `main`, Postgres 17). Local-env-only, confirm-before-wipe (`--force` to skip). Needs `PROD_DB_*` in your (gitignored) `.env` and PG17 client tools (`brew install postgresql@17` — pg_dump must be ≥ the server). DB-only: media (R2) isn't pulled, so pulled images won't render locally (test uploads still work, on the local disk).

---

## Original Next.js/Sanity Project

**Location:** `../seabound-souls-sanity-next-js/`

### Architecture
- **Next.js 15** App Router with server-side rendering
- **Sanity CMS** as the headless content backend (Studio embedded at `/admin`)
- Tailwind CSS + `@tailwindcss/typography`
- FontAwesome icons, Mapbox GL, Recharts, Swiper, Framer Motion, fslightbox

### Content Types (Sanity Schemas)
| Type | Purpose |
|------|---------|
| `homepage` | Single document — masthead slider + content builder |
| `spotGuides` | Destination pages — full spot data, conditions, locations, recommendations |
| `blogs` | Blog posts with flexible content builder |
| `pages` | Generic pages via standardSchema factory |
| `search` | Search page config |

### Content Builder Blocks (shared across types)
`splitImageText`, `contentWithBackgroundImage`, `richText`, `gallery`, `singleImage`, `imagePair`, `listContentBlogs`, `listContentSpotGuides`, `infographic`, `detailedLinksWithBackgroundImage`

### Key Components (src/app/components/)
- **Common:** `NavBar`, `Footer`, `Icon`, `Button`, `BlockWrapper`, `ContentBuilder`, `AnimateInView`, `AnimatedCounter`, `ImageFromCms`, `SpotOverview`, `Card`, `BackgroundImageCard`
- **Masthead:** `StaticMasthead`, `MastheadSlider`
- **Content:** `ContentWithBackgroundImage`, `RichText`, `Gallery`, `ImagePair`, `SingleImage`, `SplitImageText`, `DetailedLinksWithBackgroundImage`
- **Map:** `Map`, `MapLegend`
- **Spot-specific:** `LiveWeatherData`, `WhenToGo`, `WindsurfingLocations`, `RelatedSpotGuides`, `RelatedSpotGuideSlider`
- **Forms:** `ContactFormServer`, `ContactFormClient`

### Context Providers
- **MapContext** — manages Mapbox `viewState` (lat/lng/zoom) across map components; default location: London
- **FullHistoricalDatasetContext** — provides historical weather data to destination components

### Routing
| Route | Purpose |
|-------|---------|
| `/` | Homepage |
| `/destinations/[slug]` | Spot guide |
| `/blog/[slug]` | Blog post |
| `/search` | Search |
| `/[...slug]` | Catch-all pages |
| `/admin/[[...index]]` | Sanity Studio |

---

## Laravel/Inertia Rebuild

### Stack
- **Laravel 12** — backend, routing, models, API
- **Inertia.js v2** — SPA bridge (no JSON API between frontend/backend)
- **React 19** — frontend views
- **Filament v3** — admin panel (replaces Sanity Studio)
- **Spatie Media Library v11** — image management (replaces Sanity image hosting)
- **Tailwind CSS v3** + `@tailwindcss/typography`
- **Laravel Scout** — search (replaces Sanity search)
- **Vite 7** — asset bundling

### Key External Services
| Service | Config Key | Usage |
|---------|-----------|-------|
| Mapbox | `MAPBOX_TOKEN` | Interactive maps |
| OpenWeatherMap | `OPENWEATHERMAP_API_KEY` | Live weather at lat/lon |
| reCAPTCHA | `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | Contact form protection |
| Mail (Postmark/Resend) | Standard Laravel mail config | Contact form emails |

---

## Database Schema

### Tables & Key Fields

**`countries`**
- `name`, `slug` (unique), `continent` (enum: europe, africa, asia, north-america, south-america, oceania)

**`spot_guides`**
- `title`, `slug` (unique), `country_id` (FK → countries)
- `timezone`, `latitude`, `longitude` (decimal:10,7)
- `introduction_text` (longText)
- `spot_overview` (JSON) — sailing_style, best_conditions, best_direction, wind_conditions, water_conditions, launch_zone
- `water_conditions` (JSON) — content, text_right, background image
- `wind_conditions` (JSON) — content, text_right, background image
- `travelling_to` (JSON), `lessons_and_hire` (JSON)
- `content_blocks` (JSON) — flexible block builder
- `when_to_go`, `where_to_stay_intro`, `where_to_eat_intro` (longText)
- `seo_title`, `seo_description`, `seo_keywords` (JSON)
- `is_published` (bool), `is_featured` (bool — one featured guide, enforced by the `HasSingleFeatured` trait), `published_at`, soft deletes

**`blogs`**
- `title`, `slug`, `content_blocks` (JSON), SEO fields, `is_published`, `is_featured` (bool — one featured post, `HasSingleFeatured`), soft deletes

**`tags`** (curated blog tags)
- `name`, `slug` (partial-unique `WHERE deleted_at IS NULL` — reusable after soft-delete), `description`, `seo_title`, `seo_description`, `sort_order`, `thumbnail_media_id` (FK, hub card), `static_masthead_media_id` (FK, tag hero), soft deletes
- `blog_tag` pivot: `blog_id` + `tag_id` (unique together, cascade on delete)

**`pages`**
- `title`, `slug`, `template` (default: standard), `content_blocks` (JSON), SEO fields, `is_published`, soft deletes

**`recommendations`**
- `spot_guide_id` (FK), `type` (enum: stay/eat), `name`, `description`, `url`
- `latitude`, `longitude`, `sort_order`

**`windsurfing_locations`**
- `spot_guide_id` (FK), `name`, `description`, `latitude`, `longitude`, `sort_order`

**`weather_records`**
- `spot_guide_id` (FK), `year` (smallint), `month` (tinyint)
- `avg_temp`, `kts_wind`, `kts_gust` (decimal:5,1)
- `mph_wind`, `mph_gust`, `kph_wind`, `kph_gust` (smallint)
- Unique constraint: `(spot_guide_id, year, month)`

**`spot_sailable_days`**
- `spot_guide_id` (FK, cascade on delete), `date`, `year`, `month`
- `qualifying_wind_kts` (decimal:5,1) — the day's 2nd-highest **sustained**-wind 9am–7pm hour (the blend's steadiness floor — see note below)
- `qualifying_gust_kts` (decimal:5,1) — the day's 2nd-highest **gust** 9am–7pm hour (primary ranking metric — see note below)
- Unique constraint: `(spot_guide_id, date)`
- Daily layer feeding the `/destinations` sailable-days ranking; populated by `WeatherFetcher` in the same pass as `weather_records`

**`media_library`** — centralised image library; each row is one image, referenced by FK from other tables

**`media`** — Spatie Media Library managed table (now attached only to `MediaLibrary` model, collection `file`)

---

## Models & Relationships

| Model | Traits | Relationships |
|-------|--------|---------------|
| `MediaLibrary` | HasMedia | — (owns Spatie `file` collection) |
| `SpotGuide` | SoftDeletes, Searchable | belongsTo Country; hasMany Recommendations, WindsurfingLocations, WeatherRecords, SailableDays; belongsTo MediaLibrary (×8) |
| `Blog` | SoftDeletes, Searchable | belongsTo MediaLibrary (×3); belongsToMany Tag |
| `Tag` | SoftDeletes | belongsToMany Blog (curated blog tags); belongsTo MediaLibrary (×2: thumbnail, static_masthead) |
| `Page` | SoftDeletes, Searchable | belongsTo MediaLibrary (×3) |
| `Country` | — | hasMany SpotGuides |
| `Recommendation` | SoftDeletes | belongsTo SpotGuide; belongsTo MediaLibrary (thumbnail) |
| `WindsurfingLocation` | — | belongsTo SpotGuide; belongsTo MediaLibrary (thumbnail) |
| `WeatherRecord` | — | belongsTo SpotGuide |
| `SailableDay` | — | belongsTo SpotGuide |

### Media FK columns
- **SpotGuide:** `thumbnail_media_id`, `static_masthead_media_id`, `og_image_media_id`, `wind_conditions_bg_media_id`, `water_conditions_bg_media_id`, `travelling_to_bg_media_id`, `lessons_and_hire_bg_media_id`, `gallery_media_ids` (JSON)
- **Blog/Page:** `thumbnail_media_id`, `static_masthead_media_id`, `og_image_media_id`, `masthead_slider_media_ids` (JSON)
- **Recommendation / WindsurfingLocation:** `thumbnail_media_id`

### Centralised Media Library
- All images are stored once in `media_library`. Models reference them by FK.
- Admin: `/admin/media-libraries` — browse, upload, delete images
- Filament field: `MediaPicker::make('field_name_media_id')` — opens a slide-over browser
- Data migration: `php artisan media:migrate-to-library` — re-parents existing Spatie records to `MediaLibrary` and populates FK columns

---

## Routes

### Web (`routes/web.php`)
| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| GET | `/` | HomepageController@index | `home` |
| GET | `/destinations` | DestinationController@index | `destinations.index` |
| GET | `/destinations/{slug}` | SpotGuideController@show | `spot-guides.show` |
| GET | `/blog` | BlogController@index | `blog.index` |
| GET | `/blog/tags` | TagController@index | `blog.tags.index` (hub — before `/blog/{slug}`) |
| GET | `/blog/tags/{slug}` | TagController@show | `blog.tags.show` |
| GET | `/blog/{slug}` | BlogController@show | `blog.show` |
| GET | `/search` | SearchController@index | `search` |
| GET | `/contact` | ContactController@index | `contact` |
| POST | `/contact` | ContactController@store | `contact.store` |
| GET | `/contributors/{slug}` | ContributorController@show | `contributors.show` (public profile; 404 unless contributor has a published guide) |
| GET | `/about-us` | — | 301 redirect → `/about` (page renamed) |
| GET | `/contributor/set-password/{user}` | Contributor/SetPasswordController@show | `contributor.password.setup` (signed) |
| POST | `/contributor/set-password/{user}` | Contributor/SetPasswordController@store | `contributor.password.store` (signed) |
| GET | `/{slug}` | PageController@show | `pages.show` (catch-all, excludes /admin*) |

### API (`routes/api.php`)
| Method | URI | Controller | Name |
|--------|-----|-----------|------|
| POST | `/api/live-weather` | Api/LiveWeatherController@fetch | `api.live-weather` |
| GET | `/api/weather-data` | Api/WeatherDataController@index | `api.weather-data.index` |
| GET | `/api/weather-data/{spotGuide}` | Api/WeatherDataController@show | `api.weather-data.show` |

---

## Inertia Pages

| Page | File | Key Props |
|------|------|-----------|
| Homepage | `Pages/Homepage.tsx` | `page`, `featuredSpotGuides[]`, `recentBlogs[]`, `meta` |
| Destinations | `Pages/Destinations/Index.tsx` | `spotGuides[]`, `sailableDays{}`, `climate{}`, `meta` |
| Spot Guide | `Pages/SpotGuide/Show.tsx` | `spotGuide` (full), `meta` |
| Blog Index | `Pages/Blog/Index.tsx` | `blogs` (paginated), `meta` |
| Blog Show | `Pages/Blog/Show.tsx` | `blog` (with content_blocks), `meta` |
| Page | `Pages/Page/Show.tsx` | `page` (with content_blocks), `meta` |
| Search | `Pages/Search.tsx` | `query`, `results[]`, `meta` |
| Contact | `Pages/Contact.tsx` | `recaptchaSiteKey`, `meta` |

---

## React Components

### Layout
- **`Layout.tsx`** — wraps all pages with NavBar, Footer, and `<Head>` (title/description/ogImage)
- **`BlockWrapper.tsx`** — section wrapper with configurable bg colour and padding

### Common
- **`NavBar.tsx`** — logo, nav links (Home, About Us, Destinations, Blog, Contact), search toggle, mobile menu
- **`Footer.tsx`** — logo, contact info, social links (YouTube, Instagram)
- **`Icon.tsx`** — FontAwesome wrapper: `<FontAwesomeIcon icon={icon} className={...} />`
- **`Button.tsx`** — primary/outline variants
- **`Card.tsx`** — image card with title, subtitle, link
- **`SpotOverview.tsx`** — pull-out sidebar (see styling section below)

### Masthead
- **`StaticMasthead.tsx`** — full-viewport-height masthead, overflow-visible for SpotOverview sidebar
- **`MastheadSlider.tsx`** — Swiper carousel masthead

### Map
- **`DestinationsMap.tsx`** — Mapbox globe map with wind-icon markers, click popups, reset button (uses `react-map-gl/mapbox`)

### Destinations
- **`DestinationFilterBar.tsx`** — sticky top-of-page filter bar (Month / Group by continent·country·global / Spots / Unit / Min wind), URL-synced via `history.replaceState`; superseded `FilterDataset.tsx` (deleted)
- **`SailableDaysChart.tsx`** — grouped bar chart, one series per selected spot, y = typical (coverage-normalised) sailable days per month, selected month marked
- **`AllDestinationsWindChart.tsx`** — Recharts LineChart of the typical-year `climate` wind curve across destinations; unit is display-only (the filter bar is now the single unit control), gust/wind toggle is local chart state
- **`AllDestinationsTempChart.tsx`** — Recharts LineChart of the typical-year `climate` temperature curve across destinations

### Content
- **`ContentBuilder.tsx`** — routes content_blocks array to specific components
- **`ContentWithBackgroundImage.tsx`** — full-height half-width panel layout
- **`RichText.tsx`** — renders HTML from RichEditor via `dangerouslySetInnerHTML` with prose styling

---

## Admin Panel (Filament)

**URL:** `/admin` | **Color:** Amber

**Auth (owner + invited contributors):** two roles on `users.role` — `owner` and `contributor` (default `contributor`). `User::canAccessPanel()` admits any recognised role; the **real gating is per-resource Policies**, not the panel gate. No public registration and no self-service password reset. The **owner** is the house account: credentials from `ADMIN_EMAIL`/`ADMIN_PASSWORD` (env) via `config/admin.php`, seeded by `AdminUserSeeder` (`updateOrCreate` with `role = owner` — re-seed to rotate); locally unset, so the dev default `seabound.souls@outlook.com` / `password` applies. Never read the `ADMIN_*` env vars outside `config/admin.php`. **Contributors** are created only by the owner-only **Invite Contributor** action (Contributors admin section), which mints a signed 7-day set-password link (`contributor.password.setup`); the contributor sets their password (`Contributor\SetPasswordController`, `session()->regenerate()` on login). Contributors are policy-scoped to their **own** spot guides and their **own** media (house media, `media_library.user_id = null`, is invisible to them); `is_published` and `is_featured` are owner-only across every write path. See the contributor workflow: `docs/history/2026-07-13-rider-contributor-workflow.md`.

**Contributor public profiles (sub-project 2):** contributors have public profile columns on `users` (`slug` from first+last, `profile_image_media_id`, `static_masthead_media_id`, `profile_blocks` JSON content-builder, `socials` JSON). A profile is public **iff** `User::hasPublicProfile()` (contributor with ≥1 published guide) — **derived, no manual flag**; owners never get one. Public page `/contributors/{slug}` (`ContributorController@show`); the spot-guide byline is a clickable author card (`SpotGuide::authorPayload()` carries `slug` + `image`). Contributors self-edit via the Filament **My Profile** page (`App\Filament\Pages\MyProfile`, record always `auth()->user()`); the owner edits via `ContributorResource` — both share `App\Filament\Forms\ContributorProfileForm::schema()`. The About page (`about`, renamed from `about-us` with a 301) hosts a `contributor_roll_up` content block that auto-lists public-profile contributors. See `docs/history/2026-07-15-contributor-profiles.md`.

### Resources
| Resource | Key Tabs |
|---------|---------|
| **SpotGuideResource** | General, Masthead & Thumbnail, Spot Overview, Introduction & Gallery, Water/Wind Conditions (each: bg image + rich editor + text_right toggle), When To Go, Windsurfing Locations (repeater), Where To Stay/Eat (repeater with image/name/description/URL/lat/lon), Travelling To, Lessons & Hire, Content Builder, SEO |
| **BlogResource** | General, Content (blocks builder), Gallery, SEO |
| **PageResource** | General, Content (blocks builder), Gallery & Images, SEO |
| **CountryResource** | name, slug, continent |
| **TagResource** (owner-only, "Blog Tags", `/admin/blog-tags`) | Curated blog-tag vocabulary: name, slug (partial-unique, reusable after soft-delete), sort_order, Images (thumbnail + masthead MediaPickers), SEO & intro. Gated by `TagPolicy` (owner-only — contributors author guides, not blogs). Assigned to posts via a `CheckboxList` on the Blog form (`blog_tag` pivot). |
| **ContributorResource** (owner-only) | Contributors roster (first/last name, email, guide count, joined) + per-contributor guides panel; **Invite Contributor** action. Built on the `User` model, scoped to `role = contributor`. |

---

## Tailwind Configuration

### Custom Colors
```
cream:           hsl(20 13% 95%)       warm off-white
cream-darker:    hsl(0 0% 85%)         mid gray
primary-lightest: hsl(169 28% 89%)     light cyan/teal
primary-lighter:  hsl(185 36% 70%)     medium cyan
primary:          hsl(192 89% 25%)     dark teal (main brand)
primary-darker:   hsl(192 89% 15%)     very dark teal
secondary:        hsl(0 1% 15%)        dark charcoal (panel bg)
orange:           hsl(11 61% 58%)      accent orange
```

### Custom Font
- `font-title` → **Knewave** (Google Font, imported in `resources/css/app.css`)
- Used for the "Seabound Souls" nav logo text

### Safelist
Dynamic classes for colours, text alignment, prose variants, and Recharts chart colours.

---

## NPM Dependencies

| Package | Purpose |
|---------|---------|
| `react@^19`, `react-dom@^19` | UI framework |
| `@inertiajs/react@^2` | Inertia SPA bridge |
| `framer-motion@^12` | Animations |
| `mapbox-gl@^3`, `react-map-gl@^8` | Interactive maps |
| `recharts@^3` | Weather charts |
| `swiper@^12` | Masthead carousel |
| `fslightbox-react@^2` | Image gallery lightbox |
| `@fortawesome/*` | Icons |
| `react-select@^5` | Dropdown selects |
| `axios@^1` | HTTP (API calls) |
| `lodash@^4` | Utilities |

---

## Composer Dependencies

| Package | Purpose |
|---------|---------|
| `laravel/framework@^12` | Core |
| `inertiajs/inertia-laravel@^2` | Inertia adapter |
| `filament/filament@^3.3` | Admin panel |
| `filament/spatie-laravel-media-library-plugin@^3.3` | Media in Filament |
| `spatie/laravel-medialibrary@^11` | Image management |
| `laravel/scout@^11` | Search |
| `spatie/laravel-responsecache@^8` | Response caching |
| `spatie/laravel-sitemap@^8` | Sitemap generation |
| `tightenco/ziggy@^2` | Laravel routes in JS |

---

## Styling Fixes (Session: 2026-03-16)

Four visual issues were identified when comparing the Laravel build against the Next.js design reference and were resolved:

### 1. Images not loading — Storage symlink

```bash
php artisan storage:link
# Creates: public/storage → storage/app/public
```

Must be run once after project setup. Without it, all Spatie Media Library image URLs (`/storage/{uuid}/{filename}`) return 404. Already done — do not re-run unless the symlink is missing.

### 2. Knewave font not loading

**File:** `resources/css/app.css`

Added before Tailwind directives:

```css
@import url('https://fonts.googleapis.com/css2?family=Knewave&display=swap');
```

The `font-title` Tailwind class is defined in `tailwind.config.ts` but the font itself was never imported.

### 3. SpotOverview — pull-out sidebar

**File:** `resources/js/Components/Common/SpotOverview.tsx`

Rewritten to match the Next.js design:
- **Mobile/tablet:** horizontal 3-col grid of icons (labels hidden)
- **Desktop (`lg`):** absolutely positioned right sidebar within StaticMasthead
  - Collapsed: `w-[5rem]`, icons centred, `bg-secondary/90`
  - Expanded: `w-[20vw]`, icons + labels visible
  - Toggle: chevron button sitting outside the left edge (`-translate-x-full`) of the sidebar; chevron rotates 180° when open
  - Smooth `transition-all duration-300`
- Icons used: `faSailboat`, `faCalendar`, `faCompass`, `faWind`, `faRocket`, `faChevronLeft`

### 4. StaticMasthead — full viewport height + overflow visible

**File:** `resources/js/Components/Masthead/StaticMasthead.tsx`

- Height: `h-[calc(100vh-5rem)]` (was `h-[50vh] md:h-[60vh] lg:h-[70vh]`)
- `overflow-hidden` → `overflow-visible` (required so SpotOverview sidebar can extend outside masthead bounds)
- Title positioning is now conditional:
  - **With `children`** (e.g. SpotOverview): `absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2` (centred)
  - **Without `children`:** `absolute bottom-0` bar at the bottom (original default)

### 5. ContentWithBackgroundImage — full-height half-width panel

**File:** `resources/js/Components/Content/ContentWithBackgroundImage.tsx`

Rewritten to match the Next.js design:
- Outer wrapper: `relative h-[calc(100vh-5rem)] w-full flex overflow-hidden`
- Background image: `absolute inset-0 w-full h-full object-cover`
- Text panel: `lg:w-1/2` of viewport, `bg-secondary/90`, scrollable via `overflow-y-auto`
- Panel floats left (`lg:mr-auto`) or right (`lg:ml-auto`) based on `textRight` prop
- Content rendered via `dangerouslySetInnerHTML` (HTML from Filament RichEditor, not Sanity PortableText)

### Helpers (`resources/js/Helpers/`)
> **Case matters:** the directory is `Helpers/` (capital H) — import as `@/Helpers/…`. macOS is case-insensitive so lowercase `@/helpers/…` works locally but breaks the case-sensitive Linux build on Laravel Cloud (`vite:load-fallback … ENOENT`). Match the tracked case for every `@/…` import.
- **`colours.ts`** — `chartColors` for single-spot charts; `getSpotGuideColours()` auto-assigns from a 16-colour palette for multi-destination charts
- **`weatherDataHelpers.ts`** — `prepareYearlyWindData()` and `prepareYearlyTempData()` transform `{ [title]: { [year]: months[] } }` into Recharts-friendly `[{ month, Title1: value, ... }]`
- **`helpers.ts`** — `formatDate()`, `truncateText()`
- **`sailableDays.ts`** — client-side sailable-days ranking: `unitToKts()`/`ktsToUnit()`/`snapToUnitOption()` (kts/mph/kph), `sailableDaysInMonth()` (coverage-normalised rate), `rankSpots()` (month-desc, peak-month → alphabetical tie-break, dataless spots kept at rank 0)
- **`destinationFilters.ts`** — `parseFilters()`/`filtersToQuery()`: URL query-string round-trip for the destinations filter bar (`spots` serialised as **slugs**, not titles)
- **`sailableChartData.ts`** — `prepareSailableChartData()` pivots ranked spots into Recharts rows for `SailableDaysChart`
- **`climate.ts`** — `prepareClimateData()` pivots the `climate` prop into Recharts rows for the wind/temp charts; exports `MONTH_NAMES`
- **`selectTypes.ts`** — shared `SelectOption` type (moved out of the now-deleted `FilterDataset.tsx`)

---

## Destinations Page Rebuild (Session: 2026-03-17)

The `/destinations` page was rebuilt to match the Next.js design. Previously it was a basic grid with dropdown continent/country filters. Now it has:

1. **StaticMasthead** — "Destinations" title with background image from first spot guide
2. **Intro text** — centred heading + description in a BlockWrapper
3. **Interactive Mapbox globe** — `DestinationsMap` component with wind-icon markers, click popups linking to spot guides, and a reset-to-global-view button
4. **Spot guides grouped by continent** — each group has a decorative heading (`gradient line — CONTINENT NAME — gradient line`) and a 3-col grid of aspect-square image cards with overlay text
5. **FilterDataset bar** — year select (single, react-select) + destination multi-select + reset button
6. **Wind comparison LineChart** — avg wind/gust toggle, kts/mph/kph unit radios, custom tooltips coloured per destination
7. **Temperature comparison LineChart** — same structure, simpler (just avgTemp)

### Backend changes
- `DestinationController` now eager-loads `weatherRecords` alongside `country` and `media`
- Passes `weatherData` prop: `{ [spotGuideTitle]: { [year]: [{ month, avgTemp, ktsWind, ktsGust, ... }] } }` — same shape as the Next.js static JSON, keyed by title (not slug) to match chart labels
- Removed `countries` prop (no longer needed — continent grouping uses `spotGuide.country.continent` directly)

### Issues faced & solutions

1. **`react-map-gl` import fails with Vite 7** — `react-map-gl@8` doesn't have a `.` entry in its `package.json` `exports` field. Vite 7's stricter module resolution rejects `import from 'react-map-gl'`. **Fix:** Import from `react-map-gl/mapbox` instead. This is the correct subpath for Mapbox-based maps in v8.

2. **Next.js used context providers for weather data** — The original fetched weather data client-side via `fetch('/data/historical-weather-data/index.json')` and distributed it via `FullHistoricalDatasetContext`. In the Laravel version, weather data is passed as an Inertia prop directly from the controller, eliminating the need for context providers or client-side fetches.

3. **Next.js used static JSON for select options** — The original loaded year/destination options from a static `select-options/index.json` file. In the Laravel version, options are derived dynamically from the `weatherData` prop (years from the keys, destinations from the titles).

4. **Colour assignment for chart lines** — The Next.js version had a hardcoded `spotGuideColours` map with 6 entries. The Laravel version uses `getSpotGuideColours()` which dynamically assigns from a 16-colour palette, so it scales to any number of destinations without manual updates.

5. **Next.js Card component vs Laravel Card** — The Next.js `Card` component accepts a `cardData` object with Sanity-specific fields (`slug.current`, `locationCoordinates`, image references). The Laravel destination cards are rendered inline as aspect-square image cards with overlay text, matching the Next.js visual output without needing to modify the shared `Card` component.

---

## Development Notes

- **Design reference:** Always compare against `../seabound-souls-sanity-next-js/src/app/components/` when implementing or adjusting components. The Laravel components are direct ports.
- **Content vs Sanity:** In the Next.js app, rich text is `PortableText`. In Laravel, it is HTML from Filament's RichEditor, rendered with `dangerouslySetInnerHTML`.
- **Images:** All media goes through Spatie Media Library. The symlink (`public/storage → storage/app/public`) must exist.
- **Icons:** FontAwesome via `@fortawesome/react-fontawesome`. Use the shared `Icon` component at `Components/Common/Icon.tsx`.
- **Fonts:** `font-title` = Knewave. Defined in `tailwind.config.ts`, imported in `app.css`.
- **Admin:** Filament at `/admin`. All content is managed there. Slug fields auto-generate from title.
- **Search:** Laravel Scout with database driver in development. Can swap to Meilisearch/Algolia for production.
- **Caching:** `spatie/laravel-responsecache` is installed. Live weather API results are cached for 1 hour in `LiveWeatherController`.
- **Mapbox:** `react-map-gl@8` must be imported as `react-map-gl/mapbox` (not `react-map-gl`) due to Vite 7's strict exports resolution. The token is shared via Inertia middleware (`usePage().props.mapboxToken`).
- **Sailable-days ranking (`/destinations`) is a GUST+sustained BLEND.** A day counts as sailable at minimum `X` iff `qualifying_gust_kts ≥ X` **AND** `qualifying_wind_kts ≥ 0.6·X` (the `SUSTAINED_FLOOR_FRACTION` in `resources/js/Helpers/sailableDays.ts`). Gust is the primary signal because Open-Meteo's sustained 10m wind under-reads thermal/venturi spots (felt wind ≈ gusts); the sustained floor rejects gusty-but-not-steady days (winter frontal storm spikes) that pure-gust wrongly rewarded — it stopped e.g. spiky Karpathos (gust/sustained ≈ 2.1) outranking steady Langebaan (≈ 1.3) midwinter. The typical sailable-days figure is a **coverage-normalised rate** (`qualifying ÷ held × daysInMonth`, robust to the rolling 3-year window's partial boundary months), computed entirely **client-side** from the pooled `sailableDays` prop (per-day `gusts[]` + `winds[]`, index-aligned) — no per-keystroke round-trip. Filter state (month/group/spots/unit/**min-temp**) is URL-synced via `history.replaceState`, with spots serialised as slugs. **Temperature never affects the wind ranking itself** (a cold-but-windy month still ranks — imposing a warmth judgement would wrongly bury legitimate cold-water spots like Brouwersdam whose season IS winter); instead the selected-month typical air temp is shown on each card (`≈ 18 windy days · 11°C`) and an **opt-in Min. temp filter** (Any default / 10 / 15 / 20 / 25 °C, `TEMP_OPTIONS`) lets a warmth-seeker exclude spots below a threshold from cards + charts — `climateTempForMonth()` in `climate.ts`, temp from the `climate` payload's `avgTemp`. See `docs/history/2026-07-28-sailable-days-ranking.md` + `docs/history/2026-07-30-destinations-temperature-filter.md`.
