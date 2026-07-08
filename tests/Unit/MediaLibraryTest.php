<?php

// Unit tests for App\Models\MediaLibrary — focal-point defaults, cast, and the
// imagePayload() shape consumed across the app.

namespace Tests\Unit;

use App\Models\MediaLibrary;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    public function test_focal_point_defaults_to_centre(): void
    {
        $media = MediaLibrary::create(['name' => 'Sunset']);

        $this->assertSame(50, $media->fresh()->focal_x);
        $this->assertSame(50, $media->fresh()->focal_y);
    }

    public function test_image_payload_has_the_expected_shape(): void
    {
        $media = MediaLibrary::create(['name' => 'Sunset', 'focal_x' => 30, 'focal_y' => 70]);

        $payload = $media->imagePayload();

        $this->assertSame(['url', 'alt', 'focal_x', 'focal_y'], array_keys($payload));
        $this->assertSame('Sunset', $payload['alt']);
        $this->assertSame(30, $payload['focal_x']);
        $this->assertSame(70, $payload['focal_y']);
        $this->assertIsString($payload['url']); // '' when no file attached — fine
    }
}
