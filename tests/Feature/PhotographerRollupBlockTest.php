<?php

// The list_photographers block auto-populates from photographers with a live
// page — the owner never hand-picks. Photographers without a page must not
// appear, since their card would link to a 404.

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerRollupBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_lists_only_photographers_with_a_live_page(): void
    {
        Photographer::factory()->withPublicPage()->create(['name' => 'Live One']);
        Photographer::factory()->create(['name' => 'No Page', 'profile_blocks' => null]);

        Page::create([
            'title' => 'About',
            'slug' => 'about-photographers-test',
            'is_published' => true,
            'content_blocks' => [
                ['type' => 'list_photographers', 'data' => ['heading' => 'Our photographers']],
            ],
        ]);

        $this->get('/about-photographers-test')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('page.content_blocks.0.data.photographers_resolved', 1)
                ->where('page.content_blocks.0.data.photographers_resolved.0.name', 'Live One'));
    }
}
