<?php

// The list-content blocks must open in the admin without the empty-relationship
// crash (RelationshipJoiner … null given). We mount an EditPage whose
// content_blocks already contain each block type — on the old code the Select's
// ->relationship('', 'title') threw during render.

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Blog;
use App\Models\Page;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ListContentBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsOwner();
    }

    public function test_spot_guide_list_block_opens_without_the_relationship_crash(): void
    {
        Queue::fake(); // swallow the spot-guide create-hook weather job
        $guide = SpotGuide::factory()->create();
        $page = Page::factory()->create([
            'template' => 'standard',
            'content_blocks' => [[
                'type' => 'list_content_spot_guides',
                'data' => ['blockTitle' => 'Top spots', 'customSpotGuideEntries' => [$guide->id]],
            ]],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('content_blocks');
    }

    public function test_blog_list_block_opens_without_the_relationship_crash(): void
    {
        $blog = Blog::factory()->create();
        $page = Page::factory()->create([
            'template' => 'standard',
            'content_blocks' => [[
                'type' => 'list_content_blogs',
                'data' => ['blockTitle' => 'Latest', 'customBlogEntries' => [$blog->id]],
            ]],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('content_blocks');
    }
}
