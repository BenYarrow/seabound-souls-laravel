<?php

// Sent to owners when a contributor submits a guide for review. Delivered in-panel now
// (Filament database notification). Email is a later drop-in: add 'mail' to via()
// and a toMail() method — no other change needed.

namespace App\Notifications;

use App\Models\SpotGuide;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class GuideSubmittedForReview extends Notification
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
            ->title('Guide submitted for review')
            ->body(($this->guide->author?->name ?? 'A contributor')." submitted “{$this->guide->title}” for review.")
            ->icon('heroicon-o-inbox-arrow-down')
            ->getDatabaseMessage();
    }
}
