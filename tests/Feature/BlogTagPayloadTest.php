<?php

// Verifies BlogController exposes tag data on the blog index (tag bar) and
// single blog show (post tag chips) Inertia payloads.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTagPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_lists_only_tags_with_published_posts_in_sort_order(): void
    {
        $second = Tag::factory()->create(['name' => 'Second', 'sort_order' => 2]);
        $first = Tag::factory()->create(['name' => 'First', 'sort_order' => 1]);
        $first->blogs()->attach(Blog::factory()->create());
        $second->blogs()->attach(Blog::factory()->create());

        $emptyTag = Tag::factory()->create(['name' => 'Empty', 'sort_order' => 0]);

        $this->get('/blog')
            ->assertInertia(fn ($page) => $page
                ->has('tags', 2)
                ->where('tags.0.name', 'First')
                ->where('tags.1.name', 'Second'));
    }

    public function test_blog_show_includes_the_posts_tags(): void
    {
        $blog = Blog::factory()->create(['slug' => 'a-post']);
        $tag = Tag::factory()->create(['name' => 'Gear', 'slug' => 'gear']);
        $blog->tags()->attach($tag);

        $this->get('/blog/a-post')
            ->assertInertia(fn ($page) => $page
                ->has('blog.tags', 1)
                ->where('blog.tags.0.name', 'Gear')
                ->where('blog.tags.0.slug', 'gear'));
    }
}
