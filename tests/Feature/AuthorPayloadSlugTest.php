<?php

// Tests that authorPayload() carries the contributor's slug (for linking the
// byline to their profile), and null for house-authored guides.

namespace Tests\Feature;

use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorPayloadSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_guide_payload_includes_the_author_slug(): void
    {
        $contributor = User::factory()->contributor()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        $guide = SpotGuide::factory()->create(['user_id' => $contributor->id]);

        $payload = $guide->authorPayload();

        $this->assertSame('contributor', $payload['kind']);
        $this->assertSame('Jane Smith', $payload['name']);
        $this->assertSame('jane-smith', $payload['slug']);
    }

    public function test_house_guide_payload_has_null_slug(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $guide = SpotGuide::factory()->create(['user_id' => $owner->id]);

        $payload = $guide->authorPayload();

        $this->assertSame('house', $payload['kind']);
        $this->assertNull($payload['slug']);
    }
}
