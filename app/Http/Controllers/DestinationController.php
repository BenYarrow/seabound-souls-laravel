<?php

// Public destinations index:
//   GET /destinations — destinations.index
// Lists published spot guides (grouped by continent on the front end) and builds
// the sailableDays + climate maps the ranking and comparison charts consume.

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SpotGuide;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    /**
     * Render the destinations index. Returns props built from one query:
     * `spotGuides` (cards, title-ordered), `sailableDays` — pooled daily
     * qualifying-wind values keyed by guide title then month (1-12), for the
     * client to rank by coverage-normalised sailable-day rate — and `climate`
     * — cross-year-averaged monthly conditions keyed by guide title, matching
     * the shape the wind/temperature comparison charts expect (keyed by the
     * same title the chart legend uses).
     */
    public function index(): Response
    {
        // Masthead comes from a published "destinations" landing Page (like the
        // blog/search/home indexes). The front end falls back to the first guide's
        // thumbnail when no such page exists yet.
        $page = Page::where('slug', 'destinations')
            ->where('is_published', true)
            ->with(['staticMastheadMedia', 'ogImageMedia'])
            ->first();

        $spotGuides = SpotGuide::published()
            ->with(['country', 'thumbnailMedia', 'weatherRecords', 'sailableDays', 'author.profileImageMedia'])
            ->orderBy('title')
            ->get();

        $spotGuidesData = $spotGuides->map(fn ($guide) => [
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'latitude' => $guide->latitude,
            'longitude' => $guide->longitude,
            'country' => $guide->country ? [
                'name' => $guide->country->name,
                'slug' => $guide->country->slug,
                'continent' => $guide->country->continent,
            ] : null,
            // Focal-bearing object so the card's CoverImage can honour the focal point.
            'thumbnail' => $guide->thumbnailMedia?->imagePayload(),
            // Who wrote it — house vs a named contributor (shown only when showProvenance).
            'author' => $guide->authorPayload(),
        ]);

        // The featured guide is an explicit, owner-set choice (no fallback). Null
        // when nothing is flagged or the flagged guide is a draft. It is NOT
        // removed from $spotGuides — the hero is a spotlight, the grids remain the
        // complete directory.
        $featured = SpotGuide::published()
            ->where('is_featured', true)
            ->with(['country', 'thumbnailMedia'])
            ->first();

        // Pooled daily sailable-wind values, keyed by title then month (1-12).
        // The browser counts values >= minimum, divides by the held-day count and
        // scales by the month's length to get the typical (climatological) number
        // of sailable days that month — a coverage-normalised rate that is robust
        // to the rolling window's partial boundary months (so no `years` field is
        // shipped; per-year division would undercount the boundary months).
        $sailableDays = $spotGuides->mapWithKeys(fn ($guide) => [
            $guide->title => $guide->sailableDays
                ->groupBy('month')
                ->map(fn ($monthDays) => [
                    'values' => $monthDays->map(fn ($day) => (float) $day->qualifying_wind_kts)->values()->toArray(),
                ])
                ->toArray(),
        ])->toArray();

        // "Typical year" climate: monthly averages collapsed across all held years,
        // keyed by title (matching the chart legend labels), sorted by month.
        $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
        $average = fn ($collection, string $column) => round($collection->avg($column), 1);
        $climate = $spotGuides->mapWithKeys(fn ($guide) => [
            $guide->title => $guide->weatherRecords
                ->groupBy('month')
                ->sortKeys()
                ->map(fn ($monthRecords, $monthNumber) => [
                    'month' => $monthNames[(int) $monthNumber] ?? '',
                    'avgTemp' => $average($monthRecords, 'avg_temp'),
                    'ktsWind' => $average($monthRecords, 'kts_wind'),
                    'ktsGust' => $average($monthRecords, 'kts_gust'),
                    'mphWind' => (int) round($monthRecords->avg('mph_wind')),
                    'mphGust' => (int) round($monthRecords->avg('mph_gust')),
                    'kphWind' => (int) round($monthRecords->avg('kph_wind')),
                    'kphGust' => (int) round($monthRecords->avg('kph_gust')),
                ])
                ->values()
                ->toArray(),
        ])->toArray();

        return Inertia::render('Destinations/Index', [
            'spotGuides' => $spotGuidesData,
            'featuredSpotGuide' => $featured ? [
                'id' => $featured->id,
                'title' => $featured->title,
                'slug' => $featured->slug,
                'country' => $featured->country?->name,
                'thumbnail' => $featured->thumbnailMedia?->imagePayload(),
            ] : null,
            'sailableDays' => $sailableDays,
            'climate' => $climate,
            // Show provenance bylines only once a published contributor guide exists.
            'showProvenance' => SpotGuide::contributorGuidesExist(),
            'static_masthead' => $page?->staticMastheadMedia?->imagePayload(),
            'meta' => [
                'title' => $page?->seo_title ?: 'Destinations',
                'description' => $page?->seo_description ?: 'Explore windsurfing destinations around the world.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
        ]);
    }
}
