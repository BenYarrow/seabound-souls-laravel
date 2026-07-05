<?php

// Factory for App\Models\Blog — produces published blog posts by default.
// Use the ->unpublished() state to exercise the published() scope / 404 paths.

namespace Database\Factories;

use App\Models\Blog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Blog>
 */
class BlogFactory extends Factory
{
    protected $model = Blog::class;

    /**
     * Default attribute set: a published post with a unique title/slug and no
     * media attached (media FKs are nullable and the controllers null-coalesce).
     */
    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(4, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content_blocks' => [],
            'seo_title' => null,
            'seo_description' => $this->faker->sentence(),
            'seo_keywords' => [],
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 100)),
        ];
    }

    /**
     * Draft state — unpublished and with no publish date, so it should be
     * excluded from public listings and 404 on the show route.
     */
    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
