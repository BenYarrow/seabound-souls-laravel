<?php

namespace App\Filament\Resources\ContributorResource\Pages;

use App\Filament\Resources\ContributorResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ListContributors extends ListRecords
{
    protected static string $resource = ContributorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Invite a contributor: create the account and produce a signed set-password
            // link to hand over. Email delivery is a later addition (config mail
            // then send the same link) — the link is shown for manual sending now.
            // The whole resource is owner-only (UserPolicy), so no per-action gate.
            Actions\Action::make('inviteContributor')
                ->label('Invite Contributor')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->form([
                    TextInput::make('first_name')->label('First name')->required(),
                    TextInput::make('last_name')->label('Last name')->required(),
                    TextInput::make('email')->email()->required()->unique('users', 'email'),
                ])
                ->action(function (array $data) {
                    // `name` is derived from first/last by the User saving hook.
                    $contributor = User::create([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'password' => Str::random(40), // placeholder; replaced via the link
                        'role' => User::ROLE_CONTRIBUTOR,
                    ]);

                    $link = URL::temporarySignedRoute('contributor.password.setup', now()->addDays(7), ['user' => $contributor->id]);

                    Notification::make()
                        ->title('Contributor invited')
                        ->body('Send them this link to set their password: '.$link)
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }
}
