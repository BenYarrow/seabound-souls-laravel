<?php

// Factory for App\Models\Tag — a curated blog tag with a unique name/slug.

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'seo_title' => null,
            'seo_description' => null,
            'sort_order' => 0,
        ];
    }
}
