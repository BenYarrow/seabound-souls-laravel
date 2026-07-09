<?php

namespace App\Filament\Resources\SpotGuideResource\Pages;

use App\Filament\Resources\SpotGuideResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSpotGuide extends CreateRecord
{
    protected static string $resource = SpotGuideResource::class;

    /**
     * The SpotGuide::created hook queues a weather fetch for the new spot
     * (coordinates are required, so one is always dispatched). Surface that to
     * the admin so the background fetch isn't silent — completion itself arrives
     * later as a database (bell) notification from the job.
     */
    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Weather fetch queued')
            ->body("Historical weather for {$this->record->title} is being fetched in the background.")
            ->info()
            ->send();
    }
}
