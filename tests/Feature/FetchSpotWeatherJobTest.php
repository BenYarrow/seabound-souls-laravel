<?php

// Feature tests for the auto-on-create weather trigger and FetchSpotWeatherJob.

namespace Tests\Feature;

use App\Jobs\FetchSpotWeatherJob;
use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetchSpotWeatherJobTest extends TestCase
{
    public function test_creating_a_spot_with_coordinates_dispatches_the_job(): void
    {
        Queue::fake();

        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        Queue::assertPushed(FetchSpotWeatherJob::class, fn (FetchSpotWeatherJob $job) => $job->spotGuideId === $spot->id);
    }

    public function test_creating_a_spot_without_coordinates_does_not_dispatch(): void
    {
        Queue::fake();

        SpotGuide::factory()->create(['latitude' => null, 'longitude' => null]);

        Queue::assertNotPushed(FetchSpotWeatherJob::class);
    }

    public function test_job_no_ops_when_spot_has_no_coordinates(): void
    {
        Sleep::fake();
        Http::fake();

        $spot = SpotGuide::factory()->create(['latitude' => null, 'longitude' => null]);

        (new FetchSpotWeatherJob($spot->id))->handle(app(WeatherFetcher::class));

        // Guard clause fired: no Open-Meteo call was made.
        Http::assertNothingSent();
    }

    /**
     * A queued job can sit in the worker queue after the spot guide it was
     * dispatched for has been deleted. The deleted-model guard should silently
     * no-op rather than throw or call the API.
     *
     * Uses SoftDeletes' ->delete() which makes SpotGuide::find($id) return null
     * (soft-deleted rows are excluded from default queries), matching the exact
     * condition the guard tests for at runtime.
     */
    public function test_job_no_ops_when_spot_was_deleted_before_worker_ran(): void
    {
        Sleep::fake();
        Http::fake();

        // Create with coordinates so the job would normally proceed past both guards.
        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);
        $deletedSpotId = $spot->id;

        // Soft-delete the spot — SpotGuide::find($deletedSpotId) now returns null.
        $spot->delete();

        (new FetchSpotWeatherJob($deletedSpotId))->handle(app(WeatherFetcher::class));

        // Deleted-model guard fired: no Open-Meteo call was made.
        Http::assertNothingSent();
    }
}
