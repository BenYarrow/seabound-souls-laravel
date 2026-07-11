# Related Spot Guides Slider — Design

**Date:** 2026-07-11
**Status:** Approved (design), pending spec review

## Goal

Add a "related spot guides" slider to the bottom of the spot-guide template so a
visitor reading one destination can discover others. The primary relation is
**other spots in the same country** (e.g. every Greek spot guide links to the
other Greek spot guides), with a fallback to the same continent, then hidden.

This is a port of the original Next.js `RelatedSpotGuides` concept, adapted to
the Laravel/Inertia stack and the site's existing card / slider patterns.

## Behaviour

### Which guides are shown (cascade)

1. **Same country** — other *published* spot guides whose `country_id` matches
   the current guide's, excluding the current guide itself. If any exist, show
   these.
2. **Same continent** (fallback) — if there are no same-country siblings, show
   other *published* spot guides whose country's `continent` matches the current
   guide's, excluding the current guide. Fall back to these.
3. **Hidden** — if neither set yields any guides, the section does not render.

Drafts (`is_published = false`) and the current guide are always excluded.

### Ordering

Whichever set is shown is ordered identically to the destinations index page:

1. The **featured** guide first (the guide with `is_featured = true`), if one is
   present in the set.
2. Remaining guides by **gustiest this month**: the current-year, current-month
   `kts_gust` reading, descending. Guides with no reading for the current
   year+month sort last; ties break alphabetically by title.

This is exactly the comparator currently living inline in
`DestinationController::index`. To keep one source of truth, that logic is
extracted (see "Shared sorter" below) and reused here.

### Heading

The section heading reflects which set is shown:

- Country matches → **"More Spots in {Country}"** (e.g. "More Spots in Greece").
- Continent fallback → **"More Spots in {Continent}"** with the continent
  rendered as a human label (e.g. `europe` → "Europe", `north-america` →
  "North America").

### Placement

- Rendered as the final section of the spot-guide page in
  `resources/js/Pages/SpotGuide/Show.tsx`, after the "Lessons & Hire" block.
- Styled to match the existing alternating section rhythm (uses the same
  `SectionHeading` treatment and a `bg-cream`/`bg-white` background consistent
  with neighbouring sections). No dark-token work is introduced here — the page
  is not yet on the semantic token layer (a separate standing follow-up); this
  section matches its siblings.
- **Not** added to the sticky quick-nav (`SpotGuideNav`). It reads as a closing
  "explore more" element rather than a first-class page section.

## Backend

### Shared sorter

Extract the "gustiest this month" comparator into a single reusable place on the
`SpotGuide` model:

```php
/**
 * Sort a collection of spot guides "gustiest first" for the current month,
 * using this year's reading. Guides with no current year+month reading sort
 * last; ties break alphabetically by title. Read per request so ranking
 * re-orders as the month turns. Requires weatherRecords to be loaded.
 */
public static function sortByGustiestThisMonth(Collection $guides): Collection
```

- Moves the existing comparator out of `DestinationController::index` verbatim
  (current-year, current-month, `kts_gust`, nulls last, `strcmp` tie-break).
- `DestinationController::index` is refactored to call this method, so the
  destinations page and the related slider share one implementation.
- Uses `now()` for the current year/month (same as today).

### SpotGuideController::show

After the existing guide load, build the related set:

1. Query same-country published siblings (exclude current id), eager-loading
   `country`, `thumbnailMedia`, `weatherRecords`.
2. If empty, query same-continent published guides (exclude current id) with the
   same eager loads.
3. Determine `relation` = `'country'` | `'continent'` | `null` (null when both
   empty), and a `relation_label` (the country name or the humanised continent).
4. Sort the chosen set via `SpotGuide::sortByGustiestThisMonth()`.
5. Map each guide to a card payload and pass as an Inertia prop.

New prop shape:

```php
'related_spot_guides' => [
    'relation'  => 'country' | 'continent' | null,
    'label'     => 'Greece' | 'Europe' | null,   // for the heading
    'guides'    => [
        [
            'id'            => int,
            'title'         => string,
            'slug'          => string,
            'country'       => ['name' => string] | null,
            'thumbnail'     => FocalImage | null,
            'intro_snippet' => string,   // introduction_text, tags stripped + truncated
            'overview'      => [         // a couple of spot_overview badges
                'wind_conditions' => string | null,
                'best_direction'  => string | null,
            ],
        ],
        // ...
    ],
]
```

- `intro_snippet`: strip HTML tags from `introduction_text` and truncate (reuse
  the existing truncation approach; ~140 chars). Empty string when no intro.
- `overview`: pulled from the guide's `spot_overview` JSON. Both keys optional;
  the card renders whichever badges are present. (Exact keys confirmed against
  the `spot_overview` shape during implementation — `wind_conditions` and
  `best_direction` per the schema in CLAUDE.md.)

## Frontend

### New component: `resources/js/Components/SpotGuide/RelatedSpotGuides.tsx`

Props: `{ relation, label, guides }` (the payload above).

- Returns `null` when `guides` is empty (defensive; the section wrapper in
  `Show.tsx` also guards).
- Renders the `SectionHeading` "More Spots in {label}".
- Swiper slider (import from `swiper/react`), **always one slide per view**
  (`slidesPerView: 1`), with `Navigation` + `Pagination` modules — prev/next
  arrows and clickable pagination dots below the slide.
- Each slide is a **full-bleed image card** (chosen layout, "Option B"):
  - focal-aware `CoverImage` filling the card (`h-[440px] md:h-[520px]`,
    `rounded-2xl`) over a dark bottom gradient for legibility
  - overlaid bottom-left: country eyebrow, Knewave (`font-title`) title,
    intro snippet (line-clamped), up to two translucent `spot_overview`
    badges, and an "Explore →" affordance
  - entire card links to `/destinations/{slug}` via Inertia `<Link>`; the
    arrows/dots sit outside the link so they never navigate
- Pagination dots use scoped styles in `resources/css/app.css`
  (`#related-spot-guides .related-bullet` / `.related-bullet-active`),
  mirroring the gallery bullet pattern.

### Show.tsx

- Accept the `related_spot_guides` prop in `Props`.
- Render `<RelatedSpotGuides ... />` as the final section, guarded by
  `related_spot_guides.guides.length > 0`.

## Testing (TDD)

### Unit — shared sorter (`SpotGuide::sortByGustiestThisMonth`)

- Orders by current year+month `kts_gust` descending.
- Guides with no current-month reading sort last.
- Ties (equal gust, or both null) break alphabetically by title.

### Feature — `SpotGuideController::show` related payload

- Includes other published guides in the **same country**; excludes the current
  guide and drafts.
- Falls back to **same-continent** guides when the country has no siblings.
- Returns `relation = null` and an empty `guides` array when neither country nor
  continent yields others (the single-guide case).
- `relation`/`label` reflect which set was chosen.
- Ordering: a featured guide leads; the remainder follow gustiest-this-month.
- Card payload contains the expected fields (title, slug, thumbnail, snippet,
  overview badges).

### Regression

- `DestinationController::index` still orders guides gustiest-first after the
  comparator is extracted (existing behaviour preserved — add/confirm coverage).

External services (OpenWeatherMap etc.) are not touched; tests seed
`weather_records` directly and run on in-memory SQLite.

## Out of scope

- Dark-mode / semantic token work (standing separate follow-up).
- Adding the section to the sticky quick-nav.
- Any change to how weather data is fetched or stored.
- A cross-country "you might also like" recommendation engine — relation is
  strictly country → continent → hidden.
