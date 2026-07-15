<?php

// The dynamic XML sitemap and robots.txt: /sitemap.xml lists published content
// (and only published), /robots.txt points crawlers at it via the current host.

namespace Tests\Feature;

use App\Models\Country;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The sitemap response is cached; clear it so each test builds fresh data.
        Cache::flush();
    }

    private function guide(string $slug, bool $published): SpotGuide
    {
        Queue::fake();

        return SpotGuide::create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1,
            'longitude' => 1,
            'is_published' => $published,
        ]);
    }

    public function test_sitemap_is_served_as_xml_and_lists_published_guides_only(): void
    {
        $this->guide('published-bay', true);
        $this->guide('draft-bay', false);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $response->assertSee('/destinations/published-bay', false);
        $response->assertDontSee('/destinations/draft-bay', false);
        // Core static pages are present.
        $response->assertSee('/destinations', false);
        $response->assertSee('/blog', false);
    }

    public function test_sitemap_includes_tags_with_published_posts(): void
    {
        $tag = \App\Models\Tag::factory()->create(['slug' => 'wave-sailing']);
        $tag->blogs()->attach(\App\Models\Blog::factory()->create());

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/blog/tags/wave-sailing');
    }

    public function test_sitemap_excludes_empty_or_draft_only_tags(): void
    {
        $draftOnly = \App\Models\Tag::factory()->create(['slug' => 'draft-only']);
        $draftOnly->blogs()->attach(\App\Models\Blog::factory()->unpublished()->create());
        \App\Models\Tag::factory()->create(['slug' => 'totally-empty']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee('/blog/tags/draft-only');
        $response->assertDontSee('/blog/tags/totally-empty');
    }
}
