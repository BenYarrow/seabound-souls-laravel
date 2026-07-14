<?php

// Public author attribution: guides carry a house/contributor author payload, and the
// provenance byline flag (showProvenance) turns on only once a published,
// contributor-authored guide exists on the site.

namespace Tests\Feature;

use App\Models\Country;
use App\Models\SpotGuide;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttributionTest extends TestCase
{
    private function publishedGuide(string $slug, ?User $author): SpotGuide
    {
        return SpotGuide::withoutEvents(fn () => SpotGuide::create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1,
            'longitude' => 1,
            'is_published' => true,
            'user_id' => $author?->id,
        ]));
    }

    public function test_destinations_hides_provenance_and_marks_house_when_no_contributor_guides(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->publishedGuide('point-clear', $owner);

        $this->get(route('destinations.index'))->assertInertia(fn (Assert $page) => $page
            ->where('showProvenance', false)
            ->where('spotGuides.0.author.kind', 'house')
            ->where('spotGuides.0.author.name', null)
        );
    }

    public function test_destinations_shows_provenance_when_a_contributor_guide_is_published(): void
    {
        $contributor = User::factory()->create([
            'role' => User::ROLE_CONTRIBUTOR, 'first_name' => 'Jane', 'last_name' => 'Smith', 'name' => 'Jane Smith',
        ]);
        $this->publishedGuide('pozo', $contributor);

        $this->get(route('destinations.index'))->assertInertia(fn (Assert $page) => $page
            ->where('showProvenance', true)
            ->where('spotGuides.0.author.kind', 'contributor')
            ->where('spotGuides.0.author.name', 'Jane Smith')
        );
    }

    public function test_spot_guide_page_carries_author_payload_and_flag(): void
    {
        $contributor = User::factory()->create([
            'role' => User::ROLE_CONTRIBUTOR, 'first_name' => 'Jane', 'last_name' => 'Smith', 'name' => 'Jane Smith',
        ]);
        $this->publishedGuide('vassiliki', $contributor);

        $this->get(route('spot-guides.show', 'vassiliki'))->assertInertia(fn (Assert $page) => $page
            ->where('spotGuide.author.kind', 'contributor')
            ->where('spotGuide.author.name', 'Jane Smith')
            ->where('showProvenance', true)
        );
    }

    public function test_house_spot_guide_page_shows_no_contributor_when_no_contributors_exist(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->publishedGuide('mauritius', $owner);

        $this->get(route('spot-guides.show', 'mauritius'))->assertInertia(fn (Assert $page) => $page
            ->where('spotGuide.author.kind', 'house')
            ->where('showProvenance', false)
        );
    }
}
