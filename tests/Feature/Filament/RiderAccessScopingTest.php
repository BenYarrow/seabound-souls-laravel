<?php

// Verifies the rider/owner access-control layer: SpotGuideResource query scoping,
// SpotGuide/Country policy rules (rider own-only + publish-lock), and that house-only
// resources (Blog/Page/ContactEnquiry) stay hidden from riders.

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

class RiderAccessScopingTest extends TestCase
{
    private function guideOwnedBy(User $user): SpotGuide
    {
        Queue::fake();
        // A static per-process counter (not per-user) keeps the slug unique even when
        // a single test calls this twice for the same rider (two guides, one owner).
        static $sequence = 0;
        $sequence++;

        return SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => 'G' . $user->id . '-' . $sequence, 'slug' => 'g' . $user->id . '-' . $sequence,
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1, 'user_id' => $user->id,
        ]));
    }

    public function test_rider_query_only_returns_their_guides(): void
    {
        $rider = $this->actingAsRider();
        $mine = $this->guideOwnedBy($rider);
        $theirs = $this->guideOwnedBy(User::factory()->create());

        $ids = SpotGuideResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_rider_cannot_update_or_delete_others_guides(): void
    {
        $rider = $this->actingAsRider();
        $theirs = $this->guideOwnedBy(User::factory()->create());

        $this->assertFalse($rider->can('update', $theirs));
        $this->assertFalse($rider->can('delete', $theirs));
    }

    public function test_rider_cannot_delete_their_own_published_guide(): void
    {
        $rider = $this->actingAsRider();
        $mine = $this->guideOwnedBy($rider);
        $mine->update(['is_published' => true]);

        $this->assertTrue($rider->can('delete', $this->guideOwnedBy($rider))); // unpublished own
        $this->assertFalse($rider->can('delete', $mine->fresh()));             // published own
    }

    public function test_owner_can_do_everything(): void
    {
        $owner = $this->actingAsOwner();
        $theirs = $this->guideOwnedBy(User::factory()->create());
        $theirs->update(['is_published' => true]);

        $this->assertTrue($owner->can('update', $theirs));
        $this->assertTrue($owner->can('delete', $theirs->fresh()));
    }

    public function test_riders_are_blocked_from_house_only_resources(): void
    {
        $rider = $this->actingAsRider();

        $this->assertFalse($rider->can('viewAny', Blog::class));
        $this->assertFalse($rider->can('viewAny', Page::class));
        $this->assertFalse($rider->can('viewAny', ContactEnquiry::class));
    }

    public function test_rider_can_create_country_but_not_edit_existing(): void
    {
        $rider = $this->actingAsRider();
        $existing = Country::factory()->create();

        $this->assertTrue($rider->can('create', Country::class));
        $this->assertFalse($rider->can('update', $existing));
        $this->assertFalse($rider->can('delete', $existing));
    }
}
