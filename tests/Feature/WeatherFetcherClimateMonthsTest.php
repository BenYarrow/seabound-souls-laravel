<?php
// tests/Feature/WeatherFetcherClimateMonthsTest.php
//
// The /destinations charts average weather_records across years with equal
// weight per row, so a row must always represent a COMPLETE calendar month.
// These tests pin that contract at the write path.

namespace Tests\Feature;

use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class WeatherFetcherClimateMonthsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a faked Open-Meteo response whose first chunk carries the given
     * hourly rows and whose remaining chunks are empty. fetchForSpot buckets
     * by each reading's own `time` value, not by which chunk returned it, so
     * putting every reading in the first chunk is safe and keeps tests short.
     *
     * @param  array<int, array{0: string, 1: float|null, 2: float|null, 3: float|null}>  $readings
     */
    private function fakeArchive(array $readings): void
    {
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::sequence()
                ->push(['hourly' => [
                    'time' => array_column($readings, 0),
                    'temperature_2m' => array_column($readings, 1),
                    'wind_speed_10m' => array_column($readings, 2),
                    'wind_gusts_10m' => array_column($readings, 3),
                ]])
                ->whenEmpty(Http::response(['hourly' => [
                    'time' => [], 'temperature_2m' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => [],
                ]])),
        ]);
    }

    /**
     * Two in-window hourly readings for EVERY day of the given month. A month
     * only becomes a climate row once every one of its days is present, so a
     * "complete month" fixture has to actually be complete.
     *
     * @return array<int, array{0: string, 1: float|null, 2: float, 3: float}>
     */
    private function fullMonthReadings(\Illuminate\Support\Carbon $month, float $temp, float $wind, float $gust): array
    {
        $readings = [];
        $day = $month->copy()->startOfMonth();

        for ($dayNumber = 1; $dayNumber <= $month->daysInMonth; $dayNumber++) {
            $readings[] = [$day->format('Y-m-d').'T09:00', $temp, $wind, $gust];
            $readings[] = [$day->format('Y-m-d').'T10:00', $temp, $wind, $gust];
            $day->addDay();
        }

        return $readings;
    }

    public function test_it_skips_the_incomplete_current_month(): void
    {
        Sleep::fake();

        // A complete past month (six months back is always inside the 3-year
        // window and is never the current month), plus the current month —
        // which is partial by definition and must not become a climate row.
        $completeMonth = now()->subMonths(6)->startOfMonth();
        $currentMonth = now()->startOfMonth();

        $this->fakeArchive(array_merge(
            $this->fullMonthReadings($completeMonth, 20.0, 10.0, 15.0),
            // Only the 1st of the current month — deliberately partial.
            [
                [$currentMonth->format('Y-m-d').'T09:00', 25.0, 30.0, 40.0],
                [$currentMonth->format('Y-m-d').'T10:00', 26.0, 32.0, 42.0],
            ],
        ));

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseHas('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $completeMonth->year,
            'month' => (int) $completeMonth->month,
        ]);

        $this->assertDatabaseMissing('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $currentMonth->year,
            'month' => (int) $currentMonth->month,
        ]);
    }

    public function test_the_daily_layer_still_keeps_the_incomplete_current_month(): void
    {
        Sleep::fake();

        // The sailable-days ranking is coverage-normalised, so partial months
        // are correct there. Excluding them from the climate table must not
        // leak into the daily table.
        $currentMonthDay = now()->startOfMonth()->format('Y-m-d');

        $this->fakeArchive([
            [$currentMonthDay.'T09:00', 25.0, 30.0, 40.0],
            [$currentMonthDay.'T10:00', 26.0, 32.0, 42.0],
        ]);

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseHas('spot_sailable_days', [
            'spot_guide_id' => $spot->id,
            'date' => $currentMonthDay,
        ]);
    }

    public function test_it_removes_climate_rows_that_fell_out_of_the_window(): void
    {
        Sleep::fake();

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        // A frozen row from an older fetch, now far outside the 3-year window.
        // Nothing re-fetches it, so updateOrCreate can never correct it — it
        // would keep voting in the cross-year average forever.
        \App\Models\WeatherRecord::create([
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYears(5)->year,
            'month' => 7,
            'avg_temp' => 30.0,
            'kts_wind' => 99.0,
            'kts_gust' => 99.0,
            'mph_wind' => 114,
            'mph_gust' => 114,
            'kph_wind' => 183,
            'kph_gust' => 183,
        ]);

        $completeMonth = now()->subMonths(6)->startOfMonth();

        $this->fakeArchive($this->fullMonthReadings($completeMonth, 20.0, 10.0, 15.0));

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseMissing('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYears(5)->year,
            'month' => 7,
        ]);

        $this->assertDatabaseHas('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $completeMonth->year,
            'month' => (int) $completeMonth->month,
        ]);
    }

    public function test_a_failed_fetch_does_not_wipe_existing_climate_rows(): void
    {
        Sleep::fake();

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        \App\Models\WeatherRecord::create([
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYear()->year,
            'month' => 3,
            'avg_temp' => 18.0,
            'kts_wind' => 12.0,
            'kts_gust' => 18.0,
            'mph_wind' => 14,
            'mph_gust' => 21,
            'kph_wind' => 22,
            'kph_gust' => 33,
        ]);

        // The API fails partway, so fetchForSpot throws before writing anything.
        // The delete must not have happened — a failed fetch leaving a spot with
        // no climate data would blank its charts.
        Http::fake(['archive-api.open-meteo.com/*' => Http::response('boom', 500)]);

        try {
            app(WeatherFetcher::class)->fetchForSpot($spot);
            $this->fail('Expected the fetch to throw on a 500.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYear()->year,
            'month' => 3,
        ]);
    }
}
