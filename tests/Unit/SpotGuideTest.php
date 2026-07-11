<?php

// Unit tests for App\Models\SpotGuide model behaviour: the country_name
// denormalisation hook, the published scope, and relationship ordering.

namespace Tests\Unit;

use App\Models\Country;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
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

    public function test_sort_by_gustiest_this_month_orders_by_current_gust_descending(): void
    {
        $calm = SpotGuide::factory()->create(['title' => 'Calm Bay']);
        $windy = SpotGuide::factory()->create(['title' => 'Windy Point']);
        WeatherRecord::factory()->for($calm)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 8]);
        WeatherRecord::factory()->for($windy)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 20]);

        $sorted = SpotGuide::sortByGustiestThisMonth(
            SpotGuide::with('weatherRecords')->get()
        );

        $this->assertSame(['Windy Point', 'Calm Bay'], $sorted->pluck('title')->all());
    }

    public function test_sort_by_gustiest_this_month_puts_guides_without_current_data_last(): void
    {
        // "Aaa Bay" is alphabetically first but has no current-month reading.
        SpotGuide::factory()->create(['title' => 'Aaa Bay']);
        $withData = SpotGuide::factory()->create(['title' => 'Zephyr Cove']);
        WeatherRecord::factory()->for($withData)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 5]);

        $sorted = SpotGuide::sortByGustiestThisMonth(
            SpotGuide::with('weatherRecords')->get()
        );

        $this->assertSame(['Zephyr Cove', 'Aaa Bay'], $sorted->pluck('title')->all());
    }

    public function test_sort_by_gustiest_this_month_breaks_ties_alphabetically(): void
    {
        $bravo = SpotGuide::factory()->create(['title' => 'Bravo Bay']);
        $alpha = SpotGuide::factory()->create(['title' => 'Alpha Bay']);
        WeatherRecord::factory()->for($bravo)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 12]);
        WeatherRecord::factory()->for($alpha)->create(['year' => now()->year, 'month' => now()->month, 'kts_gust' => 12]);

        $sorted = SpotGuide::sortByGustiestThisMonth(
            SpotGuide::with('weatherRecords')->get()
        );

        $this->assertSame(['Alpha Bay', 'Bravo Bay'], $sorted->pluck('title')->all());
    }
}
