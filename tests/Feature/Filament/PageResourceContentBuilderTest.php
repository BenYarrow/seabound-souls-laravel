<?php

// Feature tests for PageResource: the Content Builder tab is hidden for the
// "destinations" template (a bespoke, masthead-only landing page) and shown for
// ordinary templates.

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceContentBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_content_builder_is_hidden_for_the_destinations_template(): void
    {
        $page = Page::factory()->create(['template' => 'destinations', 'slug' => 'destinations']);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormFieldIsHidden('content_blocks');
    }

    public function test_content_builder_is_shown_for_the_standard_template(): void
    {
        $page = Page::factory()->create(['template' => 'standard']);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormFieldExists('content_blocks');
    }
}
