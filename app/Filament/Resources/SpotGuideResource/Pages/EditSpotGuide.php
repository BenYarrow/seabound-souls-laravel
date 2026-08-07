<?php

namespace App\Filament\Resources\SpotGuideResource\Pages;

use App\Filament\Resources\SpotGuideResource;
use App\Models\SpotGuide;
use App\Models\User;
use App\Notifications\GuideChangesRequested;
use App\Notifications\GuidePublished;
use App\Notifications\GuideSubmittedForReview;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Notification as Notifier;

class EditSpotGuide extends EditRecord
{
    protected static string $resource = SpotGuideResource::class;

    /** Review status captured before this save, to detect edits to a live guide. */
    private ?string $reviewStatusBeforeSave = null;

    /**
     * Capture the pre-save review status (review_status isn't a form field, so at
     * this point the record still holds its stored value).
     */
    protected function beforeSave(): void
    {
        $this->reviewStatusBeforeSave = $this->record->review_status;
    }

    /**
     * If anyone but the owner edits a guide that was already APPROVED, keep it live but
     * flag it back to review and notify the owners — so live content never changes
     * silently without you knowing. Owner edits, and edits to draft/changes-requested
     * guides, are left alone. Opt-OUT for the owner rather than opt-IN for contributors:
     * the owner is the approver, so an owner edit correctly never re-flags; a role added
     * later must not fall through this check and be able to edit live content without
     * it being re-flagged or the owner being notified.
     */
    protected function afterSave(): void
    {
        $user = auth()->user();

        if (! $user?->isOwner() && $this->reviewStatusBeforeSave === SpotGuide::STATUS_APPROVED) {
            $this->record->submitForReview();
            Notifier::send(User::where('role', User::ROLE_OWNER)->get(), new GuideSubmittedForReview($this->record));
        }
    }

    /**
     * Header actions differ by role:
     *  - Contributor: Submit for review + Preview.
     *  - Owner: Publish, Request changes, Preview, Delete.
     */
    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            // Contributor submits their own guide for review.
            Actions\Action::make('submit')
                ->label('Submit for review')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (SpotGuide $record): bool => $user?->isContributor() && $record->user_id === $user->id)
                ->requiresConfirmation()
                ->action(function (SpotGuide $record) {
                    $record->submitForReview();
                    Notifier::send(User::where('role', User::ROLE_OWNER)->get(), new GuideSubmittedForReview($record));
                    Notification::make()->title('Submitted for review')->success()->send();
                }),

            // Owner approves and takes it live.
            Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => (bool) $user?->isOwner())
                ->requiresConfirmation()
                ->action(function (SpotGuide $record) {
                    $record->publish();
                    // Skip the notification when the owner is publishing their own guide
                    // (e.g. a house-authored spot guide) — no point notifying yourself.
                    if ($record->author && ! $record->author->is(auth()->user())) {
                        $record->author->notify(new GuidePublished($record));
                    }
                    Notification::make()->title('Published')->success()->send();
                }),

            // Owner sends it back with a note.
            Actions\Action::make('requestChanges')
                ->label('Request changes')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => (bool) $user?->isOwner())
                ->form([
                    Textarea::make('note')->label('What needs changing?')->required(),
                ])
                ->action(function (array $data, SpotGuide $record) {
                    $record->requestChanges($data['note']);
                    if ($record->author) {
                        $record->author->notify(new GuideChangesRequested($record, $data['note']));
                    }
                    Notification::make()->title('Changes requested')->warning()->send();
                }),

            // Preview the real public page (owner: any; author: their own). Only
            // meaningful while unpublished, but harmless when live.
            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (SpotGuide $record): string => route('spot-guides.show', $record->slug))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(), // respects SpotGuidePolicy::delete
        ];
    }
}
