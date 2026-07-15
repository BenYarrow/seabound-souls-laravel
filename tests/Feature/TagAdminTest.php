<?php

// Verifies the Blog Tags admin surface at the layer that matters: the
// owner/contributor policy gate (security-critical) and pivot persistence
// via Blog::tags() — not brittle Livewire form interaction.

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_may_manage_tags(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->assertTrue($owner->can('viewAny', Tag::class));
        $this->assertTrue($owner->can('create', Tag::class));
    }

    public function test_contributor_may_not_manage_tags(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);

        $this->assertFalse($contributor->can('viewAny', Tag::class));
        $this->assertFalse($contributor->can('create', Tag::class));
    }

    public function test_blog_tag_assignment_persists_to_the_pivot(): void
    {
        $blog = Blog::factory()->create();
        $tag = Tag::factory()->create();

        $blog->tags()->sync([$tag->id]);

        $this->assertDatabaseHas('blog_tag', ['blog_id' => $blog->id, 'tag_id' => $tag->id]);
    }
}
