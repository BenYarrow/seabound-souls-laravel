<?php

// Covers the rider-contributor workflow notifications: each class's toDatabase()
// payload shape, and that Laravel's notification pipeline actually delivers to
// the intended recipient (owner) via Notification::fake().

namespace Tests\Feature;

use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use App\Notifications\GuideChangesRequested;
use App\Notifications\GuidePublished;
use App\Notifications\GuideSubmittedForReview;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RiderWorkflowNotificationTest extends TestCase
{
    public function test_notifications_render_a_database_payload(): void
    {
        Queue::fake();
        $rider = User::factory()->create(['role' => User::ROLE_RIDER, 'name' => 'Ada']);
        $this->actingAs($rider);
        $guide = SpotGuide::create([
            'title' => 'Pozo', 'slug' => 'pozo',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1,
        ]);

        $submitted = (new GuideSubmittedForReview($guide))->toDatabase($rider);
        $this->assertArrayHasKey('title', $submitted);
        $this->assertStringContainsString('Pozo', $submitted['body']);

        $changes = (new GuideChangesRequested($guide, 'Add wind stats'))->toDatabase($rider);
        $this->assertStringContainsString('Add wind stats', $changes['body']);

        $published = (new GuidePublished($guide))->toDatabase($rider);
        $this->assertArrayHasKey('title', $published);
    }

    public function test_owner_is_notified_on_delivery(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $this->actingAs($rider);
        $guide = SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => 'X', 'slug' => 'x', 'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1, 'user_id' => $rider->id,
        ]));

        $owner->notify(new GuideSubmittedForReview($guide));

        Notification::assertSentTo($owner, GuideSubmittedForReview::class);
    }
}
