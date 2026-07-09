<?php

// Feature test for the dashboard "Fetch all weather" widget button.

namespace Tests\Feature\Filament;

use App\Filament\Widgets\WeatherFetchWidget;
use App\Jobs\FetchAllWeatherJob;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WeatherFetchWidgetTest extends TestCase
{
    public function test_button_dispatches_the_fetch_all_job(): void
    {
        Queue::fake();
        $this->actingAsOwner();

        Livewire::test(WeatherFetchWidget::class)
            ->call('fetchAll');

        Queue::assertPushed(FetchAllWeatherJob::class);
    }

    public function test_last_updated_is_null_when_no_weather_records_exist(): void
    {
        $this->assertNull((new WeatherFetchWidget())->getLastUpdatedAt());
    }

    public function test_last_updated_reflects_the_most_recent_weather_record(): void
    {
        $spot = SpotGuide::factory()->create();
        WeatherRecord::factory()->for($spot)->create();

        // A human-readable "x ago" string, not null, once any record exists.
        $this->assertNotNull((new WeatherFetchWidget())->getLastUpdatedAt());
    }

    public function test_pending_count_counts_queued_weather_fetch_jobs(): void
    {
        // The array/null test queue driver doesn't persist jobs, so insert a row
        // shaped like a queued weather job to exercise the count query directly.
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\FetchAllWeatherJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 0,
            'created_at' => 0,
        ]);

        $this->assertSame(1, (new WeatherFetchWidget())->getPendingCount());
    }

    public function test_fetch_button_is_disabled_and_relabelled_while_a_fetch_is_pending(): void
    {
        $this->actingAsOwner();

        // No pending job → the button is enabled with its normal label.
        Livewire::test(WeatherFetchWidget::class)
            ->assertSee('Fetch all weather')
            ->assertDontSee('Fetch in progress');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\FetchAllWeatherJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 0,
            'created_at' => 0,
        ]);

        // With a job queued → the in-progress label shows (button disabled).
        Livewire::test(WeatherFetchWidget::class)
            ->assertSee('Fetch in progress')
            ->assertDontSee('Fetch all weather');
    }
}
