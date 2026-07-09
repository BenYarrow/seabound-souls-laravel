<?php

// Feature tests for FetchAllWeatherJob — the dashboard "Fetch all" job that
// refreshes every spot with coordinates and notifies the admin on completion.

namespace Tests\Feature;

use App\Jobs\FetchAllWeatherJob;
use App\Models\SpotGuide;
use App\Models\User;
use App\Models\WeatherRecord;
use App\Services\WeatherFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetchAllWeatherJobTest extends TestCase
{
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

    public function test_fetches_all_spots_with_coordinates(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);
        User::factory()->create();

        // Two spots with coords (single batch, no inter-batch sleep), one without.
        $spots = SpotGuide::factory()->count(2)->create(['latitude' => 36.0, 'longitude' => -6.0]);
        SpotGuide::factory()->create(['latitude' => null, 'longitude' => null]);

        (new FetchAllWeatherJob())->handle(app(WeatherFetcher::class));

        foreach ($spots as $spot) {
            $this->assertDatabaseHas('weather_records', ['spot_guide_id' => $spot->id, 'year' => 2024, 'month' => 2]);
        }
    }

    public function test_notifies_the_admin_on_completion(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);
        $user = User::factory()->create();
        SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        (new FetchAllWeatherJob())->handle(app(WeatherFetcher::class));

        // Filament stores database notifications on the notifiable's row.
        $this->assertSame(1, $user->notifications()->count());
    }
}
