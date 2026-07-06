<?php

// Feature tests for the live-suggestions endpoint GET /api/search. Opts into
// Scout's collection engine (suite runs SCOUT_DRIVER=null) so matching runs
// in-process without an external service.

namespace Tests\Feature\Api;

use App\Models\Blog;
use App\Models\SpotGuide;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);
    }

    public function test_returns_matching_published_spot_guides(): void
    {
        SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);
        SpotGuide::factory()->unpublished()->create(['title' => 'Tarifa Draft', 'slug' => 'tarifa-draft']);

        $response = $this->getJson('/api/search?q=Tarifa');

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'spot_guide')
            ->assertJsonPath('results.0.title', 'Tarifa');
    }

    public function test_returns_empty_results_for_short_queries(): void
    {
        SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);

        $this->getJson('/api/search?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_returns_both_blogs_and_spot_guides(): void
    {
        SpotGuide::factory()->create(['title' => 'Windy Bay', 'slug' => 'windy-bay']);
        Blog::factory()->create(['title' => 'Windy days ahead', 'slug' => 'windy-days']);

        $this->getJson('/api/search?q=Windy')
            ->assertOk()
            ->assertJsonCount(2, 'results');
    }

    public function test_caps_results_at_six_per_type(): void
    {
        for ($index = 1; $index <= 8; $index++) {
            SpotGuide::factory()->create(['title' => "Sylt Spot {$index}", 'slug' => "sylt-spot-{$index}"]);
        }

        $this->getJson('/api/search?q=Sylt')
            ->assertOk()
            ->assertJsonCount(6, 'results');
    }
}
