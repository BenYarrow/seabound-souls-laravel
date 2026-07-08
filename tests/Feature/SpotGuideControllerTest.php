<?php

// Feature tests for App\Http\Controllers\SpotGuideController — the public
// /destinations/{slug} detail route. Covers rendering, published-only access,
// the stay/eat recommendation split, locations, and the weather-by-year shape.

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use App\Models\WindsurfingLocation;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpotGuideControllerTest extends TestCase
{
    public function test_show_renders_a_published_spot_guide_with_its_country(): void
    {
        $guide = SpotGuide::factory()->create();

        $response = $this->get(route('spot-guides.show', $guide->slug));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('SpotGuide/Show')
                ->where('spotGuide.slug', $guide->slug)
                ->where('spotGuide.country.name', $guide->country->name)
        );
    }

    public function test_show_returns_404_for_an_unpublished_guide(): void
    {
        $guide = SpotGuide::factory()->unpublished()->create();

        $this->get(route('spot-guides.show', $guide->slug))->assertNotFound();
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->get(route('spot-guides.show', 'nowhere'))->assertNotFound();
    }

    public function test_show_splits_recommendations_into_stay_and_eat(): void
    {
        $guide = SpotGuide::factory()->create();
        Recommendation::factory()->count(2)->for($guide)->create();          // stay (default)
        Recommendation::factory()->eat()->count(3)->for($guide)->create();

        $response = $this->get(route('spot-guides.show', $guide->slug));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('spotGuide.stay_recommendations', 2)
                ->has('spotGuide.eat_recommendations', 3)
        );
    }

    public function test_show_includes_windsurfing_locations(): void
    {
        $guide = SpotGuide::factory()->create();
        WindsurfingLocation::factory()->count(2)->for($guide)->create();

        $response = $this->get(route('spot-guides.show', $guide->slug));

        $response->assertInertia(
            fn (Assert $page) => $page->has('spotGuide.windsurfing_locations', 2)
        );
    }

    public function test_show_groups_weather_records_by_year_with_month_names(): void
    {
        $guide = SpotGuide::factory()->create();
        // Insert out of order to prove the controller sorts by month within a year.
        WeatherRecord::factory()->for($guide)->create(['year' => 2023, 'month' => 3]);
        WeatherRecord::factory()->for($guide)->create(['year' => 2023, 'month' => 1]);

        $response = $this->get(route('spot-guides.show', $guide->slug));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('spotGuide.weather_records.2023', 2)
                ->where('spotGuide.weather_records.2023.0.month', 'January')
                ->where('spotGuide.weather_records.2023.1.month', 'March')
        );
    }

    /**
     * When a guide has a MediaLibrary thumbnail with non-default focal values,
     * the controller must emit them as spotGuide.thumbnail.focal_x / focal_y
     * rather than a plain URL string.
     */
    public function test_show_exposes_thumbnail_with_focal_point(): void
    {
        $media = MediaLibrary::create(['name' => 'Hero', 'focal_x' => 20, 'focal_y' => 80]);
        $guide = SpotGuide::factory()->create(['thumbnail_media_id' => $media->id]);

        $this->get(route('spot-guides.show', $guide->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('spotGuide.thumbnail.focal_x', 20)
                ->where('spotGuide.thumbnail.focal_y', 80)
            );
    }
}
