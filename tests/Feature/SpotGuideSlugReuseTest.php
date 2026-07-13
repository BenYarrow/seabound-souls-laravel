<?php

// Regression: soft-deleting a spot guide must free its slug for reuse (the plain
// unique index used to keep trashed slugs locked), while two LIVE guides still
// cannot share a slug (enforced by a partial unique index on deleted_at IS NULL).

namespace Tests\Feature;

use App\Filament\Resources\SpotGuideResource\Pages\CreateSpotGuide;
use App\Models\Country;
use App\Models\SpotGuide;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SpotGuideSlugReuseTest extends TestCase
{
    private function make(string $slug): SpotGuide
    {
        Queue::fake();

        return SpotGuide::create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'country_id' => Country::factory()->create()->id,
            'latitude' => 1,
            'longitude' => 1,
        ]);
    }

    public function test_slug_is_freed_for_reuse_after_a_soft_delete(): void
    {
        $first = $this->make('reef-break');
        $first->delete(); // soft delete

        $second = $this->make('reef-break'); // must not throw

        $this->assertTrue($second->exists);
        $this->assertSame(2, SpotGuide::withTrashed()->where('slug', 'reef-break')->count());
        $this->assertSame(1, SpotGuide::where('slug', 'reef-break')->count());
    }

    public function test_two_live_guides_cannot_share_a_slug(): void
    {
        $this->make('reef-break');

        $this->expectException(QueryException::class);
        $this->make('reef-break');
    }

    public function test_create_form_accepts_the_slug_of_a_soft_deleted_guide(): void
    {
        $this->actingAsOwner();
        $this->make('reef-break')->delete();

        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'Reef Break',
                'slug' => 'reef-break',
                'country_id' => Country::factory()->create()->id,
                'latitude' => 1,
                'longitude' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
