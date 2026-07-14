<?php

// Feature tests for the owner-only Contributors admin section: access gating, the
// list scoped to contributor accounts, the Invite Contributor action, and the per-contributor
// panel listing that contributor's authored spot guides.

namespace Tests\Feature\Filament;

use App\Filament\Resources\ContributorResource;
use App\Filament\Resources\ContributorResource\Pages\EditContributor;
use App\Filament\Resources\ContributorResource\Pages\ListContributors;
use App\Filament\Resources\ContributorResource\RelationManagers\SpotGuidesRelationManager;
use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ContributorResourceTest extends TestCase
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

    public function test_resource_labels_read_as_contributors_not_users(): void
    {
        // Titles/breadcrumbs must say Contributor(s), not User(s) (the underlying model).
        $this->assertSame('contributor', ContributorResource::getModelLabel());
        $this->assertSame('contributors', ContributorResource::getPluralModelLabel());
    }

    public function test_owner_can_view_the_contributors_resource_but_a_contributor_cannot(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);

        $this->assertTrue($owner->can('viewAny', User::class));
        $this->assertFalse($contributor->can('viewAny', User::class));
    }

    public function test_contributors_list_is_scoped_to_contributor_accounts(): void
    {
        $this->actingAsOwner();
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);

        $roles = ContributorResource::getEloquentQuery()->pluck('role')->unique()->values()->all();

        $this->assertSame([User::ROLE_CONTRIBUTOR], $roles);
    }

    public function test_invite_contributor_action_creates_a_contributor_with_split_name(): void
    {
        $this->actingAsOwner();

        Livewire::test(ListContributors::class)
            ->callAction('inviteContributor', data: [
                'first_name' => 'Ada',
                'last_name' => 'Windsurfer',
                'email' => 'ada@example.com',
            ])
            ->assertHasNoActionErrors();

        // first/last stored, and `name` synced from them by the User saving hook.
        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
            'role' => User::ROLE_CONTRIBUTOR,
            'first_name' => 'Ada',
            'last_name' => 'Windsurfer',
            'name' => 'Ada Windsurfer',
        ]);
    }

    public function test_per_contributor_panel_lists_only_that_contributors_guides(): void
    {
        $this->actingAsOwner();
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $otherContributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $mine = $this->guideFor($contributor, 'my-spot');
        $theirs = $this->guideFor($otherContributor, 'their-spot');

        Livewire::test(SpotGuidesRelationManager::class, [
            'ownerRecord' => $contributor,
            'pageClass' => EditContributor::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }
}
