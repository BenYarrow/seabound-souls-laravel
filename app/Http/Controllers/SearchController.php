<?php

// Public search page:
//   GET /search?q=… — search
// Delegates the actual searching to App\Services\SiteSearch (shared with the
// live-suggestions API) and renders the full results page.

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SiteSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * Render the search page with all published matches for the query
     * (uncapped — the results page shows everything; the dropdown API caps).
     */
    public function index(Request $request, SiteSearch $search): Response
    {
        $query = $request->input('q', '');
        $results = $search->search($query);

        $page = Page::where('slug', 'search')
            ->where('is_published', true)
            ->with('staticMastheadMedia')
            ->first();

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
