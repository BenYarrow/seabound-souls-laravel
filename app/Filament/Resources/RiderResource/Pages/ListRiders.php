<?php

namespace App\Filament\Resources\RiderResource\Pages;

use App\Filament\Resources\RiderResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ListRiders extends ListRecords
{
    protected static string $resource = RiderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Invite a rider: create the account and produce a signed set-password
            // link to hand over. Email delivery is a later addition (config mail
            // then send the same link) — the link is shown for manual sending now.
            // The whole resource is owner-only (UserPolicy), so no per-action gate.
            Actions\Action::make('inviteRider')
                ->label('Invite Rider')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->form([
                    TextInput::make('first_name')->label('First name')->required(),
                    TextInput::make('last_name')->label('Last name')->required(),
                    TextInput::make('email')->email()->required()->unique('users', 'email'),
                ])
                ->action(function (array $data) {
                    // `name` is derived from first/last by the User saving hook.
                    $rider = User::create([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
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
