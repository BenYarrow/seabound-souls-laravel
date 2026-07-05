<?php

// Unit tests for App\Models\SpotGuide model behaviour: the country_name
// denormalisation hook, the published scope, and relationship ordering.

namespace Tests\Unit;

use App\Models\Country;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use App\Models\WindsurfingLocation;
use Tests\TestCase;

class SpotGuideTest extends TestCase
{
    public function test_saving_denormalises_country_name_from_the_related_country(): void
    {
        $country = Country::factory()->create(['name' => 'Greece']);

        $guide = SpotGuide::factory()->create(['country_id' => $country->id]);

        // The saving hook copies the country's name onto the guide for search.
        $this->assertSame('Greece', $guide->fresh()->country_name);
    }

    public function test_published_scope_excludes_drafts(): void
    {
        SpotGuide::factory()->count(2)->create();
        SpotGuide::factory()->unpublished()->count(3)->create();

        $this->assertCount(2, SpotGuide::published()->get());
    }

    public function test_recommendations_are_ordered_by_sort_order(): void
    {
        $guide = SpotGuide::factory()->create();
        Recommendation::factory()->for($guide)->create(['name' => 'Second', 'sort_order' => 2]);
        Recommendation::factory()->for($guide)->create(['name' => 'First', 'sort_order' => 1]);

        $this->assertSame(
            ['First', 'Second'],
            $guide->recommendations->pluck('name')->all()
        );
    }

    public function test_windsurfing_locations_are_ordered_by_sort_order(): void
    {
        $guide = SpotGuide::factory()->create();
        WindsurfingLocation::factory()->for($guide)->create(['name' => 'Bay', 'sort_order' => 2]);
        WindsurfingLocation::factory()->for($guide)->create(['name' => 'Lagoon', 'sort_order' => 1]);

        $this->assertSame(
            ['Lagoon', 'Bay'],
            $guide->windsurfingLocations->pluck('name')->all()
        );
    }
}
