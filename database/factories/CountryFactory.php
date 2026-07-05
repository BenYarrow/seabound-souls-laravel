<?php

// Factory for App\Models\Country.

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->country();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'continent' => $this->faker->randomElement([
                'europe', 'africa', 'asia', 'north-america', 'south-america', 'oceania',
            ]),
        ];
    }
}
