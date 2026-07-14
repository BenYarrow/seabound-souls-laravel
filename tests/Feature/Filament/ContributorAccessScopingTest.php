<?php

// Verifies the contributor/owner access-control layer: SpotGuideResource query scoping,
// SpotGuide/Country policy rules (contributor own-only + publish-lock), and that house-only
// resources (Blog/Page/ContactEnquiry) stay hidden from contributors.

namespace Tests\Feature\Filament;

use App\Filament\Resources\SpotGuideResource;
use App\Models\Blog;
use App\Models\ContactEnquiry;
use App\Models\Country;
use App\Models\Page;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContributorAccessScopingTest extends TestCase
{
    private function guideOwnedBy(User $user): SpotGuide
    {
        Queue::fake();
        // A static per-process counter (not per-user) keeps the slug unique even when
        // a single test calls this twice for the same contributor (two guides, one owner).
        static $sequence = 0;
        $sequence++;

        return SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => 'G' . $user->id . '-' . $sequence, 'slug' => 'g' . $user->id . '-' . $sequence,
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1, 'user_id' => $user->id,
        ]));
    }

    public function test_contributor_query_only_returns_their_guides(): void
    {
        $contributor = $this->actingAsContributor();
        $mine = $this->guideOwnedBy($contributor);
        $theirs = $this->guideOwnedBy(User::factory()->create());

        $ids = SpotGuideResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_contributor_cannot_update_or_delete_others_guides(): void
    {
        $contributor = $this->actingAsContributor();
        $theirs = $this->guideOwnedBy(User::factory()->create());

        $this->assertFalse($contributor->can('update', $theirs));
        $this->assertFalse($contributor->can('delete', $theirs));
    }

    public function test_contributor_cannot_delete_their_own_published_guide(): void
    {
        $contributor = $this->actingAsContributor();
        $mine = $this->guideOwnedBy($contributor);
        $mine->update(['is_published' => true]);

        $this->assertTrue($contributor->can('delete', $this->guideOwnedBy($contributor))); // unpublished own
        $this->assertFalse($contributor->can('delete', $mine->fresh()));             // published own
    }

    public function test_owner_can_do_everything(): void
    {
        $owner = $this->actingAsOwner();
        $theirs = $this->guideOwnedBy(User::factory()->create());
        $theirs->update(['is_published' => true]);

        $this->assertTrue($owner->can('update', $theirs));
        $this->assertTrue($owner->can('delete', $theirs->fresh()));
    }

    public function test_contributors_are_blocked_from_house_only_resources(): void
    {
        $contributor = $this->actingAsContributor();

        $this->assertFalse($contributor->can('viewAny', Blog::class));
        $this->assertFalse($contributor->can('viewAny', Page::class));
        $this->assertFalse($contributor->can('viewAny', ContactEnquiry::class));
    }

    public function test_contributor_can_create_country_but_not_edit_existing(): void
    {
        $contributor = $this->actingAsContributor();
        $existing = Country::factory()->create();

        $this->assertTrue($contributor->can('create', Country::class));
        $this->assertFalse($contributor->can('update', $existing));
        $this->assertFalse($contributor->can('delete', $existing));
    }
}
