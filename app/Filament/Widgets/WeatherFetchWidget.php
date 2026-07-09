<?php

// Dashboard widget: a single "Fetch all weather" button that queues a refresh
// of every spot guide's weather data. Gives the admin an on-demand trigger
// instead of waiting for the weekly scheduled command.

namespace App\Filament\Widgets;

use App\Jobs\FetchAllWeatherJob;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class WeatherFetchWidget extends Widget
{
    protected static string $view = 'filament.widgets.weather-fetch-widget';

    /** Full width, placed at the top of the dashboard. */
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    /**
     * Queue a fetch-all run and confirm to the admin. Completion itself arrives
     * later as a database notification from FetchAllWeatherJob.
     */
    public function fetchAll(): void
    {
        FetchAllWeatherJob::dispatch();

        Notification::make()
            ->title('Weather fetch started')
            ->body("You'll get a notification here when it finishes.")
            ->success()
            ->send();
    }
}
