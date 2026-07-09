<?php

// Weekly (and ad-hoc) weather refresh command. Thin CLI wrapper over
// App\Services\WeatherFetcher — it selects spot guides and reports progress to
// the console; all fetch/aggregate/upsert logic lives in the service.

namespace App\Console\Commands;

use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Console\Command;

class WeatherFetch extends Command
{
    protected $signature = 'weather:fetch {--spot= : Only fetch for a specific spot guide slug}';

    protected $description = 'Fetch historical weather data from Open-Meteo for all spot guides';

    public function handle(WeatherFetcher $fetcher): int
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

        $processed = $fetcher->fetchForSpots($spotGuides, function (SpotGuide $spot, bool $ok, ?string $error): void {
            $ok ? $this->info("✓ {$spot->title}") : $this->error("✗ {$spot->title}: {$error}");
        });

        $this->info("Completed. Processed {$processed} spot guides.");
        return self::SUCCESS;
    }
}
