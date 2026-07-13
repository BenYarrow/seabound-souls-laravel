<?php

// Tests for the MapCoordinatePicker custom Filament field: (1) the pure static
// sibling-path derivation used to write coords into a row's lat/lng fields, and
// (2) that the spot-guide create form still saves coordinates with the picker
// present alongside the plain latitude/longitude inputs.

namespace Tests\Feature\Filament;

use App\Filament\Forms\Components\MapCoordinatePicker;
use App\Filament\Resources\SpotGuideResource\Pages\CreateSpotGuide;
use App\Models\Country;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MapCoordinatePickerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsOwner();
    }

    public function test_sibling_path_is_derived_from_a_state_path(): void
    {
        // Nested repeater row: sibling shares the row's parent path.
        $this->assertSame(
            'data.windsurfingLocations.abc.latitude',
            MapCoordinatePicker::siblingPathFor('data.windsurfingLocations.abc.location_coords', 'latitude'),
        );

        // No parent (bare name): sibling is just the sibling name.
        $this->assertSame('longitude', MapCoordinatePicker::siblingPathFor('spot_coords', 'longitude'));
    }

    public function test_form_still_saves_coordinates_with_the_picker_present(): void
    {
        Queue::fake();

        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'Picker Bay',
                'slug' => 'picker-bay',
                'country_id' => Country::factory()->create()->id,
                'latitude' => 28.1,
                'longitude' => -15.42,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('spot_guides', ['slug' => 'picker-bay', 'latitude' => 28.1]);
    }
}
