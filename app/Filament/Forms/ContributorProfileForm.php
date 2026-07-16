<?php

// Shared Filament form schema for a contributor's public profile — used by the
// self-service MyProfile page and (owner-facing) ContributorResource, so the
// field set is defined once.

namespace App\Filament\Forms;

use App\Filament\Forms\Components\MediaPicker;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;

class ContributorProfileForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            Section::make('Images')
                ->description('A portrait (used on your profile and the crew page) and an optional masthead hero.')
                ->schema([
                    MediaPicker::make('profile_image_media_id')->label('Profile image'),
                    MediaPicker::make('static_masthead_media_id')->label('Masthead image'),
                ]),
            Section::make('Socials')
                ->description('Only the ones you fill in are shown.')
                ->schema([
                    TextInput::make('socials.instagram')->label('Instagram')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('socials.youtube')->label('YouTube')->url(),
                    TextInput::make('socials.tiktok')->label('TikTok')->url(),
                    TextInput::make('socials.facebook')->label('Facebook')->url(),
                    TextInput::make('socials.x')->label('X (Twitter)')->url(),
                    TextInput::make('socials.website')->label('Personal website')->url(),
                ])->columns(2),
            Section::make('Your story')
                ->schema([
                    Builder::make('profile_blocks')
                        ->label('Profile content')
                        ->blocks(ContentBuilderBlocks::blocks())
                        ->collapsible()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
