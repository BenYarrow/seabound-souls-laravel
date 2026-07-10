<?php

// Enforces the single-featured invariant: featuring one Blog / SpotGuide
// clears the flag on any previously featured row of the same model.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\SpotGuide;
use Tests\TestCase;

class SingleFeaturedTest extends TestCase
{
    public function test_featuring_a_blog_unfeatures_the_previous_one(): void
    {
        $first = Blog::factory()->create(['is_featured' => true]);
        $second = Blog::factory()->create(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    public function test_updating_is_featured_enforces_single_on_blogs(): void
    {
        $first = Blog::factory()->create(['is_featured' => true]);
        $second = Blog::factory()->create();

        $second->update(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    public function test_featuring_a_spot_guide_unfeatures_the_previous_one(): void
    {
        $first = SpotGuide::factory()->create(['is_featured' => true]);
        $second = SpotGuide::factory()->create(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }
}
