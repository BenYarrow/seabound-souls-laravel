<?php

// Tests the public contributor profile page: 404 gating (unknown slug, owner,
// contributor without a published guide), the payload shape, filled-only socials,
// and published-only guides.

namespace Tests\Feature;

use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function liveContributor(array $attributes = []): User
    {
        $user = User::factory()->contributor()->create($attributes);
        SpotGuide::factory()->create(['user_id' => $user->id, 'is_published' => true]);

        return $user->refresh();
    }

    public function test_live_profile_renders_with_published_guides(): void
    {
        $user = $this->liveContributor(['first_name' => 'Jane', 'last_name' => 'Smith']);
        SpotGuide::factory()->create(['user_id' => $user->id, 'is_published' => false]); // draft excluded

        $this->get('/contributors/'.$user->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contributors/Show', false)
                ->where('contributor.name', 'Jane Smith')
                ->has('guides', 1));
    }

    public function test_unknown_slug_404s(): void
    {
        $this->get('/contributors/nobody')->assertNotFound();
    }

    public function test_contributor_without_published_guide_404s(): void
    {
        $user = User::factory()->contributor()->create();
        $this->get('/contributors/'.$user->slug)->assertNotFound();
    }

    public function test_owner_has_no_public_profile(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'first_name' => 'The', 'last_name' => 'Owner', 'slug' => 'the-owner']);
        SpotGuide::factory()->create(['user_id' => $owner->id, 'is_published' => true]);

        $this->get('/contributors/the-owner')->assertNotFound();
    }

    public function test_socials_payload_contains_only_filled_entries(): void
    {
        $user = $this->liveContributor([
            'first_name' => 'Sam', 'last_name' => 'Social',
            'socials' => ['instagram' => 'https://instagram.com/sam', 'youtube' => '', 'tiktok' => null],
        ]);

        $this->get('/contributors/'.$user->slug)
            ->assertInertia(fn ($page) => $page
                ->where('contributor.socials', ['instagram' => 'https://instagram.com/sam']));
    }
}
