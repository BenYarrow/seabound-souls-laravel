<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Page;
use App\Models\SpotGuide;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->input('q', '');
        $results = [];

        $page = Page::where('slug', 'search')
            ->where('is_published', true)
            ->with('staticMastheadMedia')
            ->first();

        if (strlen($query) >= 2) {
            $spotGuides = SpotGuide::search($query)
                ->query(fn($q) => $q->where('is_published', true)->with(['country', 'thumbnailMedia']))
                ->get()
                ->map(fn($guide) => [
                    'type' => 'spot_guide',
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'url' => route('spot-guides.show', $guide->slug),
                    'description' => $guide->country?->name,
                    'thumbnail' => $guide->thumbnailMedia?->getUrl() ?? '',
                ]);

            $blogs = Blog::search($query)
                ->query(fn($q) => $q->where('is_published', true)->with('thumbnailMedia'))
                ->get()
                ->map(fn($blog) => [
                    'type' => 'blog',
                    'title' => $blog->title,
                    'slug' => $blog->slug,
                    'url' => route('blog.show', $blog->slug),
                    'description' => $blog->seo_description,
                    'thumbnail' => $blog->thumbnailMedia?->getUrl() ?? '',
                ]);

            $results = $spotGuides->concat($blogs)->values()->toArray();
        }

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $results,
            'static_masthead' => $page?->staticMastheadMedia?->getUrl() ?? '',
            'meta' => [
                'title' => $query ? "Search: {$query} | Seabound Souls" : 'Search | Seabound Souls',
                'description' => 'Search for windsurfing destinations and articles.',
            ],
        ]);
    }
}
