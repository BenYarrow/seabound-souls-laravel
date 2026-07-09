<?php

// Feature test: the spot-guide form requires valid coordinates (the weather
// fetch depends on them).

namespace Tests\Feature\Filament;

use App\Filament\Resources\SpotGuideResource\Pages\CreateSpotGuide;
use App\Models\Country;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SpotGuideCoordinatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_coordinates_are_required(): void
    {
        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'No Coords Bay',
                'country_id' => Country::factory()->create()->id,
                'latitude' => null,
                'longitude' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['latitude' => 'required', 'longitude' => 'required']);
    }

    public function test_coordinates_must_be_in_range(): void
    {
        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'Off World Bay',
                'country_id' => Country::factory()->create()->id,
                'latitude' => 200,
                'longitude' => 500,
            ])
            ->call('create')
            ->assertHasFormErrors(['latitude' => 'max', 'longitude' => 'max']);
    }
}
