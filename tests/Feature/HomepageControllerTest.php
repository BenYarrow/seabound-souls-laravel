<?php

// Feature tests for App\Http\Controllers\HomepageController — the / route.
// Covers rendering without a home Page record and the server-side
// infographic stat enrichment.

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageControllerTest extends TestCase
{
    public function test_index_renders_even_without_a_home_page_record(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $assert) => $assert
                ->component('Homepage')
                ->where('page', null)
                ->where('meta.title', 'Windsurfing Destination Guide')
        );
    }

    public function test_index_enriches_infographic_blocks_with_published_stats(): void
    {
        // A home page whose content builder includes an infographic block.
        Page::factory()->create([
            'slug' => 'home',
            'template' => 'homepage',
            'content_blocks' => [['type' => 'infographic']],
        ]);

        // Two published guides with two stay + one eat recommendation between them.
        $guides = SpotGuide::factory()->count(2)->create();
        Recommendation::factory()->for($guides->first())->count(2)->create();       // stay
        Recommendation::factory()->for($guides->last())->eat()->count(1)->create();

        $response = $this->get(route('home'));

        $response->assertInertia(
            fn (Assert $assert) => $assert
                ->where('page.content_blocks.0.data.stats.spots', 2)
                ->where('page.content_blocks.0.data.stats.hotels', 2)
                ->where('page.content_blocks.0.data.stats.restaurants', 1)
        );
    }
}
