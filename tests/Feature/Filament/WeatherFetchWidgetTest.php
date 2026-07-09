<?php

// Feature test for the dashboard "Fetch all weather" widget button.

namespace Tests\Feature\Filament;

use App\Filament\Widgets\WeatherFetchWidget;
use App\Jobs\FetchAllWeatherJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WeatherFetchWidgetTest extends TestCase
{
    public function test_button_dispatches_the_fetch_all_job(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        Livewire::test(WeatherFetchWidget::class)
            ->call('fetchAll');

        Queue::assertPushed(FetchAllWeatherJob::class);
    }
}
