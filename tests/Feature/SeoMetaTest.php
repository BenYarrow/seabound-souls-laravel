<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * SEO meta is editable per page via the matching Page record and always has a
 * single brand suffix (added globally in app.tsx, so controllers emit bare
 * titles). Keywords + OG image flow through for every page.
 */
class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_title_has_no_manual_brand_suffix(): void
    {
        $this->get('/blog')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Blog')
            ->where('meta.keywords', [])
        );
    }

    public function test_blog_index_reads_seo_from_its_page_record(): void
    {
        Page::factory()->create([
            'slug' => 'blog',
            'is_published' => true,
            'seo_title' => 'Windsurf Journal',
            'seo_description' => 'Our latest posts.',
            'seo_keywords' => ['windsurfing', 'blog'],
        ]);

        $this->get('/blog')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Windsurf Journal')
            ->where('meta.description', 'Our latest posts.')
            ->where('meta.keywords', ['windsurfing', 'blog'])
        );
    }

    public function test_contact_page_is_seeded_and_seo_reads_from_it(): void
    {
        $this->assertDatabaseHas('pages', ['slug' => 'contact']);

        Page::where('slug', 'contact')->update([
            'seo_title' => 'Say Hello',
            'seo_keywords' => ['contact'],
        ]);

        $this->get('/contact')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Say Hello')
            ->where('meta.keywords', ['contact'])
        );
    }

    public function test_destinations_index_falls_back_then_reads_from_its_page(): void
    {
        $this->get('/destinations')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Destinations')
            ->where('meta.keywords', [])
            ->where('meta.og_image', '')
        );

        Page::factory()->create([
            'slug' => 'destinations',
            'is_published' => true,
            'seo_title' => 'Windsurf Spots',
            'seo_keywords' => ['spots'],
        ]);

        $this->get('/destinations')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Windsurf Spots')
            ->where('meta.keywords', ['spots'])
        );
    }

    public function test_search_index_falls_back_then_reads_from_its_page(): void
    {
        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Search')
            ->where('meta.og_image', '')
        );

        Page::factory()->create([
            'slug' => 'search',
            'is_published' => true,
            'seo_title' => 'Find a Spot',
        ]);

        $this->get('/search')->assertInertia(fn (Assert $page) => $page
            ->where('meta.title', 'Find a Spot')
        );
    }
}
