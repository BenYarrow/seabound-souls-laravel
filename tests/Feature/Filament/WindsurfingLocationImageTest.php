<?php

// Feature test: the windsurfing-locations repeater on the spot-guide form saves
// a per-location image (thumbnail_media_id) and orders rows via orderColumn —
// no manual sort_order field.

namespace Tests\Feature\Filament;

use App\Filament\Resources\SpotGuideResource\Pages\CreateSpotGuide;
use App\Models\Country;
use App\Models\MediaLibrary;
use App\Models\SpotGuide;
use App\Models\WindsurfingLocation;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WindsurfingLocationImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsOwner();
    }

    public function test_windsurfing_locations_save_their_image_and_get_ordered(): void
    {
        Queue::fake(); // swallow the create-hook weather job

        $imgA = MediaLibrary::create(['name' => 'Spot A image'])->id;
        $imgB = MediaLibrary::create(['name' => 'Spot B image'])->id;

        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'Repeater Bay',
                'slug' => 'repeater-bay',
                'country_id' => Country::factory()->create()->id,
                'latitude' => 38.6,
                'longitude' => 20.6,
                'windsurfingLocations' => [
                    ['name' => 'North Reef', 'thumbnail_media_id' => $imgA],
                    ['name' => 'South Bay', 'thumbnail_media_id' => $imgB],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $spot = SpotGuide::where('slug', 'repeater-bay')->firstOrFail();
        $locations = WindsurfingLocation::where('spot_guide_id', $spot->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $locations);
        // The image field is wired: before this change the repeater had no image
        // picker, so thumbnail_media_id would not be collected/saved.
        $this->assertSame($imgA, $locations[0]->thumbnail_media_id);
        $this->assertSame($imgB, $locations[1]->thumbnail_media_id);
        // orderColumn assigns a distinct order by entry position without a manual field.
        $this->assertNotSame($locations[0]->sort_order, $locations[1]->sort_order);
        $this->assertSame('North Reef', $locations[0]->name);
        $this->assertSame('South Bay', $locations[1]->name);
    }
}
