<?php

// Covers the review-workflow header actions on the Edit Spot Guide Filament page:
// rider "submit for review" (notifies owners), owner "publish" and "request changes"
// (both notify the author), and role-gated visibility of the publish/request-changes
// actions.

namespace Tests\Feature\Filament;

use App\Filament\Resources\SpotGuideResource\Pages\EditSpotGuide;
use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use App\Notifications\GuideChangesRequested;
use App\Notifications\GuidePublished;
use App\Notifications\GuideSubmittedForReview;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SpotGuideWorkflowActionsTest extends TestCase
{
    private function guideFor(User $user): SpotGuide
    {
        Queue::fake();

        return SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => 'Bay', 'slug' => 'bay', 'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1, 'user_id' => $user->id,
        ]));
    }

    public function test_rider_submit_action_advances_status_and_notifies_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'email' => config('admin.email')]);
        $rider = $this->actingAsRider();
        $guide = $this->guideFor($rider);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->callAction('submit')
            ->assertHasNoActionErrors();

        $this->assertSame(SpotGuide::STATUS_IN_REVIEW, $guide->fresh()->review_status);
        Notification::assertSentTo($owner, GuideSubmittedForReview::class);
    }

    public function test_rider_does_not_see_publish_action(): void
    {
        $rider = $this->actingAsRider();
        $guide = $this->guideFor($rider);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->assertActionHidden('publish');
    }

    public function test_owner_publish_action_publishes_and_notifies_author(): void
    {
        Notification::fake();
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $owner = $this->actingAsOwner();
        $guide = $this->guideFor($rider);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->callAction('publish')
            ->assertHasNoActionErrors();

        $this->assertTrue($guide->fresh()->is_published);
        Notification::assertSentTo($rider, GuidePublished::class);
    }

    public function test_owner_request_changes_stores_note_and_notifies_author(): void
    {
        Notification::fake();
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $this->actingAsOwner();
        $guide = $this->guideFor($rider);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->callAction('requestChanges', data: ['note' => 'Add wind stats'])
            ->assertHasNoActionErrors();

        $this->assertSame(SpotGuide::STATUS_CHANGES_REQUESTED, $guide->fresh()->review_status);
        $this->assertSame('Add wind stats', $guide->fresh()->review_note);
        Notification::assertSentTo($rider, GuideChangesRequested::class);
    }
}
