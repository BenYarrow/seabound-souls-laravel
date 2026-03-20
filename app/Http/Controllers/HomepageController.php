<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\Blog;
use App\Models\MediaLibrary;
use App\Models\Page;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    use ResolvesContentBlockMedia;
    public function index(): Response
    {
        $page = Page::where('slug', 'home')
            ->where('template', 'homepage')
            ->where('is_published', true)
            ->with(['staticMastheadMedia', 'ogImageMedia'])
            ->first();

        $featuredSpotGuides = SpotGuide::published()
            ->with(['country', 'thumbnailMedia'])
            ->latest('published_at')
            ->limit(6)
            ->get()
            ->map(fn ($guide) => [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'country' => $guide->country?->name,
                'thumbnail' => $guide->thumbnailMedia?->getUrl() ?? '',
            ]);

        $recentBlogs = Blog::published()
            ->with(['thumbnailMedia'])
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn ($blog) => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'thumbnail' => $blog->thumbnailMedia?->getUrl() ?? '',
            ]);

        $mastheadSlider = [];
        if ($page) {
            $sliderIds = $page->masthead_slider_media_ids ?? [];
            if (!empty($sliderIds)) {
                $sliderItems = MediaLibrary::whereIn('id', $sliderIds)->get()->keyBy('id');
                $mastheadSlider = collect($sliderIds)
                    ->map(fn ($id) => $sliderItems->get($id))
                    ->filter()
                    ->map(fn ($m) => $m->getUrl())
                    ->values()
                    ->toArray();
            }
        }

        // Enrich infographic blocks with server-side stats
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
                'static_masthead' => $page->staticMastheadMedia?->getUrl() ?? '',
            ] : null,
            'featuredSpotGuides' => $featuredSpotGuides,
            'recentBlogs' => $recentBlogs,
            'meta' => [
                'title' => $page?->seo_title ?? 'Seabound Souls - Windsurfing Destination Guide',
                'description' => $page?->seo_description ?? 'Discover the best windsurfing destinations around the world.',
            ],
        ]);
    }
}
