<?php

// Feature tests for App\Models\SailableDay — the spot-guide relationship and
// the (spot_guide_id, date) uniqueness constraint.

namespace Tests\Feature;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SailableDayModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_spot_guide_has_many_sailable_days(): void
    {
        $spot = SpotGuide::factory()->create();
        SailableDay::factory()->for($spot)->create([
            'date' => '2025-08-01', 'year' => 2025, 'month' => 8, 'qualifying_wind_kts' => 21.4,
        ]);

        $this->assertCount(1, $spot->refresh()->sailableDays);
        $this->assertSame('21.4', (string) $spot->sailableDays->first()->qualifying_wind_kts);
        $this->assertSame(8, $spot->sailableDays->first()->month);
    }

    public function test_date_and_spot_are_unique_together(): void
    {
        $spot = SpotGuide::factory()->create();
        SailableDay::factory()->for($spot)->create(['date' => '2025-08-01', 'year' => 2025, 'month' => 8]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        SailableDay::factory()->for($spot)->create(['date' => '2025-08-01', 'year' => 2025, 'month' => 8]);
    }
}
