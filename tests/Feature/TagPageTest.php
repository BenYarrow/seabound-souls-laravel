<?php

// Feature tests for the public tag page (GET /blog/tags/{slug}): renders the
// tag's published posts, 404s on unknown/empty tags, and paginates at 12/page.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_page_renders_with_its_published_posts(): void
    {
        $tag = Tag::factory()->create(['name' => 'Wave Sailing']);
        $published = Blog::factory()->create(['title' => 'Mast High at Ho\'okipa']);
        $draft = Blog::factory()->unpublished()->create();
        $tag->blogs()->attach([$published->id, $draft->id]);

        $this->get('/blog/tags/'.$tag->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Second arg `false` skips the page-file-exists check: this repo's Inertia
                // testing config defaults ensure_pages_exist to true, but Blog/Tag.tsx isn't
                // built until Task 6 — only the component name string is asserted here.
                ->component('Blog/Tag', false)
                ->where('tag.name', 'Wave Sailing')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Mast High at Ho\'okipa'));
    }

    public function test_unknown_tag_slug_404s(): void
    {
        $this->get('/blog/tags/does-not-exist')->assertNotFound();
    }

    public function test_tag_with_no_published_posts_404s(): void
    {
        $tag = Tag::factory()->create();
        $tag->blogs()->attach(Blog::factory()->unpublished()->create());

        $this->get('/blog/tags/'.$tag->slug)->assertNotFound();
    }

    public function test_tag_page_paginates_at_twelve_per_page(): void
    {
        $tag = Tag::factory()->create();
        $tag->blogs()->attach(Blog::factory()->count(13)->create()->pluck('id'));

        $this->get('/blog/tags/'.$tag->slug)
            ->assertInertia(fn ($page) => $page->has('posts.data', 12));

        $this->get('/blog/tags/'.$tag->slug.'?page=2')
            ->assertInertia(fn ($page) => $page->has('posts.data', 1));
    }

    public function test_seo_meta_falls_back_when_tag_seo_fields_blank(): void
    {
        $tag = Tag::factory()->create(['name' => 'Freestyle', 'seo_title' => null]);
        $tag->blogs()->attach(Blog::factory()->create());

        $this->get('/blog/tags/'.$tag->slug)
            ->assertInertia(fn ($page) => $page->where('meta.title', 'Posts tagged Freestyle — Seabound Souls'));
    }
}
