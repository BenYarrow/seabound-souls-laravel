<?php

// Live search-suggestions endpoint:
//   GET /api/search?q=… — api.search
// Returns a small JSON list (capped per type) for the nav search dropdown,
// via the shared SiteSearch service.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Number of results returned per content type for the dropdown. */
    private const SUGGESTION_LIMIT = 6;

    public function index(Request $request, SiteSearch $search): JsonResponse
    {
        $results = $search->search($request->input('q', ''), self::SUGGESTION_LIMIT);

        return response()->json(['results' => $results]);
    }
}
