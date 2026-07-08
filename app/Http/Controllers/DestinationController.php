<?php

// Public destinations index:
//   GET /destinations — destinations.index
// Lists published spot guides (grouped by continent on the front end) and builds
// the weatherData map the comparison charts consume.

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SpotGuide;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    /**
     * Render the destinations index. Returns two props built from one query:
     * `spotGuides` (cards, title-ordered) and `weatherData` — a map keyed by
     * guide title, each value grouped by year and sorted by month, matching the
     * shape the wind/temperature comparison charts expect (keyed by the same
     * title the chart legend uses).
     */
    public function index(): Response
    {
        // Masthead comes from a published "destinations" landing Page (like the
        // blog/search/home indexes). The front end falls back to the first guide's
        // thumbnail when no such page exists yet.
        $page = Page::where('slug', 'destinations')
            ->where('is_published', true)
            ->with('staticMastheadMedia')
            ->first();

        $spotGuides = SpotGuide::published()
            ->with(['country', 'thumbnailMedia', 'weatherRecords'])
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
        ]);

        // Keyed by title (not slug) so the chart legend/series labels line up.
        $weatherData = $spotGuides->mapWithKeys(fn ($guide) => [
            $guide->title => $guide->weatherRecords
                ->groupBy('year')
                ->map(fn ($yearRecords) => $yearRecords->sortBy('month')->values()->map(fn ($r) => [
                    'month' => $r->month_name,
                    'avgTemp' => (float) $r->avg_temp,
                    'ktsWind' => (float) $r->kts_wind,
                    'ktsGust' => (float) $r->kts_gust,
                    'mphWind' => $r->mph_wind,
                    'mphGust' => $r->mph_gust,
                    'kphWind' => $r->kph_wind,
                    'kphGust' => $r->kph_gust,
                ])->toArray())
                ->toArray(),
        ])->toArray();

        return Inertia::render('Destinations/Index', [
            'spotGuides' => $spotGuidesData,
            'weatherData' => $weatherData,
            'static_masthead' => $page?->staticMastheadMedia?->imagePayload(),
            'meta' => [
                'title' => 'Destinations | Seabound Souls',
                'description' => 'Explore windsurfing destinations around the world.',
            ],
        ]);
    }
}
