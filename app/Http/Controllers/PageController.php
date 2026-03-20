<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\MediaLibrary;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use ResolvesContentBlockMedia;
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
