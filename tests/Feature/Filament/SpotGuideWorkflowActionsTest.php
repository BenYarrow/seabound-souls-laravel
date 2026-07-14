<?php

// Covers the review-workflow header actions on the Edit Spot Guide Filament page:
// contributor "submit for review" (notifies owners), owner "publish" and "request changes"
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
use PHPUnit\Framework\ExpectationFailedException;
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

    public function test_contributor_editing_an_approved_guide_returns_it_to_review_and_notifies_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'email' => config('admin.email')]);
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor);
        $guide->publish(); // approved + live

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->fillForm(['introduction_text' => 'Updated by the contributor'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $guide->fresh();
        $this->assertSame(SpotGuide::STATUS_IN_REVIEW, $fresh->review_status);
        $this->assertTrue($fresh->is_published); // stays live
        Notification::assertSentTo($owner, GuideSubmittedForReview::class);
    }

    public function test_owner_editing_an_approved_guide_does_not_flip_status(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $this->actingAsOwner();
        $guide = $this->guideFor($contributor);
        $guide->publish();

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->fillForm(['introduction_text' => 'Owner tweak'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SpotGuide::STATUS_APPROVED, $guide->fresh()->review_status);
    }

    public function test_contributor_editing_a_draft_guide_does_not_flip_to_in_review(): void
    {
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor); // draft

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->fillForm(['introduction_text' => 'Still drafting'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SpotGuide::STATUS_DRAFT, $guide->fresh()->review_status);
    }

    public function test_a_contributor_cannot_feature_a_guide_via_any_write_path(): void
    {
        // Featuring is an owner-only editorial decision. The form field and the
        // inline list toggle are hidden from contributors, but the model must also
        // refuse a direct/crafted save (the toggle is a non-form write path).
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor);

        $guide->is_featured = true;
        $guide->save();

        $this->assertFalse($guide->fresh()->is_featured);
    }

    public function test_an_owner_can_feature_a_guide(): void
    {
        $this->actingAsOwner();
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $guide = $this->guideFor($contributor);

        $guide->is_featured = true;
        $guide->save();

        $this->assertTrue($guide->fresh()->is_featured);
    }

    public function test_contributor_submit_action_advances_status_and_notifies_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'email' => config('admin.email')]);
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->callAction('submit')
            ->assertHasNoActionErrors();

        $this->assertSame(SpotGuide::STATUS_IN_REVIEW, $guide->fresh()->review_status);
        Notification::assertSentTo($owner, GuideSubmittedForReview::class);
    }

    public function test_contributor_does_not_see_publish_action(): void
    {
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->assertActionHidden('publish');
    }

    public function test_owner_publish_action_publishes_and_notifies_author(): void
    {
        Notification::fake();
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $owner = $this->actingAsOwner();
        $guide = $this->guideFor($contributor);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->callAction('publish')
            ->assertHasNoActionErrors();

        $this->assertTrue($guide->fresh()->is_published);
        Notification::assertSentTo($contributor, GuidePublished::class);
    }

    public function test_owner_request_changes_stores_note_and_notifies_author(): void
    {
        Notification::fake();
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $this->actingAsOwner();
        $guide = $this->guideFor($contributor);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->callAction('requestChanges', data: ['note' => 'Add wind stats'])
            ->assertHasNoActionErrors();

        $this->assertSame(SpotGuide::STATUS_CHANGES_REQUESTED, $guide->fresh()->review_status);
        $this->assertSame('Add wind stats', $guide->fresh()->review_note);
        Notification::assertSentTo($contributor, GuideChangesRequested::class);
    }

    /**
     * Security regression lock: the `publish` action is only guarded by
     * ->visible(owner) — there is no ->authorize() tied to the update policy.
     * Filament's Livewire test helper independently asserts the action is
     * visible before invoking it, so calling it as a contributor on their own guide
     * throws an ExpectationFailedException rather than silently publishing.
     * Whichever way it fails, the record must never end up published.
     */
    public function test_contributor_cannot_publish_their_own_guide_via_the_publish_action(): void
    {
        Notification::fake();
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor);

        try {
            Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
                ->callAction('publish');
        } catch (ExpectationFailedException $exception) {
            // Expected: Filament's test helper refuses to invoke a hidden action.
        }

        $this->assertFalse($guide->fresh()->is_published);
        Notification::assertNothingSent();
    }

    /**
     * Security regression lock: `is_published` is only present in the form
     * schema when the acting user is the owner (Toggle::visible(owner) in
     * SpotGuideResource::form()). A contributor's form simply never carries the
     * field, so attempting to set it via fillForm() has no effect — the
     * saved record must stay unpublished either way.
     */
    public function test_contributor_cannot_set_is_published_via_the_edit_form(): void
    {
        $contributor = $this->actingAsContributor();
        $guide = $this->guideFor($contributor);

        Livewire::test(EditSpotGuide::class, ['record' => $guide->getRouteKey()])
            ->fillForm([
                'title' => 'Bay Updated',
                'slug' => 'bay',
                'is_published' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($guide->fresh()->is_published);
    }
}
