<?php

// Tests the Tag model: blog relation, slug auto-generation, and the withPublishedPosts scope.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tag_can_have_blogs_attached(): void
    {
        $tag = Tag::factory()->create();
        $blog = Blog::factory()->create();

        $tag->blogs()->attach($blog);

        $this->assertTrue($tag->blogs->contains($blog));
        $this->assertTrue($blog->fresh()->tags->contains($tag));
    }

    public function test_slug_auto_generates_from_name_when_blank(): void
    {
        $tag = Tag::factory()->create(['name' => 'Wave Sailing', 'slug' => null]);

        $this->assertSame('wave-sailing', $tag->slug);
    }

    public function test_with_published_posts_scope_includes_only_tags_with_a_published_post(): void
    {
        $withPublished = Tag::factory()->create();
        $withPublished->blogs()->attach(Blog::factory()->create()); // published by default

        $draftOnly = Tag::factory()->create();
        $draftOnly->blogs()->attach(Blog::factory()->unpublished()->create());

        $empty = Tag::factory()->create();

        $slugs = Tag::withPublishedPosts()->pluck('slug');

        $this->assertTrue($slugs->contains($withPublished->slug));
        $this->assertFalse($slugs->contains($draftOnly->slug));
        $this->assertFalse($slugs->contains($empty->slug));
    }

    public function test_soft_deleted_blog_does_not_keep_a_tag_qualifying(): void
    {
        $tag = Tag::factory()->create();
        $blog = Blog::factory()->create();
        $tag->blogs()->attach($blog);

        $blog->delete(); // soft delete

        $this->assertFalse(Tag::withPublishedPosts()->pluck('slug')->contains($tag->slug));
    }
}
