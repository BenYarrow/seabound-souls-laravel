<?php

// Unit tests for App\Models\Recommendation: the stay / eat type scopes.

namespace Tests\Unit;

use App\Models\Recommendation;
use App\Models\SpotGuide;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    public function test_stay_and_eat_scopes_filter_by_type(): void
    {
        $guide = SpotGuide::factory()->create();
        Recommendation::factory()->for($guide)->count(2)->create();       // stay
        Recommendation::factory()->for($guide)->eat()->count(3)->create();

        $this->assertCount(2, Recommendation::stay()->get());
        $this->assertCount(3, Recommendation::eat()->get());
    }
}
