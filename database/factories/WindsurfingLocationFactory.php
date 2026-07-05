<?php

// Factory for App\Models\WindsurfingLocation — a launch spot within a guide.

namespace Database\Factories;

use App\Models\SpotGuide;
use App\Models\WindsurfingLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WindsurfingLocation>
 */
class WindsurfingLocationFactory extends Factory
{
    protected $model = WindsurfingLocation::class;

    public function definition(): array
    {
        return [
            'spot_guide_id' => SpotGuide::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'sort_order' => 0,
        ];
    }
}
