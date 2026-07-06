<?php

// Catch-all generic page route:
//   GET /{slug} — pages.show (registered last; excludes /admin*)
// Renders a published Page's content builder — used for About, etc.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\MediaLibrary;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render a published page by slug (404 on draft/unknown). Resolves content-
     * block media to URLs and re-orders the masthead slider to the stored id list.
     *
     * @param  string  $slug  the page's URL slug
     */
    public function show(string $slug): Response
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->with(['staticMastheadMedia', 'ogImageMedia'])
            ->firstOrFail();

        $sliderIds = $page->masthead_slider_media_ids ?? [];
        $sliderItems = !empty($sliderIds)
            ? MediaLibrary::whereIn('id', $sliderIds)->get()->keyBy('id')
            : collect();

        $mastheadSlider = collect($sliderIds)
            ->map(fn ($id) => $sliderItems->get($id))
            ->filter()
            ->map(fn ($m) => $m->getUrl())
            ->values()
            ->toArray();

        return Inertia::render('Page/Show', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'template' => $page->template,
                'content_blocks' => $this->resolveContentBlockMedia($page->content_blocks ?? []),
                'static_masthead' => $page->staticMastheadMedia?->getUrl() ?? '',
                'masthead_slider' => $mastheadSlider,
            ],
            'meta' => [
                'title' => $page->seo_title ?? $page->title,
                'description' => $page->seo_description ?? '',
                'og_image' => $page->ogImageMedia?->getUrl() ?? '',
            ],
        ]);
    }
}
