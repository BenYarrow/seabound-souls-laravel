<?php

// Verifies media library ownership scoping: riders only ever see/manage their
// own uploads (never house media, user_id null), owners see everything.

namespace Tests\Feature\Filament;

use App\Filament\Resources\MediaLibraryResource;
use App\Models\MediaLibrary;
use App\Models\User;
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
}
