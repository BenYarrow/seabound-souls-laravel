<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\Blog;
use App\Models\MediaLibrary;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    use ResolvesContentBlockMedia;
    public function index(): Response
    {
        $blogs = Blog::published()
            ->with(['thumbnailMedia'])
            ->latest('published_at')
            ->paginate(12)
            ->through(fn ($blog) => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'published_at' => $blog->published_at?->toDateString(),
                'thumbnail' => $blog->thumbnailMedia?->getUrl() ?? '',
                'seo_description' => $blog->seo_description,
            ]);

        return Inertia::render('Blog/Index', [
            'blogs' => $blogs,
            'meta' => [
                'title' => 'Blog | Seabound Souls',
                'description' => 'Windsurfing tips, guides and destination insights.',
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $blog = Blog::where('slug', $slug)
            ->where('is_published', true)
            ->with(['thumbnailMedia', 'staticMastheadMedia', 'ogImageMedia'])
            ->firstOrFail();

        $sliderIds = $blog->masthead_slider_media_ids ?? [];
        $sliderItems = !empty($sliderIds)
            ? MediaLibrary::whereIn('id', $sliderIds)->get()->keyBy('id')
            : collect();

        $mastheadSlider = collect($sliderIds)
            ->map(fn ($id) => $sliderItems->get($id))
            ->filter()
            ->map(fn ($m) => $m->getUrl())
            ->values()
            ->toArray();

        return Inertia::render('Blog/Show', [
            'blog' => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'content_blocks' => $this->resolveContentBlockMedia($blog->content_blocks ?? []),
                'published_at' => $blog->published_at?->toDateString(),
                'thumbnail' => $blog->thumbnailMedia?->getUrl() ?? '',
                'static_masthead' => $blog->staticMastheadMedia?->getUrl() ?? '',
                'masthead_slider' => $mastheadSlider,
            ],
            'meta' => [
                'title' => $blog->seo_title ?? $blog->title,
                'description' => $blog->seo_description ?? '',
                'og_image' => $blog->ogImageMedia?->getUrl() ?: ($blog->thumbnailMedia?->getUrl() ?? ''),
            ],
        ]);
    }
}
