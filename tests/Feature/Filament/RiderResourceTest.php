<?php

// Feature tests for the owner-only Riders admin section: access gating, the
// list scoped to rider accounts, the Invite Rider action, and the per-rider
// panel listing that rider's authored spot guides.

namespace Tests\Feature\Filament;

use App\Filament\Resources\RiderResource;
use App\Filament\Resources\RiderResource\Pages\EditRider;
use App\Filament\Resources\RiderResource\Pages\ListRiders;
use App\Filament\Resources\RiderResource\RelationManagers\SpotGuidesRelationManager;
use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class RiderResourceTest extends TestCase
{
    /** Create a spot guide authored by the given user (weather job suppressed). */
    private function guideFor(User $author, string $slug): SpotGuide
    {
        Queue::fake();

        return SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1,
            'longitude' => 1,
            'user_id' => $author->id,
        ]));
    }

    public function test_resource_labels_read_as_riders_not_users(): void
    {
        // Titles/breadcrumbs must say Rider(s), not User(s) (the underlying model).
        $this->assertSame('rider', RiderResource::getModelLabel());
        $this->assertSame('riders', RiderResource::getPluralModelLabel());
    }

    public function test_owner_can_view_the_riders_resource_but_a_rider_cannot(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);

        $this->assertTrue($owner->can('viewAny', User::class));
        $this->assertFalse($rider->can('viewAny', User::class));
    }

    public function test_riders_list_is_scoped_to_rider_accounts(): void
    {
        $this->actingAsOwner();
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);

        $roles = RiderResource::getEloquentQuery()->pluck('role')->unique()->values()->all();

        $this->assertSame([User::ROLE_RIDER], $roles);
    }

    public function test_invite_rider_action_creates_a_rider_account(): void
    {
        $this->actingAsOwner();

        Livewire::test(ListRiders::class)
            ->callAction('inviteRider', data: [
                'name' => 'Ada Windsurfer',
                'email' => 'ada@example.com',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
            'role' => User::ROLE_RIDER,
        ]);
    }

    public function test_per_rider_panel_lists_only_that_riders_guides(): void
    {
        $this->actingAsOwner();
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $otherRider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $mine = $this->guideFor($rider, 'my-spot');
        $theirs = $this->guideFor($otherRider, 'their-spot');

        Livewire::test(SpotGuidesRelationManager::class, [
            'ownerRecord' => $rider,
            'pageClass' => EditRider::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }
}
