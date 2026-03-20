<?php

namespace App\Console\Commands;

use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WeatherFetch extends Command
{
    protected $signature = 'weather:fetch {--spot= : Only fetch for a specific spot guide slug}';

    protected $description = 'Fetch historical weather data from Open-Meteo for all spot guides';

    private const MONTH_NAMES = [
        '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
        '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
        '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December',
    ];

    public function handle(): int
    {
        $this->info('Starting weather data fetch...');

        $query = SpotGuide::whereNotNull('latitude')->whereNotNull('longitude');
        if ($slug = $this->option('spot')) {
            $query->where('slug', $slug);
        }
        $spotGuides = $query->get();

        if ($spotGuides->isEmpty()) {
            $this->warn('No spot guides with coordinates found.');
            return self::SUCCESS;
        }

        $this->info("Processing {$spotGuides->count()} spot guides...");

        $chunks = $spotGuides->chunk(3);
        $processed = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            if ($chunkIndex > 0) {
                $this->line('Waiting 2 seconds between batches...');
                sleep(2);
            }

            foreach ($chunk as $spotGuide) {
                try {
                    $this->processSpotGuide($spotGuide);
                    $processed++;
                    $this->info("✓ {$spotGuide->title}");
                } catch (\Throwable $e) {
                    $this->error("✗ {$spotGuide->title}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Completed. Processed {$processed} spot guides.");
        return self::SUCCESS;
    }

    private function processSpotGuide(SpotGuide $spotGuide): void
    {
        $currentYear = now()->year;
        $month = now()->format('m');
        $day = now()->format('d');

        // Split into 3-month chunks to avoid 500 errors from large JSON responses
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
                sleep(1);
            }
            $isFirstChunk = false;

            $response = Http::get('https://archive-api.open-meteo.com/v1/archive', [
                'latitude' => $spotGuide->latitude,
                'longitude' => $spotGuide->longitude,
                'start_date' => $chunkStart->format('Y-m-d'),
                'end_date' => $chunkEnd->format('Y-m-d'),
                'hourly' => 'temperature_2m,wind_speed_10m,wind_gusts_10m',
                'wind_speed_unit' => 'kn',
                'timezone' => 'auto',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException("API error: {$response->status()} for {$chunkStart->format('Y-m-d')} to {$chunkEnd->format('Y-m-d')}");
            }

            $data = $response->json();
            $hourlyData = $data['hourly'] ?? [];

            $times = array_merge($times, $hourlyData['time'] ?? []);
            $temps = array_merge($temps, $hourlyData['temperature_2m'] ?? []);
            $winds = array_merge($winds, $hourlyData['wind_speed_10m'] ?? []);
            $gusts = array_merge($gusts, $hourlyData['wind_gusts_10m'] ?? []);

            $chunkStart = $chunkEnd->copy();
        }

        // Build daily averages (9am-7pm only)
        $dailyMap = [];
        foreach ($times as $i => $datetime) {
            $hour = (int) substr($datetime, 11, 2);
            if ($hour < 9 || $hour > 19) continue;

            $date = substr($datetime, 0, 10);
            if (! isset($dailyMap[$date])) {
                $dailyMap[$date] = ['temps' => [], 'winds' => [], 'gusts' => []];
            }
            if (isset($temps[$i]) && $temps[$i] !== null) $dailyMap[$date]['temps'][] = $temps[$i];
            if (isset($winds[$i]) && $winds[$i] !== null) $dailyMap[$date]['winds'][] = $winds[$i];
            if (isset($gusts[$i]) && $gusts[$i] !== null) $dailyMap[$date]['gusts'][] = $gusts[$i];
        }

        // Group by year/month and calculate averages
        $yearMonthMap = [];
        foreach ($dailyMap as $date => $values) {
            [$year, $monthNum] = explode('-', $date);
            $key = "{$year}-{$monthNum}";
            if (! isset($yearMonthMap[$key])) {
                $yearMonthMap[$key] = ['year' => (int) $year, 'month' => (int) $monthNum, 'temps' => [], 'winds' => [], 'gusts' => []];
            }
            $avg = fn(array $arr): float => count($arr) > 0 ? array_sum($arr) / count($arr) : 0.0;
            $yearMonthMap[$key]['temps'][] = $avg($values['temps']);
            $yearMonthMap[$key]['winds'][] = $avg($values['winds']);
            $yearMonthMap[$key]['gusts'][] = $avg($values['gusts']);
        }

        // Upsert weather records
        foreach ($yearMonthMap as $row) {
            $avg = fn(array $arr): float => count($arr) > 0 ? array_sum($arr) / count($arr) : 0.0;
            $ktsWind = round($avg($row['winds']), 1);
            $ktsGust = round($avg($row['gusts']), 1);

            WeatherRecord::updateOrCreate(
                [
                    'spot_guide_id' => $spotGuide->id,
                    'year' => $row['year'],
                    'month' => $row['month'],
                ],
                [
                    'avg_temp' => round($avg($row['temps']), 1),
                    'kts_wind' => $ktsWind,
                    'kts_gust' => $ktsGust,
                    'mph_wind' => (int) round($ktsWind * 1.15078),
                    'mph_gust' => (int) round($ktsGust * 1.15078),
                    'kph_wind' => (int) round($ktsWind * 1.852),
                    'kph_gust' => (int) round($ktsGust * 1.852),
                ]
            );
        }
    }
}
