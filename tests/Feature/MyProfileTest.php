<?php

// Tests the self-service profile editing: the MyProfile page binds to the
// authenticated user, and contributor profile fields persist through a save.

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_profile_page_binds_to_the_authenticated_user(): void
    {
        $contributor = User::factory()->contributor()->create();
        $this->actingAs($contributor);

        $page = new MyProfile;
        $this->assertSame($contributor->id, $page->resolveRecord()->id);
    }

    public function test_profile_fields_persist(): void
    {
        $contributor = User::factory()->contributor()->create();

        $contributor->update([
            'socials' => ['instagram' => 'https://instagram.com/x'],
            'profile_blocks' => [['type' => 'rich_text', 'data' => []]],
        ]);

        $this->assertDatabaseHas('users', ['id' => $contributor->id]);
        $this->assertSame('https://instagram.com/x', $contributor->fresh()->socials['instagram']);
    }
}
