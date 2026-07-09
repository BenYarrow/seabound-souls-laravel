<?php

// Queued single-spot weather fetch. Dispatched when a spot guide is created
// with coordinates (see SpotGuide::booted). Guards against a spot that has no
// coordinates or was deleted before the worker ran, so it can never hammer the
// API pointlessly or throw on a missing model.

namespace App\Jobs;

use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchSpotWeatherJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry a few times so a transient Open-Meteo blip doesn't lose the fetch. */
    public int $tries = 3;

    /** Seconds to wait between retries. */
    public int $backoff = 10;

    public function __construct(public int $spotGuideId)
    {
    }

    public function handle(WeatherFetcher $fetcher): void
    {
        $spot = SpotGuide::find($this->spotGuideId);

        if (! $spot) {
            return;
        }

        if ($spot->latitude === null || $spot->longitude === null) {
            Log::info("Skipping weather fetch for spot guide {$spot->id} ({$spot->title}): no coordinates.");
            return;
        }

        $fetcher->fetchForSpot($spot);
    }
}
