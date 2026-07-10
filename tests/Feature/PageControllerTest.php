<?php

// Feature tests for App\Http\Controllers\PageController — the catch-all
// /{slug} route that renders generic published pages.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Page;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    public function test_show_renders_a_published_page(): void
    {
        $page = Page::factory()->create(['title' => 'About Us', 'slug' => 'about-us']);

        $response = $this->get(route('pages.show', $page->slug));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $assert) => $assert
                ->component('Page/Show')
                ->where('page.slug', 'about-us')
                ->where('page.title', 'About Us')
        );
    }

    public function test_show_returns_404_for_an_unpublished_page(): void
    {
        $page = Page::factory()->create([
            'slug' => 'draft-page',
            'is_published' => false,
        ]);

        $this->get(route('pages.show', $page->slug))->assertNotFound();
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->get(route('pages.show', 'no-such-page'))->assertNotFound();
    }

    public function test_show_resolves_spot_guide_list_block_to_published_entries_in_order(): void
    {
        Queue::fake();
        $first = SpotGuide::factory()->create(['title' => 'First Spot', 'slug' => 'first-spot']);
        $second = SpotGuide::factory()->create(['title' => 'Second Spot', 'slug' => 'second-spot']);
        $draft = SpotGuide::factory()->unpublished()->create(['title' => 'Draft Spot', 'slug' => 'draft-spot']);

        $page = Page::factory()->create([
            'slug' => 'curated',
            'content_blocks' => [[
                'type' => 'list_content_spot_guides',
                // Authored order second → first → draft; draft drops, order kept.
                'data' => ['customSpotGuideEntries' => [$second->id, $first->id, $draft->id]],
            ]],
        ]);

        $this->get(route('pages.show', 'curated'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('page.content_blocks.0.data.customSpotGuideEntries_resolved', 2)
                ->where('page.content_blocks.0.data.customSpotGuideEntries_resolved.0.slug', 'second-spot')
                ->where('page.content_blocks.0.data.customSpotGuideEntries_resolved.1.slug', 'first-spot')
            );
    }

    public function test_show_resolves_blog_list_block_to_published_entries_only(): void
    {
        $published = Blog::factory()->create(['title' => 'Live Post', 'slug' => 'live-post']);
        Blog::factory()->unpublished()->create(['title' => 'Draft Post', 'slug' => 'draft-post']);

        // Reference the draft too (by a high id that won't exist / or its id) — it must drop.
        $draftId = Blog::where('slug', 'draft-post')->value('id');

        $page = Page::factory()->create([
            'slug' => 'reads',
            'content_blocks' => [[
                'type' => 'list_content_blogs',
                'data' => ['customBlogEntries' => [$published->id, $draftId]],
            ]],
        ]);

        $this->get(route('pages.show', 'reads'))
            ->assertInertia(fn (Assert $assert) => $assert
                ->has('page.content_blocks.0.data.customBlogEntries_resolved', 1)
                ->where('page.content_blocks.0.data.customBlogEntries_resolved.0.slug', 'live-post')
            );
    }
}
