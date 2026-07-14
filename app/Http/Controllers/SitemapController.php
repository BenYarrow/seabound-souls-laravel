<?php

// Serves /sitemap.xml dynamically. Generating on request (cached) rather than
// writing a static public/ file keeps it working on Laravel Cloud's ephemeral/
// immutable filesystem and always fresh — the URLs inside adapt to the serving
// host, so it's correct on local, staging, prod, and the eventual custom domain.
// (robots.txt is a static file instead — web servers special-case /robots.txt and
// serve it before it ever reaches Laravel.)

namespace App\Http\Controllers;

use App\Support\SitemapBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * The XML sitemap, cached for an hour so repeated crawler hits don't re-query
     * (the content set is small and changes rarely, so an hour's staleness is fine).
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => SitemapBuilder::build()->render());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
