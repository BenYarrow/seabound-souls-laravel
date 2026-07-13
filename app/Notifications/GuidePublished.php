<?php

// Sent to a guide's author when the owner publishes it.

namespace App\Notifications;

use App\Models\SpotGuide;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class GuidePublished extends Notification
{
    public function __construct(public SpotGuide $guide) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Your guide is published')
            ->body("“{$this->guide->title}” is now live on the site.")
            ->icon('heroicon-o-check-circle')
            ->success()
            ->getDatabaseMessage();
    }
}
