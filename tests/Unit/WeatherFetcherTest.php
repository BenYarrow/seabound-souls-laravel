<?php

// Unit tests for App\Services\WeatherFetcher — the shared Open-Meteo fetch +
// monthly-average upsert used by the weekly command and the fetch jobs.

namespace Tests\Unit;

use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use App\Services\WeatherFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class WeatherFetcherTest extends TestCase
{
    /** Two days of hourly readings in Feb 2024, all inside the 9am–7pm window. */
    private function fakeArchiveResponse(): array
    {
        $times = [];
        $temps = [];
        $winds = [];
        $gusts = [];

        foreach (['2024-02-10', '2024-02-11'] as $date) {
            foreach (range(9, 19) as $hour) {
                $times[] = sprintf('%sT%02d:00', $date, $hour);
                $temps[] = 20.0;
                $winds[] = 15.0;
                $gusts[] = 22.0;
            }
        }

        return ['hourly' => ['time' => $times, 'temperature_2m' => $temps, 'wind_speed_10m' => $winds, 'wind_gusts_10m' => $gusts]];
    }

    public function test_fetch_for_spot_upserts_monthly_averages(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);

        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $record = WeatherRecord::where('spot_guide_id', $spot->id)->where('year', 2024)->where('month', 2)->first();
        $this->assertNotNull($record);
        $this->assertEquals(20.0, (float) $record->avg_temp);
        $this->assertEquals(15.0, (float) $record->kts_wind);
        $this->assertEquals(22.0, (float) $record->kts_gust);
    }

    public function test_fetch_for_spot_is_idempotent(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);

        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);
        $fetcher = app(WeatherFetcher::class);

        $fetcher->fetchForSpot($spot);
        $fetcher->fetchForSpot($spot);

        // One row per (spot, year, month) — the second run updates, not duplicates.
        $this->assertSame(1, WeatherRecord::where('spot_guide_id', $spot->id)->where('year', 2024)->where('month', 2)->count());
    }

    public function test_fetch_for_spots_reports_and_counts(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);

        $spots = SpotGuide::factory()->count(2)->create(['latitude' => 36.0, 'longitude' => -6.0]);
        $reported = [];

        $processed = app(WeatherFetcher::class)->fetchForSpots($spots, function (SpotGuide $spot, bool $ok) use (&$reported) {
            $reported[$spot->id] = $ok;
        });

        $this->assertSame(2, $processed);
        $this->assertSame([true, true], array_values($reported));
    }
}
