<?php

// Sent to a guide's author when the owner requests changes, carrying the note.

namespace App\Notifications;

use App\Models\SpotGuide;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class GuideChangesRequested extends Notification
{
    public function __construct(public SpotGuide $guide, public string $note) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Changes requested')
            ->body("“{$this->guide->title}”: {$this->note}")
            ->icon('heroicon-o-pencil-square')
            ->warning()
            ->getDatabaseMessage();
    }
}
