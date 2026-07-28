<?php

// Shared Open-Meteo fetch service. Pulls 3 years of hourly archive data for a
// spot guide, reduces it to monthly averages (temperature / wind / gust) over
// the 9am–7pm sailing window, and upserts WeatherRecord rows. Single source of
// truth for the weekly weather:fetch command and the FetchSpotWeather /
// FetchAllWeather queued jobs — none of them re-implement the fetch.

namespace App\Services;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class WeatherFetcher
{
    /**
     * Fetch, aggregate, and upsert monthly weather averages for one spot.
     *
     * Splits the 3-year range into 3-month windows because a single large
     * request to the archive API intermittently 500s; pauses between windows
     * (via the fakeable Sleep helper) to stay within Open-Meteo rate limits.
     */
    public function fetchForSpot(SpotGuide $spot): void
    {
        $times = [];
        $temps = [];
        $winds = [];
        $gusts = [];

        $startOfRange = now()->subYears(3);
        $endOfRange = now();
        $chunkStart = $startOfRange->copy();
        $isFirstChunk = true;

        while ($chunkStart->lt($endOfRange)) {
            $chunkEnd = $chunkStart->copy()->addMonths(3);
            if ($chunkEnd->gt($endOfRange)) {
                $chunkEnd = $endOfRange->copy();
            }

            if (! $isFirstChunk) {
                Sleep::for(1)->second();
            }
            $isFirstChunk = false;

            $response = Http::get('https://archive-api.open-meteo.com/v1/archive', [
                'latitude' => $spot->latitude,
                'longitude' => $spot->longitude,
                'start_date' => $chunkStart->format('Y-m-d'),
                'end_date' => $chunkEnd->format('Y-m-d'),
                'hourly' => 'temperature_2m,wind_speed_10m,wind_gusts_10m',
                'wind_speed_unit' => 'kn',
                'timezone' => 'auto',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException("API error: {$response->status()} for {$chunkStart->format('Y-m-d')} to {$chunkEnd->format('Y-m-d')}");
            }

            $hourlyData = $response->json()['hourly'] ?? [];
            $times = array_merge($times, $hourlyData['time'] ?? []);
            $temps = array_merge($temps, $hourlyData['temperature_2m'] ?? []);
            $winds = array_merge($winds, $hourlyData['wind_speed_10m'] ?? []);
            $gusts = array_merge($gusts, $hourlyData['wind_gusts_10m'] ?? []);

            $chunkStart = $chunkEnd->copy();
        }

        // Daily buckets, restricted to the 9am–7pm sailing window.
        $dailyMap = [];
        foreach ($times as $index => $datetime) {
            $hour = (int) substr($datetime, 11, 2);
            if ($hour < 9 || $hour > 19) {
                continue;
            }

            $date = substr($datetime, 0, 10);
            if (! isset($dailyMap[$date])) {
                $dailyMap[$date] = ['temps' => [], 'winds' => [], 'gusts' => []];
            }
            if (isset($temps[$index]) && $temps[$index] !== null) {
                $dailyMap[$date]['temps'][] = $temps[$index];
            }
            if (isset($winds[$index]) && $winds[$index] !== null) {
                $dailyMap[$date]['winds'][] = $winds[$index];
            }
            if (isset($gusts[$index]) && $gusts[$index] !== null) {
                $dailyMap[$date]['gusts'][] = $gusts[$index];
            }
        }

        $average = fn (array $values): float => count($values) > 0 ? array_sum($values) / count($values) : 0.0;

        // Roll daily buckets up into year/month buckets.
        $yearMonthMap = [];
        foreach ($dailyMap as $date => $values) {
            [$year, $monthNumber] = explode('-', $date);
            $key = "{$year}-{$monthNumber}";
            if (! isset($yearMonthMap[$key])) {
                $yearMonthMap[$key] = ['year' => (int) $year, 'month' => (int) $monthNumber, 'temps' => [], 'winds' => [], 'gusts' => []];
            }
            $yearMonthMap[$key]['temps'][] = $average($values['temps']);
            $yearMonthMap[$key]['winds'][] = $average($values['winds']);
            $yearMonthMap[$key]['gusts'][] = $average($values['gusts']);
        }

        foreach ($yearMonthMap as $row) {
            $ktsWind = round($average($row['winds']), 1);
            $ktsGust = round($average($row['gusts']), 1);

            WeatherRecord::updateOrCreate(
                ['spot_guide_id' => $spot->id, 'year' => $row['year'], 'month' => $row['month']],
                [
                    'avg_temp' => round($average($row['temps']), 1),
                    'kts_wind' => $ktsWind,
                    'kts_gust' => $ktsGust,
                    'mph_wind' => (int) round($ktsWind * 1.15078),
                    'mph_gust' => (int) round($ktsGust * 1.15078),
                    'kph_wind' => (int) round($ktsWind * 1.852),
                    'kph_gust' => (int) round($ktsGust * 1.852),
                ]
            );
        }

        // Persist the daily sailable-wind layer from the same 9am-7pm buckets.
        // qualifying_wind_kts = the day's 2nd-highest sustained-wind hour, because
        // "sailable" means >= 2 hours at/above the user's minimum, i.e. the 2nd
        // hour (when winds are sorted high-to-low) already clears it. A day with
        // fewer than 2 in-window readings can never be sailable, so it scores 0.
        foreach ($dailyMap as $date => $values) {
            $winds = $values['winds'];
            rsort($winds); // highest first
            $secondHighest = count($winds) >= 2 ? $winds[1] : 0.0;

            [$year, $monthNumber] = explode('-', $date);

            SailableDay::updateOrCreate(
                ['spot_guide_id' => $spot->id, 'date' => $date],
                [
                    'year' => (int) $year,
                    'month' => (int) $monthNumber,
                    'qualifying_wind_kts' => round($secondHighest, 1),
                ]
            );
        }
    }

    /**
     * Fetch weather for many spots, paced in batches of 3 with a 2-second gap
     * between batches (Open-Meteo rate-limit protection). Each spot is fetched
     * inside a try/catch so one failure never aborts the batch. Optionally
     * reports per-spot progress. Returns the number of spots fetched OK.
     *
     * @param  iterable<SpotGuide>  $spots
     * @param  (callable(SpotGuide, bool, ?string): void)|null  $reporter
     */
    public function fetchForSpots(iterable $spots, ?callable $reporter = null): int
    {
        $processed = 0;

        foreach (collect($spots)->chunk(3)->values() as $batchIndex => $batch) {
            if ($batchIndex > 0) {
                Sleep::for(2)->seconds();
            }

            foreach ($batch as $spot) {
                try {
                    $this->fetchForSpot($spot);
                    $processed++;
                    if ($reporter) {
                        $reporter($spot, true, null);
                    }
                } catch (\Throwable $exception) {
                    if ($reporter) {
                        $reporter($spot, false, $exception->getMessage());
                    }
                }
            }
        }

        return $processed;
    }
}
