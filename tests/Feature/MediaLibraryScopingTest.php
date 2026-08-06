<?php

// Media list scoping must be an opt-OUT for the owner, not an opt-IN for
// contributors: a role added later (e.g. a photographer login) must not fall
// through the check and see the whole library including house media.

namespace Tests\Feature;

use App\Filament\Resources\MediaLibraryResource;
use App\Models\MediaLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_all_media(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        MediaLibrary::create(['name' => 'House shot']);
        MediaLibrary::create(['name' => 'Contributor shot', 'user_id' => $owner->id]);

        $this->actingAs($owner);

        $this->assertSame(2, MediaLibraryResource::getEloquentQuery()->count());
    }

    public function test_non_owner_sees_only_their_own_media(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        MediaLibrary::create(['name' => 'House shot']);
        MediaLibrary::create(['name' => 'Theirs', 'user_id' => $contributor->id]);

        $this->actingAs($contributor);

        $results = MediaLibraryResource::getEloquentQuery()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Theirs', $results->first()->name);
    }

    public function test_folder_options_exclude_house_folders_for_non_owners(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        MediaLibrary::create(['name' => 'House shot', 'folder' => 'House']);
        MediaLibrary::create(['name' => 'Theirs', 'folder' => 'Theirs', 'user_id' => $contributor->id]);

        $this->actingAs($contributor);

        $this->assertSame(['Theirs' => 'Theirs'], MediaLibraryResource::folderOptions());
    }
}
