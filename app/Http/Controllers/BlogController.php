<?php

// Public-facing blog routes:
//   GET /blog        — paginated listing of published posts (blog.index)
//   GET /blog/{slug} — a single published post (blog.show)
// Both render Inertia pages and reference images from the centralised media
// library by FK; media getters null-coalesce so posts without images still render.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\Blog;
use App\Models\MediaLibrary;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render the blog index: published posts newest-first, 12 per page, each
     * projected down to the fields the listing card needs. The masthead image
     * comes from the "blog" landing Page (if one is published).
     */
    public function index(): Response
    {
        $page = Page::where('slug', 'blog')
            ->where('is_published', true)
            ->with('staticMastheadMedia')
            ->first();

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
            'static_masthead' => $page?->staticMastheadMedia?->getUrl() ?? '',
            'meta' => [
                'title' => 'Blog | Seabound Souls',
                'description' => 'Windsurfing tips, guides and destination insights.',
            ],
        ]);
    }

    /**
     * Render a single published post by slug, 404ing on drafts or unknown slugs.
     * Content-block media is resolved to URLs, and the masthead slider images are
     * fetched in one query then re-ordered to match the stored id order.
     *
     * @param  string  $slug  the post's URL slug
     */
    public function show(string $slug): Response
    {
        $blog = Blog::where('slug', $slug)
            ->where('is_published', true)
            ->with(['thumbnailMedia', 'staticMastheadMedia', 'ogImageMedia'])
            ->firstOrFail();

        // Fetch all slider images in a single whereIn query, keyed by id, then
        // map back over $sliderIds so the gallery keeps its authored order.
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
