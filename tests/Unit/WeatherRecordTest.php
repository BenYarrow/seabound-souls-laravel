<?php

// Unit tests for App\Models\WeatherRecord: the month_name accessor and the
// forYear / forMonth query scopes.

namespace Tests\Unit;

use App\Models\WeatherRecord;
use Tests\TestCase;

class WeatherRecordTest extends TestCase
{
    public function test_month_name_accessor_maps_the_month_number(): void
    {
        $record = WeatherRecord::factory()->create(['month' => 7]);

        $this->assertSame('July', $record->month_name);
    }

    public function test_month_name_is_empty_for_an_out_of_range_month(): void
    {
        $record = WeatherRecord::factory()->make(['month' => 0]);

        $this->assertSame('', $record->month_name);
    }

    public function test_for_year_and_for_month_scopes_filter(): void
    {
        $guide = \App\Models\SpotGuide::factory()->create();
        WeatherRecord::factory()->for($guide)->create(['year' => 2022, 'month' => 4]);
        WeatherRecord::factory()->for($guide)->create(['year' => 2023, 'month' => 4]);
        WeatherRecord::factory()->for($guide)->create(['year' => 2023, 'month' => 8]);

        $this->assertCount(2, WeatherRecord::forYear(2023)->get());
        $this->assertCount(1, WeatherRecord::forYear(2023)->forMonth(8)->get());
    }
}
