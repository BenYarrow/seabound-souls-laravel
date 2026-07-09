<?php

// Feature tests for App\Http\Controllers\Api\LiveWeatherController — coordinate
// validation and the cached OpenWeatherMap proxy (mocked).

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveWeatherControllerTest extends TestCase
{
    public function test_rejects_out_of_range_coordinates_without_calling_the_api(): void
    {
        Http::fake();

        $this->postJson('/api/live-weather', ['lat' => 200, 'lon' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lon']);

        // Validation must fail before any outbound request is made.
        Http::assertNothingSent();
    }

    public function test_accepts_valid_coordinates_and_returns_weather(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response(['weather' => [['main' => 'Clear']]], 200),
        ]);

        $this->postJson('/api/live-weather', ['lat' => 36.0, 'lon' => -6.0])
            ->assertOk()
            ->assertJsonPath('weather.0.main', 'Clear');
    }
}
