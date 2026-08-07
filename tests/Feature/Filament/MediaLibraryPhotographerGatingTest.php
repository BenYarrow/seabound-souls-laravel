<?php

// A contributor reaching /admin/media-libraries must not be able to browse the
// photographer roster or attribute a credit to someone else. The photographer
// admin (PhotographerPolicy) is already owner-only, but MediaLibraryPolicy's
// viewAny() is deliberately `true` for everyone (the list is query-scoped, not
// gated) — so without a separate check here, the photographer surfaces this
// branch added to MediaLibraryResource (the assignment Select, table column,
// filter, and bulk action) would be reachable by any contributor, letting them
// enumerate every photographer and falsely credit their own upload to one.

namespace Tests\Feature\Filament;

use App\Filament\Resources\MediaLibraryResource\Pages\CreateMediaLibrary;
use App\Filament\Resources\MediaLibraryResource\Pages\EditMediaLibrary;
use App\Filament\Resources\MediaLibraryResource\Pages\ListMediaLibraries;
use App\Models\MediaLibrary;
use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryPhotographerGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_cannot_see_photographer_select_on_create_form(): void
    {
        $this->actingAsContributor();

        Livewire::test(CreateMediaLibrary::class)
            ->assertFormFieldIsHidden('photographer_id');
    }

    public function test_owner_can_see_photographer_select_on_create_form(): void
    {
        $this->actingAsOwner();

        Livewire::test(CreateMediaLibrary::class)
            ->assertFormFieldIsVisible('photographer_id');
    }

    public function test_contributor_cannot_see_photographer_select_on_edit_form(): void
    {
        $contributor = $this->actingAsContributor();
        $media = MediaLibrary::create(['name' => 'Mine', 'user_id' => $contributor->id]);

        Livewire::test(EditMediaLibrary::class, ['record' => $media->getRouteKey()])
            ->assertFormFieldIsHidden('photographer_id');
    }

    public function test_contributor_cannot_see_photographer_table_column(): void
    {
        $this->actingAsContributor();

        Livewire::test(ListMediaLibraries::class)
            ->assertTableColumnHidden('photographer.name');
    }

    public function test_owner_can_see_photographer_table_column(): void
    {
        $this->actingAsOwner();

        Livewire::test(ListMediaLibraries::class)
            ->assertTableColumnVisible('photographer.name');
    }

    public function test_contributor_cannot_see_photographer_filter(): void
    {
        $this->actingAsContributor();

        Livewire::test(ListMediaLibraries::class)
            ->assertTableFilterHidden('photographer_id');
    }

    public function test_owner_can_see_photographer_filter(): void
    {
        $this->actingAsOwner();

        Livewire::test(ListMediaLibraries::class)
            ->assertTableFilterVisible('photographer_id');
    }

    public function test_contributor_cannot_reach_the_assign_photographer_bulk_action(): void
    {
        $this->actingAsContributor();

        Livewire::test(ListMediaLibraries::class)
            ->assertTableBulkActionHidden('assignPhotographer');
    }

    public function test_owner_can_reach_the_assign_photographer_bulk_action(): void
    {
        $this->actingAsOwner();

        Livewire::test(ListMediaLibraries::class)
            ->assertTableBulkActionVisible('assignPhotographer');
    }

    /**
     * The hidden/disabled distinction matters: a hidden bulk action must also
     * refuse to run if invoked directly (e.g. a replayed Livewire payload),
     * not just be absent from the UI. `callTableBulkAction()` (the test
     * helper) asserts visibility itself before dispatching, which would mask
     * exactly the gap this test exists to catch — so this calls the
     * underlying Livewire component methods directly instead, the same way a
     * forged `wire:click` payload would, bypassing the helper's own check.
     * `->authorize()` feeds `isHidden()` -> `isDisabled()`, and both
     * `mountTableBulkAction()` and `callMountedTableBulkAction()` refuse to
     * run a disabled action.
     */
    public function test_contributor_cannot_execute_the_assign_photographer_bulk_action_directly(): void
    {
        $contributor = $this->actingAsContributor();
        $photographer = Photographer::factory()->create();
        $media = MediaLibrary::create(['name' => 'Mine', 'user_id' => $contributor->id]);

        Livewire::test(ListMediaLibraries::class)
            ->call('mountTableBulkAction', 'assignPhotographer', [$media->id])
            ->set('mountedTableBulkActionData.photographer_id', $photographer->id)
            ->call('callMountedTableBulkAction');

        $this->assertNull($media->fresh()->photographer_id);
    }
}
