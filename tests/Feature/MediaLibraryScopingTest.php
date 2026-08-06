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

    // The two tests below use a role — 'photographer' — that does not exist
    // anywhere in the application today. That's deliberate: they stand in for
    // "any role added in the future". The whole point of scoping via
    // `! $user->isOwner()` rather than `$user->isContributor()` is that a
    // brand-new role automatically falls into the restricted branch instead
    // of silently falling through to the unscoped, house-media-included
    // query. A test built only from `owner` and `contributor` can never catch
    // a regression back to `isContributor()`, because across just those two
    // roles `isContributor()` and `! isOwner()` are logically equivalent —
    // this fictional third role is what actually exercises the "opt-OUT, not
    // opt-IN" property.
    public function test_future_role_sees_only_their_own_media(): void
    {
        $photographer = User::factory()->create(['role' => 'photographer']);
        MediaLibrary::create(['name' => 'House shot']);
        MediaLibrary::create(['name' => 'Theirs', 'user_id' => $photographer->id]);

        $this->actingAs($photographer);

        $results = MediaLibraryResource::getEloquentQuery()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Theirs', $results->first()->name);
    }

    public function test_future_role_folder_options_exclude_house_folders(): void
    {
        $photographer = User::factory()->create(['role' => 'photographer']);
        MediaLibrary::create(['name' => 'House shot', 'folder' => 'House']);
        MediaLibrary::create(['name' => 'Theirs', 'folder' => 'Theirs', 'user_id' => $photographer->id]);

        $this->actingAs($photographer);

        $this->assertSame(['Theirs' => 'Theirs'], MediaLibraryResource::folderOptions());
    }
}
