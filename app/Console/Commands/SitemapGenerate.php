<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Page;
use App\Models\SpotGuide;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapGenerate extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the XML sitemap';

    public function handle(): int
    {
        $this->info('Generating sitemap...');

        $sitemap = Sitemap::create();

        // Static pages
        $sitemap->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        $sitemap->add(Url::create('/destinations')->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        $sitemap->add(Url::create('/blog')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        $sitemap->add(Url::create('/contact')->setPriority(0.5));

        // Spot guides
        SpotGuide::published()->each(function (SpotGuide $guide) use ($sitemap) {
            $sitemap->add(
                Url::create("/destinations/{$guide->slug}")
                    ->setLastModificationDate($guide->updated_at)
                    ->setPriority(0.9)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );
        });

        // Blog posts
        Blog::published()->each(function (Blog $blog) use ($sitemap) {
            $sitemap->add(
                Url::create("/blog/{$blog->slug}")
                    ->setLastModificationDate($blog->updated_at)
                    ->setPriority(0.7)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );
        });

        // Generic pages
        Page::published()->each(function (Page $page) use ($sitemap) {
            if (! in_array($page->slug, ['home', 'homepage'])) {
                $sitemap->add(
                    Url::create("/{$page->slug}")
                        ->setLastModificationDate($page->updated_at)
                        ->setPriority(0.6)
                );
            }
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated at public/sitemap.xml');
        return self::SUCCESS;
    }
}
