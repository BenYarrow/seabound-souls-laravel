<?php

// Tests contributor profile fields on the User model: slug generation from
// first+last (collision-safe, contributors only), derived public-profile
// visibility, casts, and media relations.

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\SpotGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorProfileModelTest extends TestCase
{
    use RefreshDatabase;

    private function contributor(string $first, string $last): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CONTRIBUTOR,
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    public function test_slug_is_generated_from_first_and_last_name(): void
    {
        $user = $this->contributor('Jane', 'Smith');
        $this->assertSame('jane-smith', $user->slug);
    }

    public function test_slug_collision_gets_a_numeric_suffix(): void
    {
        $this->contributor('Jane', 'Smith');
        $second = $this->contributor('Jane', 'Smith');
        $this->assertSame('jane-smith-2', $second->slug);
    }

    public function test_owner_gets_no_slug(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'first_name' => null, 'last_name' => null]);
        $this->assertNull($owner->slug);
    }

    public function test_has_public_profile_only_when_contributor_has_a_published_guide(): void
    {
        $withPublished = $this->contributor('Ann', 'Long');
        SpotGuide::factory()->create(['user_id' => $withPublished->id, 'is_published' => true]);

        $draftOnly = $this->contributor('Bea', 'Short');
        SpotGuide::factory()->create(['user_id' => $draftOnly->id, 'is_published' => false]);

        $none = $this->contributor('Cass', 'None');

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        SpotGuide::factory()->create(['user_id' => $owner->id, 'is_published' => true]);

        $this->assertTrue($withPublished->hasPublicProfile());
        $this->assertFalse($draftOnly->hasPublicProfile());
        $this->assertFalse($none->hasPublicProfile());
        $this->assertFalse($owner->hasPublicProfile());
    }

    public function test_with_public_profile_scope_matches_has_public_profile(): void
    {
        $live = $this->contributor('Dee', 'Live');
        SpotGuide::factory()->create(['user_id' => $live->id, 'is_published' => true]);
        $this->contributor('Eve', 'Empty');

        $slugs = User::withPublicProfile()->pluck('slug');
        $this->assertTrue($slugs->contains('dee-live'));
        $this->assertFalse($slugs->contains('eve-empty'));
    }

    public function test_socials_and_profile_blocks_cast_to_array_and_media_relations_resolve(): void
    {
        $image = MediaLibrary::create(['name' => 'Portrait']);
        $masthead = MediaLibrary::create(['name' => 'Hero']);
        $user = User::factory()->create([
            'role' => User::ROLE_CONTRIBUTOR,
            'first_name' => 'Fay',
            'last_name' => 'Media',
            'socials' => ['instagram' => 'https://instagram.com/fay'],
            'profile_blocks' => [['type' => 'rich_text', 'data' => []]],
            'profile_image_media_id' => $image->id,
            'static_masthead_media_id' => $masthead->id,
        ]);

        $this->assertIsArray($user->socials);
        $this->assertIsArray($user->profile_blocks);
        $this->assertTrue($user->profileImageMedia->is($image));
        $this->assertTrue($user->staticMastheadMedia->is($masthead));
    }
}
