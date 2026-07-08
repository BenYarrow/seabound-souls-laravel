<?php

// Feature tests for the focal-point save endpoint — POST /admin/media/{media}/focal.
// Verifies that the click-to-set interaction in the MediaPicker preview card can
// persist focal_x/focal_y via an auth-gated JSON route.

namespace Tests\Feature\Filament;

use App\Models\MediaLibrary;
use App\Models\User;
use Tests\TestCase;

class MediaPickerFocalTest extends TestCase
{
    public function test_focal_point_can_be_saved_for_a_media_item(): void
    {
        $this->actingAs(User::factory()->create());
        $media = MediaLibrary::create(['name' => 'Hero']);

        $this->postJson("/admin/media/{$media->id}/focal", ['x' => 25, 'y' => 75])
            ->assertOk();

        $media->refresh();
        $this->assertSame(25, $media->focal_x);
        $this->assertSame(75, $media->focal_y);
    }

    public function test_focal_endpoint_requires_auth(): void
    {
        $media = MediaLibrary::create(['name' => 'Hero']);
        $this->postJson("/admin/media/{$media->id}/focal", ['x' => 25, 'y' => 75])
            ->assertUnauthorized();
    }

    public function test_focal_values_are_clamped_to_0_100(): void
    {
        $this->actingAs(User::factory()->create());
        $media = MediaLibrary::create(['name' => 'Hero']);
        $this->postJson("/admin/media/{$media->id}/focal", ['x' => 250, 'y' => -5])
            ->assertUnprocessable();
    }
}
