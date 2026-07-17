<?php

// Tests that the contributor_roll_up content block resolves to only the
// contributors with a public profile (published guide), with their card data.

namespace Tests\Feature;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorRollupBlockTest extends TestCase
{
    use RefreshDatabase;

    /** Expose the protected trait method for testing. */
    private function resolver(): object
    {
        return new class
        {
            use ResolvesContentBlockMedia;

            public function run(array $blocks): array
            {
                return $this->resolveContentBlockMedia($blocks);
            }
        };
    }

    public function test_rollup_resolves_only_public_profile_contributors(): void
    {
        $live = User::factory()->contributor()->create(['first_name' => 'Ann', 'last_name' => 'Live']);
        SpotGuide::factory()->create(['user_id' => $live->id, 'is_published' => true]);

        $draftOnly = User::factory()->contributor()->create(['first_name' => 'Bea', 'last_name' => 'Draft']);
        SpotGuide::factory()->create(['user_id' => $draftOnly->id, 'is_published' => false]);

        $blocks = [['type' => 'contributor_roll_up', 'data' => ['heading' => 'Our Crew']]];
        $resolved = $this->resolver()->run($blocks);

        $cards = $resolved[0]['data']['contributors_resolved'];
        $slugs = array_column($cards, 'slug');

        $this->assertContains('ann-live', $slugs);
        $this->assertNotContains('bea-draft', $slugs);
        $this->assertSame(1, $cards[0]['guides_count']);
    }

    public function test_rollup_excludes_public_profile_contributor_with_null_slug(): void
    {
        $slugless = User::factory()->contributor()->create(['first_name' => 'Cid', 'last_name' => 'Null']);
        SpotGuide::factory()->create(['user_id' => $slugless->id, 'is_published' => true]);
        // Simulate a pre-feature contributor whose slug was never backfilled:
        // bypass the model's saving hook so slug stays null despite a published guide.
        $slugless->forceFill(['slug' => null])->saveQuietly();

        $blocks = [['type' => 'contributor_roll_up', 'data' => ['heading' => 'Our Crew']]];
        $resolved = $this->resolver()->run($blocks);

        $slugs = array_column($resolved[0]['data']['contributors_resolved'], 'slug');

        $this->assertNotContains(null, $slugs);
        $this->assertNotContains('cid-null', $slugs);
    }
}
