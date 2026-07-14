<?php

// Covers SpotGuide's author-assignment behaviour: user_id defaults to the
// authenticated user on create, but an explicitly-passed user_id (e.g. an
// owner creating a guide on a contributor's behalf) is never overwritten.

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
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $this->actingAs($contributor);

        $guide = SpotGuide::create([
            'title' => 'Pozo', 'slug' => 'pozo',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 27.8, 'longitude' => -15.4,
        ]);

        $this->assertSame($contributor->id, $guide->fresh()->user_id);
        $this->assertTrue($guide->author->is($contributor));
        $this->assertTrue($contributor->authoredSpotGuides->contains($guide));
    }

    public function test_explicit_author_is_not_overwritten(): void
    {
        Queue::fake();
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $this->actingAsOwner();

        $guide = SpotGuide::create([
            'title' => 'Vass', 'slug' => 'vass',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 38.6, 'longitude' => 20.6,
            'user_id' => $contributor->id,
        ]);

        $this->assertSame($contributor->id, $guide->fresh()->user_id);
    }
}
