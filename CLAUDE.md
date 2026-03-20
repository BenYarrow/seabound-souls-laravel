# Seabound Souls — Laravel/Inertia Project

## Project Overview

A full rebuild of the **Seabound Souls** windsurfing destination guide website. The original was built with **Next.js 15 + Sanity CMS**. This project is a complete port to **Laravel 12 + Inertia.js + React + Filament admin**. The design reference and component logic should always be compared against the original at `../seabound-souls-sanity-next-js/`.

**What the site is:** A windsurfing destination guide featuring spot guides with maps, weather data, recommendations, galleries, a blog, and contact form.

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
- `is_published` (bool), `published_at`, soft deletes

**`blogs`**
- `title`, `slug`, `content_blocks` (JSON), SEO fields, `is_published`, soft deletes

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

**`media_library`** — centralised image library; each row is one image, referenced by FK from other tables

**`media`** — Spatie Media Library managed table (now attached only to `MediaLibrary` model, collection `file`)

---

## Models & Relationships

| Model | Traits | Relationships |
|-------|--------|---------------|
| `MediaLibrary` | HasMedia | — (owns Spatie `file` collection) |
| `SpotGuide` | SoftDeletes, Searchable | belongsTo Country; hasMany Recommendations, WindsurfingLocations, WeatherRecords; belongsTo MediaLibrary (×8) |
| `Blog` | SoftDeletes, Searchable | belongsTo MediaLibrary (×3) |
| `Page` | SoftDeletes, Searchable | belongsTo MediaLibrary (×3) |
| `Country` | — | hasMany SpotGuides |
| `Recommendation` | SoftDeletes | belongsTo SpotGuide; belongsTo MediaLibrary (thumbnail) |
| `WindsurfingLocation` | — | belongsTo SpotGuide; belongsTo MediaLibrary (thumbnail) |
| `WeatherRecord` | — | belongsTo SpotGuide |

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
| GET | `/blog/{slug}` | BlogController@show | `blog.show` |
| GET | `/search` | SearchController@index | `search` |
| GET | `/contact` | ContactController@index | `contact` |
| POST | `/contact` | ContactController@store | `contact.store` |
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
| Destinations | `Pages/Destinations/Index.tsx` | `spotGuides[]`, `weatherData{}`, `meta` |
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
- **`FilterDataset.tsx`** — year select + destination multi-select (react-select) + reset button in `bg-primary-lighter` bar
- **`AllDestinationsWindChart.tsx`** — Recharts LineChart comparing wind speeds across destinations, with avg wind/gust toggle and kts/mph/kph unit selector
- **`AllDestinationsTempChart.tsx`** — Recharts LineChart comparing temperatures across destinations

### Content
- **`ContentBuilder.tsx`** — routes content_blocks array to specific components
- **`ContentWithBackgroundImage.tsx`** — full-height half-width panel layout
- **`RichText.tsx`** — renders HTML from RichEditor via `dangerouslySetInnerHTML` with prose styling

---

## Admin Panel (Filament)

**URL:** `/admin` | **Color:** Amber

### Resources
| Resource | Key Tabs |
|---------|---------|
| **SpotGuideResource** | General, Masthead & Thumbnail, Spot Overview, Introduction & Gallery, Water/Wind Conditions (each: bg image + rich editor + text_right toggle), When To Go, Windsurfing Locations (repeater), Where To Stay/Eat (repeater with image/name/description/URL/lat/lon), Travelling To, Lessons & Hire, Content Builder, SEO |
| **BlogResource** | General, Content (blocks builder), Gallery, SEO |
| **PageResource** | General, Content (blocks builder), Gallery & Images, SEO |
| **CountryResource** | name, slug, continent |

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

### Helpers (`resources/js/helpers/`)
- **`colours.ts`** — `chartColors` for single-spot charts; `getSpotGuideColours()` auto-assigns from a 16-colour palette for multi-destination charts
- **`weatherDataHelpers.ts`** — `prepareYearlyWindData()` and `prepareYearlyTempData()` transform `{ [title]: { [year]: months[] } }` into Recharts-friendly `[{ month, Title1: value, ... }]`
- **`helpers.ts`** — `formatDate()`, `truncateText()`

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
