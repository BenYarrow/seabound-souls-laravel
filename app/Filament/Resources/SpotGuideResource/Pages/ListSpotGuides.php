<?php

namespace App\Filament\Resources\SpotGuideResource\Pages;

use App\Filament\Resources\SpotGuideResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ListSpotGuides extends ListRecords
{
    protected static string $resource = SpotGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // Owner-only: create a rider account and produce a signed set-password
            // link to hand over. Email delivery is a later addition (config mail
            // then send the same link) — the link is shown for manual sending now.
            Actions\Action::make('inviteRider')
                ->label('Invite Rider')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->isOwner())
                ->form([
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email()->required()->unique('users', 'email'),
                ])
                ->action(function (array $data) {
                    $rider = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Str::random(40), // placeholder; replaced via the link
                        'role' => User::ROLE_RIDER,
                    ]);

                    $link = URL::temporarySignedRoute('rider.password.setup', now()->addDays(7), ['user' => $rider->id]);

                    Notification::make()
                        ->title('Rider invited')
                        ->body('Send them this link to set their password: '.$link)
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }
}
