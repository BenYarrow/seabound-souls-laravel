<?php

// Factory for App\Models\SailableDay — one day's qualifying sailing-window
// wind reading for a spot guide, with a random recent date and wind speed.

namespace Database\Factories;

use App\Models\SpotGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\SailableDay> */
class SailableDayFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-3 years', 'now');

        return [
            'spot_guide_id' => SpotGuide::factory(),
            'date' => $date->format('Y-m-d'),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'qualifying_wind_kts' => $this->faker->randomFloat(1, 0, 40),
            'qualifying_gust_kts' => $this->faker->randomFloat(1, 0, 45),
        ];
    }
}
