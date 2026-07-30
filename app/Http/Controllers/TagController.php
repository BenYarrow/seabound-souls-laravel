<?php

// Public tag surfaces:
//   GET /blog/tags        — the hub: a grid of every tag with published posts
//   GET /blog/tags/{slug} — a single tag's page (its posts as a crawlable hub)
// Both resolve tags via withPublishedPosts(), so unknown slugs and empty/draft-only
// tags 404 or are omitted (no thin indexable pages). Each tag can carry a card
// thumbnail (hub) and a masthead image (tag page); both fall back to a designed
// gradient hero on the front end when unset. SEO meta uses the tag's overrides
// when set, else a sensible auto-generated fallback.

namespace App\Http\Controllers;

use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Render the tag hub: a card grid of every tag that has published posts,
     * ordered by sort_order, each with its thumbnail and published-post count.
     * Always resolves (even with no tags) so /blog/tags never 404s the bare path.
     */
    public function index(): Response
    {
        $tags = Tag::withPublishedPosts()
            ->withCount(['blogs as posts_count' => fn ($query) => $query->where('is_published', true)])
            ->with('thumbnailMedia')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description,
                'posts_count' => $tag->posts_count,
                'thumbnail' => $tag->thumbnailMedia?->imagePayload(),
            ]);

        return Inertia::render('Blog/Tags', [
            'tags' => $tags,
            'meta' => [
                'title' => 'Topics — Seabound Sessions',
                'description' => 'Browse windsurfing articles and guides by topic.',
                'keywords' => [],
                'og_image' => '',
            ],
        ]);
    }

    /**
     * Render a single tag's page: its optional masthead + intro copy plus a
     * paginated grid of its published posts (newest first, 12/page), 404ing on
     * unknown or empty tags.
     *
     * @param  string  $slug  the tag's URL slug
     */
    public function show(string $slug): Response
    {
        $tag = Tag::withPublishedPosts()->with('staticMastheadMedia')->where('slug', $slug)->firstOrFail();

        $posts = $tag->publishedBlogs()
            ->with('thumbnailMedia')
            ->latest('published_at')
            ->paginate(12)
            ->through(fn ($blog) => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'published_at' => $blog->published_at?->toDateString(),
                'thumbnail' => $blog->thumbnailMedia?->imagePayload(),
                'seo_description' => $blog->seo_description,
            ]);

        return Inertia::render('Blog/Tag', [
            'tag' => [
                'name' => $tag->name,
                'description' => $tag->description,
            ],
            // Focal-bearing hero image, or null → front end renders the gradient fallback.
            'static_masthead' => $tag->staticMastheadMedia?->imagePayload(),
            'posts' => $posts,
            'meta' => [
                'title' => $tag->seo_title ?: "Posts tagged {$tag->name} — Seabound Sessions",
                'description' => $tag->seo_description
                    ?: "Windsurfing articles and guides tagged {$tag->name}.",
                'keywords' => [],
                'og_image' => '',
            ],
        ]);
    }
}
