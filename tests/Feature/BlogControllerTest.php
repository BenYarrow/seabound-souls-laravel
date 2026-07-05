<?php

// Feature tests for App\Http\Controllers\BlogController — the public /blog index
// and /blog/{slug} detail routes. Verifies the Inertia responses, published-only
// filtering, pagination, and the 404 behaviour for drafts and unknown slugs.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogControllerTest extends TestCase
{
    public function test_index_renders_the_blog_index_with_published_posts(): void
    {
        Blog::factory()->count(3)->create();

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('blogs.data', 3)
                ->has('meta.title')
        );
    }

    public function test_index_excludes_unpublished_posts(): void
    {
        Blog::factory()->count(2)->create();
        Blog::factory()->unpublished()->count(3)->create();

        $response = $this->get(route('blog.index'));

        $response->assertInertia(
            fn (Assert $page) => $page->has('blogs.data', 2)
        );
    }

    public function test_index_paginates_at_twelve_per_page(): void
    {
        Blog::factory()->count(13)->create();

        $response = $this->get(route('blog.index'));

        // 13 published posts, 12 per page → first page shows exactly 12.
        $response->assertInertia(
            fn (Assert $page) => $page->has('blogs.data', 12)
        );
    }

    public function test_index_uses_the_blog_landing_page_masthead_when_present(): void
    {
        Page::factory()->slug('blog')->create();
        Blog::factory()->create();

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        // No media attached in tests, so the URL null-coalesces to an empty string
        // rather than erroring — the prop must still be present.
        $response->assertInertia(
            fn (Assert $page) => $page->where('static_masthead', '')
        );
    }

    public function test_show_renders_a_published_post(): void
    {
        $blog = Blog::factory()->create();

        $response = $this->get(route('blog.show', $blog->slug));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('blog.slug', $blog->slug)
                ->where('blog.title', $blog->title)
        );
    }

    public function test_show_returns_404_for_an_unpublished_post(): void
    {
        $blog = Blog::factory()->unpublished()->create();

        $this->get(route('blog.show', $blog->slug))->assertNotFound();
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->get(route('blog.show', 'does-not-exist'))->assertNotFound();
    }
}
