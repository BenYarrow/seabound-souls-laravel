<?php

// Builds the site's XML sitemap from published content. Kept as a standalone
// builder (rather than inside a controller/command) so the URL set has a single
// source of truth. Served dynamically + cached by SitemapController — on Laravel
// Cloud's ephemeral filesystem a written public/sitemap.xml isn't reliably served,
// so we generate on request instead of to a static file.

namespace App\Support;

use App\Models\Blog;
use App\Models\Page;
use App\Models\SpotGuide;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapBuilder
{
    /**
     * Assemble the sitemap: static pages plus every published spot guide, blog
     * post, and generic page (the home Page is excluded — "/" is already listed).
     */
    public static function build(): Sitemap
    {
        $sitemap = Sitemap::create();

        $sitemap->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        $sitemap->add(Url::create('/destinations')->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        $sitemap->add(Url::create('/blog')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        $sitemap->add(Url::create('/contact')->setPriority(0.5));

        SpotGuide::published()->each(fn (SpotGuide $guide) => $sitemap->add(
            Url::create("/destinations/{$guide->slug}")
                ->setLastModificationDate($guide->updated_at)
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
        ));

        Blog::published()->each(fn (Blog $blog) => $sitemap->add(
            Url::create("/blog/{$blog->slug}")
                ->setLastModificationDate($blog->updated_at)
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
        ));

        Page::published()->each(function (Page $page) use ($sitemap) {
            // Skip the home Page — its URL is "/", already added above.
            if (! in_array($page->slug, ['home', 'homepage'], true)) {
                $sitemap->add(
                    Url::create("/{$page->slug}")
                        ->setLastModificationDate($page->updated_at)
                        ->setPriority(0.6)
                );
            }
        });

        return $sitemap;
    }
}
