<?php
// tests/Feature/DestinationSailablePayloadTest.php

namespace Tests\Feature;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationSailablePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_pools_sailable_days_by_month(): void
    {
        $spot = SpotGuide::factory()->create(['title' => 'Vassiliki', 'is_published' => true]);

        // Two Augusts (2024, 2025), one day each — pooled into a single flat values array.
        SailableDay::factory()->for($spot)->create(['date' => '2024-08-10', 'year' => 2024, 'month' => 8, 'qualifying_wind_kts' => 22.0]);
        SailableDay::factory()->for($spot)->create(['date' => '2025-08-11', 'year' => 2025, 'month' => 8, 'qualifying_wind_kts' => 12.0]);

        $response = $this->get('/destinations');

        // Values are pooled across both years; there is no server-side `years` field.
        // Note: expected as ints (not 22.0/12.0) — Inertia's assertInertia() round-trips
        // the page data through json_encode()/json_decode() before comparing with
        // assertSame(), and PHP's json_encode collapses whole-number floats (22.0 -> "22"),
        // so a literal float here would mismatch the decoded int on type alone.
        $response->assertInertia(fn ($page) => $page
            ->where('sailableDays.Vassiliki.8.values', [22, 12])
            ->missing('sailableDays.Vassiliki.8.years')
        );
    }

    public function test_climate_averages_a_month_across_years(): void
    {
        $spot = SpotGuide::factory()->create(['title' => 'Tarifa', 'is_published' => true]);
        WeatherRecord::factory()->for($spot)->create(['year' => 2024, 'month' => 8, 'kts_wind' => 20.0, 'avg_temp' => 26.0, 'kts_gust' => 25.0, 'mph_wind' => 23, 'mph_gust' => 29, 'kph_wind' => 37, 'kph_gust' => 46]);
        WeatherRecord::factory()->for($spot)->create(['year' => 2025, 'month' => 8, 'kts_wind' => 22.0, 'avg_temp' => 28.0, 'kts_gust' => 27.0, 'mph_wind' => 25, 'mph_gust' => 31, 'kph_wind' => 41, 'kph_gust' => 50]);

        $response = $this->get('/destinations');

        // Aug ktsWind averages (20+22)/2 = 21.0; there is exactly one August entry.
        // Note: expected as ints (not 21.0/27.0) for the same json_encode round-trip
        // reason as above — the averages here happen to land on whole numbers.
        $response->assertInertia(fn ($page) => $page
            ->where('climate.Tarifa.0.month', 'August')
            ->where('climate.Tarifa.0.ktsWind', 21)
            ->where('climate.Tarifa.0.avgTemp', 27)
        );
    }
}
