<?php

// The credit reaches the front end through imagePayload() — the single
// serialisation choke point every image on the site flows through. A soft-deleted
// photographer's credit must disappear: note this works via the SoftDeletes
// global scope on the relation, NOT via the FK's nullOnDelete (a database-level
// action that only fires on a hard delete).

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerCreditPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_payload_carries_no_credit_without_a_photographer(): void
    {
        $media = MediaLibrary::create(['name' => 'House shot']);

        $this->assertNull($media->imagePayload()['credit']);
    }

    public function test_image_payload_carries_the_photographer_credit(): void
    {
        $photographer = Photographer::factory()->create([
            'name' => 'Hamish',
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'instagram',
        ]);
        $media = MediaLibrary::create(['name' => 'Tarifa', 'photographer_id' => $photographer->id]);

        $this->assertSame(
            ['name' => 'Hamish', 'url' => 'https://instagram.com/hamish'],
            $media->fresh()->imagePayload()['credit']
        );
    }

    public function test_credit_disappears_when_the_photographer_is_soft_deleted(): void
    {
        $photographer = Photographer::factory()->create();
        $media = MediaLibrary::create(['name' => 'Tarifa', 'photographer_id' => $photographer->id]);

        $photographer->delete();

        $this->assertNull($media->fresh()->imagePayload()['credit']);
    }

    public function test_credit_returns_when_the_photographer_is_restored(): void
    {
        $photographer = Photographer::factory()->create(['name' => 'Hamish']);
        $media = MediaLibrary::create(['name' => 'Tarifa', 'photographer_id' => $photographer->id]);
        $photographer->delete();

        $photographer->restore();

        $this->assertSame('Hamish', $media->fresh()->imagePayload()['credit']['name']);
    }
}
