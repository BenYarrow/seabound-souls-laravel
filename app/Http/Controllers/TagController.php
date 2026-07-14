<?php

// Public tag page: GET /blog/tags/{slug}. Renders the posts carrying a given tag
// as a crawlable topic hub. Resolves the tag among those WITH published posts, so
// unknown slugs and empty/draft-only tags 404 (no thin indexable pages). SEO meta
// uses the tag's overrides when set, else a sensible auto-generated fallback.

namespace App\Http\Controllers;

use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Render a single tag's page: its intro copy plus a paginated grid of its
     * published posts (newest first, 12/page), 404ing on unknown or empty tags.
     *
     * @param  string  $slug  the tag's URL slug
     */
    public function show(string $slug): Response
    {
        $tag = Tag::withPublishedPosts()->where('slug', $slug)->firstOrFail();

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
            'posts' => $posts,
            'meta' => [
                'title' => $tag->seo_title ?: "Posts tagged {$tag->name} — Seabound Souls",
                'description' => $tag->seo_description
                    ?: "Windsurfing articles and guides tagged {$tag->name}.",
                'keywords' => [],
                'og_image' => '',
            ],
        ]);
    }
}
