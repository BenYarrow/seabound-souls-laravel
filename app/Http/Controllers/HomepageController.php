<?php

// Homepage:
//   GET / — home
// Renders the "home" Page's content builder. Featured spot guides / recent
// blogs are content-managed via list-content blocks on that page, not
// hardcoded here. Infographic blocks are enriched server-side with live counts.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\MediaLibrary;
use App\Models\Page;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render the homepage. The "home" Page record is optional — the page still
     * renders with sensible meta defaults if it's absent. Featured spot guides
     * and recent blogs are content-managed via list-content blocks in the
     * page's content builder, not queried here. Only when the content builder
     * contains an infographic block does this enrich it with live published
     * counts of continents/countries/spots/hotels/restaurants.
     */
    public function index(): Response
    {
        $page = Page::where('slug', 'home')
            ->where('template', 'homepage')
            ->where('is_published', true)
            ->with(['staticMastheadMedia', 'ogImageMedia'])
            ->first();

        $mastheadSlider = [];
        if ($page) {
            $sliderIds = $page->masthead_slider_media_ids ?? [];
            if (!empty($sliderIds)) {
                $sliderItems = MediaLibrary::whereIn('id', $sliderIds)->get()->keyBy('id');
                // Each slider item becomes a focal-bearing object for MastheadSlider/CoverImage.
                $mastheadSlider = collect($sliderIds)
                    ->map(fn ($id) => $sliderItems->get($id))
                    ->filter()
                    ->map(fn ($m) => $m->imagePayload())
                    ->values()
                    ->toArray();
            }
        }

        // Enrich infographic blocks with server-side stats. Only runs the extra
        // count queries when the page actually contains an infographic block.
        $contentBlocks = $page ? $page->content_blocks : [];
        if (is_array($contentBlocks)) {
            $hasInfographic = collect($contentBlocks)->contains(fn ($block) => ($block['type'] ?? '') === 'infographic');
            if ($hasInfographic) {
                $publishedGuideIds = SpotGuide::published()->pluck('id');
                $infographicStats = [
                    'continents' => SpotGuide::published()
                        ->join('countries', 'spot_guides.country_id', '=', 'countries.id')
                        ->distinct('countries.continent')
                        ->count('countries.continent'),
                    'countries' => SpotGuide::published()->distinct('country_id')->count('country_id'),
                    'spots' => $publishedGuideIds->count(),
                    'hotels' => Recommendation::whereIn('spot_guide_id', $publishedGuideIds)->where('type', 'stay')->count(),
                    'restaurants' => Recommendation::whereIn('spot_guide_id', $publishedGuideIds)->where('type', 'eat')->count(),
                ];

                $contentBlocks = collect($contentBlocks)->map(function ($block) use ($infographicStats) {
                    if (($block['type'] ?? '') === 'infographic') {
                        $block['data']['stats'] = $infographicStats;
                    }
                    return $block;
                })->toArray();
            }
        }

        return Inertia::render('Homepage', [
            'page' => $page ? [
                'title' => $page->title,
                'content_blocks' => $this->resolveContentBlockMedia($contentBlocks),
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'masthead_slider' => $mastheadSlider,
                // Display image as focal-bearing object for CoverImage in StaticMasthead.
                'static_masthead' => $page->staticMastheadMedia?->imagePayload(),
            ] : null,
            'meta' => [
                'title' => $page?->seo_title ?: 'Windsurfing Destination Guide',
                'description' => $page?->seo_description ?: 'Discover the best windsurfing destinations around the world.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
        ]);
    }
}
