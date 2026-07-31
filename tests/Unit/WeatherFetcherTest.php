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
    /** Complete Feb 2024 hourly readings, all inside the 9am–7pm window. */
    private function fakeArchiveResponse(): array
    {
        $times = [];
        $temps = [];
        $winds = [];
        $gusts = [];

        // Generate all 29 days of Feb 2024 (leap year).
        for ($day = 1; $day <= 29; $day++) {
            $date = sprintf('2024-02-%02d', $day);
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

    /**
     * Guarantees that a single spot's HTTP 500 does not abort the entire batch.
     *
     * When fetchForSpots is called with two spots, the first of which receives an
     * HTTP 500 from the Open-Meteo archive API, the service must:
     *  - catch the RuntimeException thrown by fetchForSpot for the failing spot,
     *  - continue and successfully process the second spot,
     *  - report false (+ an error message) for the failing spot and true for the succeeding one,
     *  - return 1 (only the successful fetch counted),
     *  - write a weather_records row only for the succeeding spot.
     */
    public function test_fetch_for_spots_isolates_per_spot_failures(): void
    {
        Sleep::fake();

        $failingSpot = SpotGuide::factory()->create(['latitude' => 10.0, 'longitude' => -6.0]);
        $succeedingSpot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        // Return HTTP 500 for any request whose latitude matches the failing spot;
        // return a valid archive response for everything else.
        Http::fake(function ($request) use ($failingSpot) {
            $requestedLatitude = (float) $request->data()['latitude'];

            if ($requestedLatitude === (float) $failingSpot->latitude) {
                return Http::response([], 500);
            }

            return Http::response($this->fakeArchiveResponse(), 200);
        });

        $reportedResults = [];

        $successCount = app(WeatherFetcher::class)->fetchForSpots(
            [$failingSpot, $succeedingSpot],
            function (SpotGuide $spot, bool $succeeded, ?string $errorMessage) use (&$reportedResults) {
                $reportedResults[$spot->id] = [
                    'succeeded' => $succeeded,
                    'errorMessage' => $errorMessage,
                ];
            }
        );

        // Only the succeeding spot contributes to the return count.
        $this->assertSame(1, $successCount);

        // Reporter must have been called for both spots.
        $this->assertArrayHasKey($failingSpot->id, $reportedResults);
        $this->assertArrayHasKey($succeedingSpot->id, $reportedResults);

        // Failing spot: reporter receives false and a non-empty error message.
        $this->assertFalse($reportedResults[$failingSpot->id]['succeeded']);
        $this->assertNotEmpty($reportedResults[$failingSpot->id]['errorMessage']);

        // Succeeding spot: reporter receives true and no error message.
        $this->assertTrue($reportedResults[$succeedingSpot->id]['succeeded']);
        $this->assertNull($reportedResults[$succeedingSpot->id]['errorMessage']);

        // Only the succeeding spot must have a weather_records row in the database.
        $this->assertSame(0, WeatherRecord::where('spot_guide_id', $failingSpot->id)->count());
        $this->assertSame(1, WeatherRecord::where('spot_guide_id', $succeedingSpot->id)->count());
    }
}
