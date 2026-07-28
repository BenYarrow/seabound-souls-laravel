<?php
// tests/Feature/WeatherFetcherSailableDaysTest.php

namespace Tests\Feature;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class WeatherFetcherSailableDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_second_highest_sailing_window_hour_per_day(): void
    {
        Sleep::fake();

        // fetchForSpot splits the 3-year range into 3-month chunks (~13 requests),
        // all matching this URL pattern. The real archive API would only return
        // this single day inside the one chunk whose date range covers it; the
        // sequence fake reproduces that — only the FIRST request gets the day's
        // 09:00 -> 22kts, 10:00 -> 18kts readings (08:00 is outside the 9am-7pm
        // window and must be ignored), every other chunk gets an empty response.
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::sequence()
                ->push([
                    'hourly' => [
                        'time' => ['2025-08-01T08:00', '2025-08-01T09:00', '2025-08-01T10:00'],
                        'temperature_2m' => [20.0, 21.0, 22.0],
                        'wind_speed_10m' => [40.0, 22.0, 18.0],
                        'wind_gusts_10m' => [50.0, 30.0, 26.0],
                    ],
                ])
                ->whenEmpty(Http::response(['hourly' => ['time' => [], 'temperature_2m' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => []]])),
        ]);

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $day = SailableDay::where('spot_guide_id', $spot->id)->where('date', '2025-08-01')->first();
        $this->assertNotNull($day);
        // 2nd-highest of the in-window winds [22, 18] is 18.0 (08:00's 40 is excluded).
        $this->assertSame('18.0', (string) $day->qualifying_wind_kts);
        $this->assertSame(2025, $day->year);
        $this->assertSame(8, $day->month);
    }

    public function test_a_day_with_a_single_in_window_hour_scores_zero(): void
    {
        Sleep::fake();

        // Same reasoning as above: only the first of the ~13 chunk requests should
        // carry this day's single in-window reading, the rest must be empty so the
        // day isn't artificially duplicated into a "2+ readings" day.
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::sequence()
                ->push([
                    'hourly' => [
                        'time' => ['2025-08-02T09:00'],
                        'temperature_2m' => [21.0],
                        'wind_speed_10m' => [30.0],
                        'wind_gusts_10m' => [40.0],
                    ],
                ])
                ->whenEmpty(Http::response(['hourly' => ['time' => [], 'temperature_2m' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => []]])),
        ]);

        $spot = SpotGuide::factory()->create(['latitude' => 1, 'longitude' => 1]);
        app(WeatherFetcher::class)->fetchForSpot($spot);

        $day = SailableDay::where('spot_guide_id', $spot->id)->where('date', '2025-08-02')->first();
        $this->assertNotNull($day);
        $this->assertSame('0.0', (string) $day->qualifying_wind_kts); // <2 hours => never sailable
    }
}
