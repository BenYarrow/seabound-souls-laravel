<?php

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
        ];
    }
}
