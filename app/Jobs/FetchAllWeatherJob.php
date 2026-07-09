<?php

// Queued "fetch all" weather refresh. Dispatched by the dashboard widget; keeps
// every spot's data in sync in one paced run, then posts a Filament database
// notification to all admins so they know the refresh finished.

namespace App\Jobs;

use App\Models\SpotGuide;
use App\Models\User;
use App\Services\WeatherFetcher;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchAllWeatherJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** One retry attempt — a full re-run is expensive, so keep retries low. */
    public int $tries = 2;

    public int $backoff = 30;

    public function handle(WeatherFetcher $fetcher): void
    {
        $spots = SpotGuide::whereNotNull('latitude')->whereNotNull('longitude')->get();

        $processed = $fetcher->fetchForSpots($spots);

        // Build the database notification payload via the Filament fluent API.
        $databaseNotification = Notification::make()
            ->title('Weather data updated')
            ->body("Refreshed weather for {$processed} spot " . ($processed === 1 ? 'guide' : 'guides') . '.')
            ->success()
            ->toDatabase();

        // Use notifyNow() rather than sendToDatabase() / notify() so the row is
        // written synchronously — sendToDatabase() calls $user->notify() which
        // defers to the queue driver and would silently discard the write in
        // environments where the queue driver is null (e.g. the test suite).
        foreach (User::all() as $user) {
            $user->notifyNow($databaseNotification);
        }
    }
}
