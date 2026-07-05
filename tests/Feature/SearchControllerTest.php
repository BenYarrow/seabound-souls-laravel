<?php

// Feature tests for App\Http\Controllers\SearchController — the /search page.
// Covers the empty/short-query short-circuit and Scout matching across published
// spot guides and blogs.
//
// The suite disables Scout globally (SCOUT_DRIVER=null); these tests opt into the
// `collection` engine, which matches in-process against the models' searchable
// arrays — no external search service needed.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\SpotGuide;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);
    }

    public function test_index_renders_the_search_page_with_no_query(): void
    {
        $response = $this->get(route('search'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search')
                ->where('query', '')
                ->where('results', [])
        );
    }

    public function test_a_query_shorter_than_two_characters_returns_no_results(): void
    {
        SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);

        $response = $this->get(route('search', ['q' => 'a']));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('query', 'a')
                ->where('results', [])
        );
    }

    public function test_it_finds_published_spot_guides_matching_the_query(): void
    {
        SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);
        // A draft with the same term must not surface.
        SpotGuide::factory()->unpublished()->create(['title' => 'Tarifa Draft', 'slug' => 'tarifa-draft']);

        $response = $this->get(route('search', ['q' => 'Tarifa']));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('results', 1)
                ->where('results.0.type', 'spot_guide')
                ->where('results.0.title', 'Tarifa')
        );
    }

    public function test_it_finds_published_blogs_matching_the_query(): void
    {
        Blog::factory()->create(['title' => 'Windsurfing Tips', 'slug' => 'windsurfing-tips']);

        $response = $this->get(route('search', ['q' => 'Windsurfing']));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('results', 1)
                ->where('results.0.type', 'blog')
                ->where('results.0.title', 'Windsurfing Tips')
        );
    }
}
