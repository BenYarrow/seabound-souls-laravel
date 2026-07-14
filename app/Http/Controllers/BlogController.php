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
use App\Models\Tag;
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
            ->with(['staticMastheadMedia', 'ogImageMedia'])
            ->first();

        // The featured post is an explicit, owner-set choice — no fallback to
        // "latest". Null when nothing is flagged (or the flagged post is a draft),
        // in which case the index shows no hero. Excluded from the grid so it
        // only ever appears once, as the hero.
        $featured = Blog::published()
            ->where('is_featured', true)
            ->with(['thumbnailMedia'])
            ->first();

        $blogs = Blog::published()
            ->when($featured, fn ($query) => $query->whereKeyNot($featured->id))
            ->with(['thumbnailMedia'])
            ->latest('published_at')
            ->paginate(12)
            ->through(fn ($blog) => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'published_at' => $blog->published_at?->toDateString(),
                // Focal-bearing object so listing cards can honour the focal point.
                'thumbnail' => $blog->thumbnailMedia?->imagePayload(),
                'seo_description' => $blog->seo_description,
            ]);

        // Only tags that have at least one published post appear in the bar, so
        // every chip links to a live tag page (never a 404). Ordered by sort_order.
        $tags = Tag::withPublishedPosts()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug]);

        return Inertia::render('Blog/Index', [
            'blogs' => $blogs,
            'featured' => $featured ? [
                'id' => $featured->id,
                'title' => $featured->title,
                'slug' => $featured->slug,
                'published_at' => $featured->published_at?->toDateString(),
                'thumbnail' => $featured->thumbnailMedia?->imagePayload(),
                'seo_description' => $featured->seo_description,
            ] : null,
            // Display images as objects; the static_masthead feeds StaticMasthead which uses CoverImage.
            'static_masthead' => $page?->staticMastheadMedia?->imagePayload(),
            'tags' => $tags,
            'meta' => [
                'title' => $page?->seo_title ?: 'Blog',
                'description' => $page?->seo_description ?: 'Windsurfing tips, guides and destination insights.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
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
            ->with(['thumbnailMedia', 'staticMastheadMedia', 'ogImageMedia', 'tags'])
            ->firstOrFail();

        // Fetch all slider images in a single whereIn query, keyed by id, then
        // map back over $sliderIds so the gallery keeps its authored order.
        $sliderIds = $blog->masthead_slider_media_ids ?? [];
        $sliderItems = !empty($sliderIds)
            ? MediaLibrary::whereIn('id', $sliderIds)->get()->keyBy('id')
            : collect();

        // Each slider item becomes a focal-bearing object for MastheadSlider/CoverImage.
        $mastheadSlider = collect($sliderIds)
            ->map(fn ($id) => $sliderItems->get($id))
            ->filter()
            ->map(fn ($m) => $m->imagePayload())
            ->values()
            ->toArray();

        return Inertia::render('Blog/Show', [
            'blog' => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'tags' => $blog->tags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->values(),
                'content_blocks' => $this->resolveContentBlockMedia($blog->content_blocks ?? []),
                'published_at' => $blog->published_at?->toDateString(),
                // Display images as focal-bearing objects; og_image stays a plain URL string.
                'thumbnail' => $blog->thumbnailMedia?->imagePayload(),
                'static_masthead' => $blog->staticMastheadMedia?->imagePayload(),
                'masthead_slider' => $mastheadSlider,
            ],
            'meta' => [
                'title' => $blog->seo_title ?? $blog->title,
                'description' => $blog->seo_description ?? '',
                'keywords' => $blog->seo_keywords ?? [],
                'og_image' => $blog->ogImageMedia?->getUrl() ?: ($blog->thumbnailMedia?->getUrl() ?? ''),
            ],
        ]);
    }
}
