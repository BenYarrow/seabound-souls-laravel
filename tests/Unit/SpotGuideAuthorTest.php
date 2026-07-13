<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotGuideAuthorTest extends TestCase
{
    public function test_author_is_set_to_the_authenticated_user_on_create(): void
    {
        Queue::fake(); // suppress the weather-fetch job
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $this->actingAs($rider);

        $guide = SpotGuide::create([
            'title' => 'Pozo', 'slug' => 'pozo',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 27.8, 'longitude' => -15.4,
        ]);

        $this->assertSame($rider->id, $guide->fresh()->user_id);
        $this->assertTrue($guide->author->is($rider));
        $this->assertTrue($rider->authoredSpotGuides->contains($guide));
    }

    public function test_explicit_author_is_not_overwritten(): void
    {
        Queue::fake();
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $this->actingAsOwner();

        $guide = SpotGuide::create([
            'title' => 'Vass', 'slug' => 'vass',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 38.6, 'longitude' => 20.6,
            'user_id' => $rider->id,
        ]);

        $this->assertSame($rider->id, $guide->fresh()->user_id);
    }
}
