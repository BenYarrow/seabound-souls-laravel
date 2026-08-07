<?php

// Test data for photographers. The default record has NO profile_blocks, so it
// deliberately has no public page — matching the common real case of a
// photographer who only wants a credit.

namespace Database\Factories;

use App\Models\Photographer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photographer>
 */
class PhotographerFactory extends Factory
{
    protected $model = Photographer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'socials' => ['instagram' => 'https://instagram.com/'.$this->faker->userName()],
            'credit_link' => 'instagram',
            'profile_blocks' => null,
        ];
    }

    /** A photographer whose profile page is live (has content-builder content). */
    public function withPublicPage(): static
    {
        return $this->state(fn (): array => [
            'profile_blocks' => [
                ['type' => 'rich_text', 'data' => ['content' => '<p>Shoots water shots in Tarifa.</p>']],
            ],
        ]);
    }
}
