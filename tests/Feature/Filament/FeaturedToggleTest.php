<?php

// The Filament Featured toggle persists and routes through the single-featured
// enforcement — featuring one record via the admin form un-features another.

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\SpotGuideResource\Pages\EditSpotGuide;
use App\Models\Blog;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FeaturedToggleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsOwner();
    }

    public function test_featuring_a_blog_via_the_admin_unfeatures_the_previous(): void
    {
        $current = Blog::factory()->create(['is_featured' => true]);
        $target = Blog::factory()->create();

        Livewire::test(EditBlog::class, ['record' => $target->getRouteKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->is_featured);
        $this->assertFalse($current->fresh()->is_featured);
    }

    public function test_featuring_a_spot_guide_via_the_admin_unfeatures_the_previous(): void
    {
        Queue::fake(); // swallow any weather job

        $current = SpotGuide::factory()->create(['is_featured' => true]);
        $target = SpotGuide::factory()->create();

        Livewire::test(EditSpotGuide::class, ['record' => $target->getRouteKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->is_featured);
        $this->assertFalse($current->fresh()->is_featured);
    }
}
