<?php

// Verifies media library ownership scoping: riders only ever see/manage their
// own uploads (never house media, user_id null), owners see everything.

namespace Tests\Feature\Filament;

use App\Filament\Resources\MediaLibraryResource;
use App\Livewire\MediaPickerBrowser;
use App\Models\MediaLibrary;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class MediaOwnershipTest extends TestCase
{
    public function test_rider_only_sees_their_own_media_in_the_resource_query(): void
    {
        $rider = $this->actingAsRider();
        $houseMedia = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $rider->id]);
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
        MediaLibrary::create(['name' => 'Rider', 'user_id' => User::factory()->create()->id]);

        $this->assertCount(2, MediaLibraryResource::getEloquentQuery()->get());
    }

    public function test_rider_cannot_view_house_media_via_policy(): void
    {
        $rider = $this->actingAsRider();
        $house = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $rider->id]);

        $this->assertFalse($rider->can('view', $house));
        $this->assertTrue($rider->can('view', $mine));
    }

    /**
     * Livewire public methods are directly network-callable regardless of what
     * the client rendered — a rider could call toggleSelect() with an id the
     * scoped browse query never surfaced (house media, another rider's media).
     * The component must re-authorize the id itself, not just its render query.
     */
    public function test_rider_cannot_select_house_or_foreign_media_via_toggle_select(): void
    {
        $rider = $this->actingAsRider();
        $house = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $other = MediaLibrary::create(['name' => 'Theirs', 'user_id' => User::factory()->create()->id]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $rider->id]);

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
        $rider = $this->actingAsRider();
        $house = MediaLibrary::create(['name' => 'House', 'user_id' => null]);
        $mine = MediaLibrary::create(['name' => 'Mine', 'user_id' => $rider->id]);

        $component = Livewire::test(MediaPickerBrowser::class, ['multiple' => true, 'fieldKey' => 'thumbnail_media_id']);
        $component->set('selectedIds', [$house->id, $mine->id]);

        $component->call('confirm');

        $component->assertDispatched('media-library-selected', fieldKey: 'thumbnail_media_id', ids: [$mine->id]);
    }
}
