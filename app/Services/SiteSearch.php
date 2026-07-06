<?php

// Shared site-search logic: a published Scout search across spot guides and
// blogs, normalised into a single typed results list. Used by both the full
// search page (SearchController) and the live-suggestions API (Api\SearchController).

namespace App\Services;

use App\Models\Blog;
use App\Models\SpotGuide;

class SiteSearch
{
    /** Minimum query length before we search — avoids noisy single-letter hits. */
    public const MIN_QUERY_LENGTH = 2;

    /**
     * Search published spot guides and blogs for the query, newest matches first
     * within each type. Returns a flat, view-ready array.
     *
     * @param  string    $query  raw user query (trimmed here)
     * @param  int|null  $limit  optional cap applied to each content type
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?int $limit = null): array
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $spotGuides = SpotGuide::search($query)
            ->query(fn ($builder) => $builder->where('is_published', true)->with(['country', 'thumbnailMedia']))
            ->get()
            ->map(fn ($guide) => [
                'type' => 'spot_guide',
                'title' => $guide->title,
                'slug' => $guide->slug,
                'url' => route('spot-guides.show', $guide->slug),
                'description' => $guide->country?->name,
                'thumbnail' => $guide->thumbnailMedia?->getUrl() ?? '',
            ]);

        $blogs = Blog::search($query)
            ->query(fn ($builder) => $builder->where('is_published', true)->with('thumbnailMedia'))
            ->get()
            ->map(fn ($blog) => [
                'type' => 'blog',
                'title' => $blog->title,
                'slug' => $blog->slug,
                'url' => route('blog.show', $blog->slug),
                'description' => $blog->seo_description,
                'thumbnail' => $blog->thumbnailMedia?->getUrl() ?? '',
            ]);

        if ($limit !== null) {
            $spotGuides = $spotGuides->take($limit);
            $blogs = $blogs->take($limit);
        }

        return $spotGuides->concat($blogs)->values()->toArray();
    }
}
