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

    /**
     * Store the spot guide id so the worker can resolve the model at runtime.
     * We serialise the id rather than the model itself so stale model state
     * in the payload can never mask a deletion that happened after dispatch.
     */
    public function __construct(public int $spotGuideId)
    {
    }

    /**
     * Execute the weather fetch.
     *
     * Two early-exit guards keep the job safe in the face of race conditions:
     * — deleted-model guard: the spot was soft/hard deleted after dispatch was
     *   queued, so nothing to fetch for; silently no-op rather than throwing.
     * — no-coordinates guard: the spot was created without lat/lon (admins
     *   sometimes add those later); log and bail rather than hitting the API
     *   with a useless request.
     */
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
