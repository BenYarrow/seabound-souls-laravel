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
}
