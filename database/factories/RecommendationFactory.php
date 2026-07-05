<?php

// Factory for App\Models\Recommendation — a "stay" recommendation by default.
// Use ->eat() for the eat type; the controller splits recommendations by type.

namespace Database\Factories;

use App\Models\Recommendation;
use App\Models\SpotGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    public function definition(): array
    {
        return [
            'spot_guide_id' => SpotGuide::factory(),
            'type' => 'stay',
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'url' => $this->faker->url(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'sort_order' => 0,
        ];
    }

    /** Eat-type recommendation (restaurants/cafés) rather than accommodation. */
    public function eat(): static
    {
        return $this->state(fn () => ['type' => 'eat']);
    }
}
