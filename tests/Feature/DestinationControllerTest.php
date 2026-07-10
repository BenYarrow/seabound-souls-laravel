<?php

// Feature tests for App\Http\Controllers\DestinationController — the /destinations
// index. Covers published-only listing, title ordering, country inclusion, and the
// weatherData map (keyed by guide title → year → month rows) the charts consume.

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\Page;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DestinationControllerTest extends TestCase
{
    public function test_index_renders_published_destinations_with_country(): void
    {
        $guide = SpotGuide::factory()->create();

        $response = $this->get(route('destinations.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Destinations/Index')
                ->has('spotGuides', 1)
                ->where('spotGuides.0.country.name', $guide->country->name)
        );
    }

    public function test_index_excludes_unpublished_destinations(): void
    {
        SpotGuide::factory()->count(2)->create();
        SpotGuide::factory()->unpublished()->count(3)->create();

        $response = $this->get(route('destinations.index'));

        $response->assertInertia(
            fn (Assert $page) => $page->has('spotGuides', 2)
        );
    }

    public function test_index_orders_destinations_by_title(): void
    {
        SpotGuide::factory()->create(['title' => 'Zebra Bay', 'slug' => 'zebra-bay']);
        SpotGuide::factory()->create(['title' => 'Alpha Cove', 'slug' => 'alpha-cove']);

        $response = $this->get(route('destinations.index'));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('spotGuides.0.title', 'Alpha Cove')
                ->where('spotGuides.1.title', 'Zebra Bay')
        );
    }

    public function test_index_keys_weather_data_by_title_grouped_by_year(): void
    {
        $guide = SpotGuide::factory()->create(['title' => 'Tarifa', 'slug' => 'tarifa']);
        WeatherRecord::factory()->for($guide)->create(['year' => 2023, 'month' => 2]);
        WeatherRecord::factory()->for($guide)->create(['year' => 2023, 'month' => 1]);

        $response = $this->get(route('destinations.index'));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('weatherData.Tarifa.2023', 2)
                // Sorted by month within the year, so January comes first.
                ->where('weatherData.Tarifa.2023.0.month', 'January')
        );
    }

    /**
     * Each guide card on the index must expose thumbnail as a focal-bearing
     * object rather than a plain URL string.
     */
    public function test_index_exposes_thumbnail_with_focal_point(): void
    {
        $media = MediaLibrary::create(['name' => 'Thumb', 'focal_x' => 30, 'focal_y' => 70]);
        SpotGuide::factory()->create(['thumbnail_media_id' => $media->id]);

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('spotGuides.0.thumbnail.focal_x', 30)
                ->where('spotGuides.0.thumbnail.focal_y', 70)
            );
    }

    public function test_index_uses_the_destinations_page_masthead_when_present(): void
    {
        $media = MediaLibrary::create(['name' => 'Destinations Hero', 'focal_x' => 40, 'focal_y' => 65]);
        Page::factory()->slug('destinations')->create(['static_masthead_media_id' => $media->id]);
        SpotGuide::factory()->create();

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('static_masthead.focal_x', 40)
                ->where('static_masthead.focal_y', 65)
            );
    }

    public function test_index_static_masthead_is_null_without_a_destinations_page(): void
    {
        SpotGuide::factory()->create();

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('static_masthead', null));
    }

    public function test_index_passes_the_featured_spot_guide_and_keeps_it_in_the_grid(): void
    {
        SpotGuide::factory()->create(['title' => 'Featured Bay', 'slug' => 'featured-bay', 'is_featured' => true]);
        SpotGuide::factory()->create();

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('featuredSpotGuide.title', 'Featured Bay')
                ->has('spotGuides', 2) // the featured guide is still listed in its continent grid
            );
    }

    public function test_index_featured_spot_guide_is_null_when_none_flagged(): void
    {
        SpotGuide::factory()->count(2)->create();

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('featuredSpotGuide', null));
    }

    public function test_index_featured_spot_guide_is_null_when_unpublished(): void
    {
        SpotGuide::factory()->create(['is_published' => false, 'is_featured' => true]);

        $this->get(route('destinations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('featuredSpotGuide', null));
    }
}
