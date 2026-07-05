<?php

// Factory for App\Models\SpotGuide — a published spot guide with a country.
// The model's saving hook denormalises country_name from the related country,
// so tests don't set it directly. Use ->unpublished() for the 404 / scope paths.

namespace Database\Factories;

use App\Models\Country;
use App\Models\SpotGuide;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpotGuide>
 */
class SpotGuideFactory extends Factory
{
    protected $model = SpotGuide::class;

    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'country_id' => Country::factory(),
            'timezone' => 'Europe/London',
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'introduction_text' => $this->faker->paragraph(),
            'when_to_go' => $this->faker->sentence(),
            'seo_keywords' => [],
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 100)),
        ];
    }

    /** Draft state — excluded from the public site and 404s on the show route. */
    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
