<?php

// Factory for App\Models\Page — produces published pages by default.
// The blog index looks up a published page with slug "blog" for its masthead,
// so tests use ->slug('blog') to satisfy that lookup.

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'template' => 'standard',
            'content_blocks' => [],
            'seo_description' => $this->faker->sentence(),
            'seo_keywords' => [],
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 100)),
        ];
    }

    /**
     * Pin the slug — used to create the "blog" landing page the index controller
     * queries for its masthead image.
     */
    public function slug(string $slug): static
    {
        return $this->state(fn () => ['slug' => $slug]);
    }
}
