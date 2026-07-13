<?php

// Public preview gate for unpublished spot guides: /destinations/{slug} 404s for
// guests and non-author riders, but returns 200 (with an is_preview prop) for the
// owner or the guide's own author, mirroring the Filament "Preview" action's link.

namespace Tests\Feature;

use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotGuidePreviewTest extends TestCase
{
    private function draftGuide(User $author): SpotGuide
    {
        Queue::fake();
        return SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => 'Secret Bay', 'slug' => 'secret-bay',
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1, 'longitude' => 1,
            'user_id' => $author->id, 'is_published' => false,
        ]));
    }

    public function test_guest_gets_404_for_unpublished_guide(): void
    {
        $this->draftGuide(User::factory()->create());
        $this->get('/destinations/secret-bay')->assertNotFound();
    }

    public function test_non_author_rider_gets_404(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_RIDER]);
        $this->draftGuide($author);
        $this->actingAsRider(); // a different rider
        $this->get('/destinations/secret-bay')->assertNotFound();
    }

    public function test_author_can_preview_their_own_unpublished_guide(): void
    {
        $author = $this->actingAsRider();
        $this->draftGuide($author);
        $this->get('/destinations/secret-bay')->assertOk();
    }

    public function test_owner_can_preview_any_unpublished_guide(): void
    {
        $this->draftGuide(User::factory()->create());
        $this->actingAsOwner();
        $this->get('/destinations/secret-bay')->assertOk();
    }

    public function test_published_guide_is_public(): void
    {
        $guide = $this->draftGuide(User::factory()->create());
        $guide->update(['is_published' => true]);
        $this->get('/destinations/secret-bay')->assertOk();
    }
}
