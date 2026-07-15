<?php

// Tests the /blog/tags hub page (TagController@index), the tag-page masthead
// payload, tag media relations, and that /blog/tags resolves to the hub rather
// than being read as a blog post with slug "tags".

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\MediaLibrary;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_lists_only_tags_with_published_posts_in_sort_order(): void
    {
        $second = Tag::factory()->create(['name' => 'Second', 'sort_order' => 2]);
        $first = Tag::factory()->create(['name' => 'First', 'sort_order' => 1]);
        $first->blogs()->attach(Blog::factory()->create());
        $second->blogs()->attach(Blog::factory()->create());

        // A tag with only a draft post must not appear on the hub.
        $draftOnly = Tag::factory()->create(['name' => 'Draft Only', 'sort_order' => 0]);
        $draftOnly->blogs()->attach(Blog::factory()->unpublished()->create());

        $this->get('/blog/tags')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Blog/Tags')
                ->has('tags', 2)
                ->where('tags.0.name', 'First')
                ->where('tags.1.name', 'Second'));
    }

    public function test_hub_tag_cards_include_thumbnail_and_post_count(): void
    {
        $tag = Tag::factory()->create();
        $tag->blogs()->attach(Blog::factory()->count(2)->create()->pluck('id'));

        $this->get('/blog/tags')
            ->assertInertia(fn ($page) => $page
                ->where('tags.0.posts_count', 2)
                ->has('tags.0.thumbnail')); // null is fine; the key must be present
    }

    public function test_blog_tags_path_is_not_treated_as_a_blog_slug(): void
    {
        // A blog literally slugged "tags" must not shadow the hub route.
        Blog::factory()->create(['slug' => 'tags']);

        $this->get('/blog/tags')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Blog/Tags'));
    }

    public function test_tag_page_payload_includes_static_masthead_when_set(): void
    {
        $media = MediaLibrary::create(['name' => 'Tag Hero']);
        $tag = Tag::factory()->create(['static_masthead_media_id' => $media->id]);
        $tag->blogs()->attach(Blog::factory()->create());

        $this->get('/blog/tags/'.$tag->slug)
            ->assertInertia(fn ($page) => $page->has('static_masthead'));
    }

    public function test_tag_media_relations_resolve(): void
    {
        $thumb = MediaLibrary::create(['name' => 'Tag Thumb']);
        $masthead = MediaLibrary::create(['name' => 'Tag Masthead']);
        $tag = Tag::factory()->create([
            'thumbnail_media_id' => $thumb->id,
            'static_masthead_media_id' => $masthead->id,
        ]);

        $this->assertTrue($tag->thumbnailMedia->is($thumb));
        $this->assertTrue($tag->staticMastheadMedia->is($masthead));
    }
}
