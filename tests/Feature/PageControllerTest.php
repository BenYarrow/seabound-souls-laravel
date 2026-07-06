<?php

// Feature tests for App\Http\Controllers\PageController — the catch-all
// /{slug} route that renders generic published pages.

namespace Tests\Feature;

use App\Models\Page;
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
}
