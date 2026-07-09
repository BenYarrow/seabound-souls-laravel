<?php

// Dashboard widget: a "Fetch all weather" button plus a live status line
// (last refresh + any in-progress fetches). Gives the admin an on-demand trigger
// and visible feedback, instead of waiting silently for the weekly command.

namespace App\Filament\Widgets;

use App\Jobs\FetchAllWeatherJob;
use App\Models\WeatherRecord;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

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

    /**
     * Human-readable "x ago" for the most recent weather record, or null when no
     * weather has been fetched yet. Uses the newest record's `updated_at` as a
     * proxy for "when weather data last changed".
     */
    public function getLastUpdatedAt(): ?string
    {
        return WeatherRecord::max('updated_at')
            ? \Illuminate\Support\Carbon::parse(WeatherRecord::max('updated_at'))->diffForHumans()
            : null;
    }

    /**
     * Number of weather-fetch jobs still queued (not yet processed by a worker),
     * so the admin can see a fetch is in progress. Matches both the per-spot and
     * fetch-all jobs by their class name in the serialised payload.
     */
    public function getPendingCount(): int
    {
        return DB::table('jobs')
            ->where('payload', 'like', '%FetchSpotWeatherJob%')
            ->orWhere('payload', 'like', '%FetchAllWeatherJob%')
            ->count();
    }

    /**
     * Whether a weather fetch is currently queued/running. Used to disable the
     * "Fetch all" button so the admin can't stack a second run on top of one
     * already in progress.
     */
    public function hasFetchInProgress(): bool
    {
        return $this->getPendingCount() > 0;
    }
}
