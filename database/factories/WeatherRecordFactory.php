<?php

// Factory for App\Models\WeatherRecord — one month of averages for a spot guide.
// There's a unique (spot_guide_id, year, month) constraint, so when creating a
// run of months for one guide, drive month via a sequence to avoid collisions.

namespace Database\Factories;

use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherRecord>
 */
class WeatherRecordFactory extends Factory
{
    protected $model = WeatherRecord::class;

    public function definition(): array
    {
        return [
            'spot_guide_id' => SpotGuide::factory(),
            'year' => 2023,
            'month' => $this->faker->numberBetween(1, 12),
            'avg_temp' => $this->faker->randomFloat(1, 5, 35),
            'kts_wind' => $this->faker->randomFloat(1, 5, 30),
            'kts_gust' => $this->faker->randomFloat(1, 10, 45),
            'mph_wind' => $this->faker->numberBetween(5, 35),
            'mph_gust' => $this->faker->numberBetween(10, 55),
            'kph_wind' => $this->faker->numberBetween(10, 55),
            'kph_gust' => $this->faker->numberBetween(15, 90),
        ];
    }
}
