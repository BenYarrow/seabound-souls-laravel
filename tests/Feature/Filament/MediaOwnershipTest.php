<?php

// Verifies media library ownership scoping: contributors only ever see/manage their
// own uploads (never house media, user_id null), owners see everything.

namespace Tests\Feature\Filament;

use App\Filament\Resources\MediaLibraryResource;
use App\Filament\Resources\MediaLibraryResource\Pages\CreateMediaLibrary;
use App\Livewire\MediaPickerBrowser;
use App\Models\MediaLibrary;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaOwnershipTest extends TestCase
{
    public function test_image_file_is_required_when_creating_media(): void
    {
        $this->actingAsOwner();

        Livewire::test(CreateMediaLibrary::class)
            ->fillForm(['name' => 'No image'])
            ->call('create')
            ->assertHasFormErrors(['file' => 'required']);

        $this->assertDatabaseMissing('media_library', ['name' => 'No image']);
    }

    public function test_media_created_by_a_contributor_via_the_resource_is_owned_by_them(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $contributor = $this->actingAsContributor();

        Livewire::test(CreateMediaLibrary::class)
            ->fillForm(['name' => 'My upload', 'file' => [UploadedFile::fake()->image('mine.jpg')]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('media_library', [
            'name' => 'My upload',
            'user_id' => $contributor->id,
        ]);
    }

    public function test_media_created_by_the_owner_via_the_resource_is_house_media(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $this->actingAsOwner();

        Livewire::test(CreateMediaLibrary::class)
            ->fillForm(['name' => 'House upload', 'file' => [UploadedFile::fake()->image('house.jpg')]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('media_library', [
            'name' => 'House upload',
            'user_id' => null,
        ]);
    }

    public function test_folder_options_exclude_house_folders_for_contributors(): void
    {
        $contributor = $this->actingAsContributor();
        MediaLibrary::create(['name' => 'h', 'folder' => 'HouseFolder', 'user_id' => null]);
        MediaLibrary::create(['name' => 'o', 'folder' => 'OtherContributor', 'user_id' => User::factory()->create()->id]);
        MediaLibrary::create(['name' => 'm', 'folder' => 'MyFolder', 'user_id' => $contributor->id]);

        $options = MediaLibraryResource::folderOptions();

        $this->assertArrayHasKey('MyFolder', $options);
        $this->assertArrayNotHasKey('HouseFolder', $options);
        $this->assertArrayNotHasKey('OtherContributor', $options);
    }

    public function test_folder_options_show_all_folders_for_the_owner(): void
    {
        $this->actingAsOwner();
        MediaLibrary::create(['name' => 'h', 'folder' => 'HouseFolder', 'user_id' => null]);
        MediaLibrary::create(['name' => 'm', 'folder' => 'ContributorFolder', 'user_id' => User::factory()->create()->id]);

        $options = MediaLibraryResource::folderOptions();

        $this->assertArrayHasKey('HouseFolder', $options);
        $this->assertArrayHasKey('ContributorFolder', $options);
    }

    public function test_contributor_only_sees_their_own_media_in_the_resource_query(): void
    {
        $contributor = $this->actingAsContributor();
        $houseMedia = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $contributor->id]);
        $other = MediaLibrary::create(['name' => 'Theirs', 'user_id' => User::factory()->create()->id]);

        $ids = MediaLibraryResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($houseMedia->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_owner_sees_all_media(): void
    {
        $this->actingAsOwner();
        MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        MediaLibrary::create(['name' => 'Contributor', 'user_id' => User::factory()->create()->id]);

        $this->assertCount(2, MediaLibraryResource::getEloquentQuery()->get());
    }

    public function test_contributor_cannot_view_house_media_via_policy(): void
    {
        $contributor = $this->actingAsContributor();
        $house = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $contributor->id]);

        $this->assertFalse($contributor->can('view', $house));
        $this->assertTrue($contributor->can('view', $mine));
    }

    /**
     * Livewire public methods are directly network-callable regardless of what
     * the client rendered — a contributor could call toggleSelect() with an id the
     * scoped browse query never surfaced (house media, another contributor's media).
     * The component must re-authorize the id itself, not just its render query.
     */
    public function test_contributor_cannot_select_house_or_foreign_media_via_toggle_select(): void
    {
        $contributor = $this->actingAsContributor();
        $house = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $other = MediaLibrary::create(['name' => 'Theirs', 'user_id' => User::factory()->create()->id]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $contributor->id]);

        $component = Livewire::test(MediaPickerBrowser::class, ['multiple' => true]);

        $component->call('toggleSelect', $house->id);
        $this->assertNotContains($house->id, $component->get('selectedIds'));

        $component->call('toggleSelect', $other->id);
        $this->assertNotContains($other->id, $component->get('selectedIds'));

        $component->call('toggleSelect', $mine->id);
        $this->assertContains($mine->id, $component->get('selectedIds'));
    }

    /**
     * Even if selectedIds is somehow pre-seeded/hydrated with out-of-scope ids
     * (e.g. tampered wire:snapshot), confirm() must not dispatch them onward.
     */
    public function test_confirm_only_dispatches_ids_within_the_current_users_scope(): void
    {
        $contributor = $this->actingAsContributor();
        $house = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $contributor->id]);

        $component = Livewire::test(MediaPickerBrowser::class, ['multiple' => true, 'fieldKey' => 'thumbnail_media_id']);
        $component->set('selectedIds', [$house->id, $mine->id]);

        $component->call('confirm');

        $component->assertDispatched('media-library-selected', fieldKey: 'thumbnail_media_id', ids: [$mine->id]);
    }
}
