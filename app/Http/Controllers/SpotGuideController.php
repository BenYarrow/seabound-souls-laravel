<?php

// Public spot-guide (destination) detail page:
//   GET /destinations/{slug} — spot-guides.show
// Assembles the full destination payload for the Inertia page: conditions,
// gallery, stay/eat recommendations, windsurfing locations, and weather history.
// All images resolve from the centralised media library and null-coalesce.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\MediaLibrary;
use App\Models\SpotGuide;
use Inertia\Inertia;
use Inertia\Response;

class SpotGuideController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render a single published spot guide by slug (404 on draft/unknown).
     * Eager-loads every relation the page needs in one pass, then reshapes:
     * recommendations are split by type (stay/eat), gallery images are ordered
     * to match the stored id list, and weather records are grouped by year and
     * sorted by month for the charts.
     *
     * @param  string  $slug  the guide's URL slug
     */
    public function show(string $slug): Response
    {
        $spotGuide = SpotGuide::where('slug', $slug)
            ->where('is_published', true)
            ->with([
                'country',
                'recommendations.thumbnailMedia',
                'windsurfingLocations.thumbnailMedia',
                'weatherRecords',
                'thumbnailMedia',
                'staticMastheadMedia',
                'ogImageMedia',
                'windConditionsBgMedia',
                'waterConditionsBgMedia',
                'travellingToBgMedia',
                'lessonsAndHireBgMedia',
            ])
            ->firstOrFail();

        // Fetch gallery images in one whereIn query, keyed by id, then map back
        // over the stored id list so the gallery keeps its authored order.
        $galleryIds = $spotGuide->gallery_media_ids ?? [];
        $galleryItems = !empty($galleryIds)
            ? MediaLibrary::whereIn('id', $galleryIds)->get()->keyBy('id')
            : collect();

        // Each gallery item becomes a full imagePayload object so CoverImage can
        // use its focal point; lightbox still reads .url from the same object.
        $gallery = collect($galleryIds)
            ->map(fn ($id) => $galleryItems->get($id))
            ->filter()
            ->map(fn ($m) => $m->imagePayload())
            ->values()
            ->toArray();

        return Inertia::render('SpotGuide/Show', [
            'spotGuide' => [
                'id' => $spotGuide->id,
                'title' => $spotGuide->title,
                'slug' => $spotGuide->slug,
                'country' => $spotGuide->country ? [
                    'name' => $spotGuide->country->name,
                    'slug' => $spotGuide->country->slug,
                    'continent' => $spotGuide->country->continent,
                ] : null,
                'latitude' => $spotGuide->latitude,
                'longitude' => $spotGuide->longitude,
                'introduction_text' => $spotGuide->introduction_text,
                'spot_overview' => $spotGuide->spot_overview,
                'water_conditions' => $spotGuide->water_conditions,
                'wind_conditions' => $spotGuide->wind_conditions,
                'when_to_go' => $spotGuide->when_to_go,
                'where_to_stay_intro' => $spotGuide->where_to_stay_intro,
                'where_to_eat_intro' => $spotGuide->where_to_eat_intro,
                'travelling_to' => $spotGuide->travelling_to,
                'lessons_and_hire' => $spotGuide->lessons_and_hire,
                'content_blocks' => $this->resolveContentBlockMedia($spotGuide->content_blocks ?? []),
                // Display images as focal-bearing objects; og_image stays a plain
                // URL string because it feeds <meta> tags, not <CoverImage>.
                'thumbnail' => $spotGuide->thumbnailMedia?->imagePayload(),
                'static_masthead' => $spotGuide->staticMastheadMedia?->imagePayload(),
                'gallery' => $gallery,
                'water_conditions_bg' => $spotGuide->waterConditionsBgMedia?->imagePayload(),
                'wind_conditions_bg' => $spotGuide->windConditionsBgMedia?->imagePayload(),
                'travelling_to_bg' => $spotGuide->travellingToBgMedia?->imagePayload(),
                'lessons_and_hire_bg' => $spotGuide->lessonsAndHireBgMedia?->imagePayload(),
                'stay_recommendations' => $spotGuide->recommendations->where('type', 'stay')->values()->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => $r->description,
                    'url' => $r->url,
                    'latitude' => $r->latitude,
                    'longitude' => $r->longitude,
                    'thumbnail' => $r->thumbnailMedia?->imagePayload(),
                ])->toArray(),
                'eat_recommendations' => $spotGuide->recommendations->where('type', 'eat')->values()->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => $r->description,
                    'url' => $r->url,
                    'latitude' => $r->latitude,
                    'longitude' => $r->longitude,
                    'thumbnail' => $r->thumbnailMedia?->imagePayload(),
                ])->toArray(),
                'windsurfing_locations' => $spotGuide->windsurfingLocations->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'description' => $l->description,
                    'latitude' => $l->latitude,
                    'longitude' => $l->longitude,
                    'thumbnail' => $l->thumbnailMedia?->imagePayload(),
                ])->toArray(),
                'weather_records' => $spotGuide->weatherRecords
                    ->groupBy('year')
                    ->map(fn ($yearRecords) => $yearRecords->sortBy('month')->values()->map(fn ($r) => [
                        'month' => $r->month_name,
                        'avg_temp' => $r->avg_temp,
                        'kts_wind' => $r->kts_wind,
                        'kts_gust' => $r->kts_gust,
                        'mph_wind' => $r->mph_wind,
                        'mph_gust' => $r->mph_gust,
                        'kph_wind' => $r->kph_wind,
                        'kph_gust' => $r->kph_gust,
                    ])->toArray())
                    ->toArray(),
            ],
            'meta' => [
                'title' => $spotGuide->seo_title ?? $spotGuide->title,
                'description' => $spotGuide->seo_description ?? '',
                'keywords' => $spotGuide->seo_keywords ?? [],
                'og_image' => $spotGuide->ogImageMedia?->getUrl() ?: ($spotGuide->thumbnailMedia?->getUrl() ?? ''),
            ],
        ]);
    }

}
