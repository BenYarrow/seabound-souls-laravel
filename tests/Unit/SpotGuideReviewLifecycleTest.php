<?php

// Unit test: review-lifecycle status transitions on SpotGuide (draft -> in_review ->
// changes_requested / approved), exercised via submitForReview()/publish()/requestChanges().

namespace Tests\Unit;

use App\Models\Country;
use App\Models\SpotGuide;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotGuideReviewLifecycleTest extends TestCase
{
    private function makeGuide(): SpotGuide
    {
        Queue::fake();
        $this->actingAsRider();

        return SpotGuide::create([
            'title' => 'Test Bay', 'slug' => 'test-bay',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1,
        ]);
    }

    public function test_new_guide_starts_as_draft(): void
    {
        $this->assertSame(SpotGuide::STATUS_DRAFT, $this->makeGuide()->review_status);
    }

    public function test_submit_for_review_sets_in_review_and_timestamp(): void
    {
        $guide = $this->makeGuide();
        $guide->submitForReview();

        $this->assertSame(SpotGuide::STATUS_IN_REVIEW, $guide->review_status);
        $this->assertNotNull($guide->submitted_at);
    }

    public function test_publish_marks_published_and_approved(): void
    {
        $guide = $this->makeGuide();
        $guide->publish();

        $this->assertTrue($guide->is_published);
        $this->assertSame(SpotGuide::STATUS_APPROVED, $guide->review_status);
        $this->assertNotNull($guide->published_at);
        $this->assertNotNull($guide->reviewed_at);
    }

    public function test_request_changes_stores_note_and_status(): void
    {
        $guide = $this->makeGuide();
        $guide->requestChanges('Please add wind stats.');

        $this->assertSame(SpotGuide::STATUS_CHANGES_REQUESTED, $guide->review_status);
        $this->assertSame('Please add wind stats.', $guide->review_note);
        $this->assertNotNull($guide->reviewed_at);
    }
}
