<?php

// Shared Filament form schema for a photographer's record — used by the
// owner-facing PhotographerResource today, and reusable verbatim by a
// photographer self-edit page if logins are ever added. Defining it once here is
// what makes that future handover free; it mirrors ContributorProfileForm.

namespace App\Filament\Forms;

use App\Filament\Forms\Components\MediaPicker;
use App\Models\Photographer;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class PhotographerProfileForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            Section::make('Identity')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)
                        ->helperText('Shown as the credit on every image assigned to them.'),
                    TextInput::make('slug')->maxLength(255)
                        ->helperText('Leave blank to generate from the name. Used for their page URL.'),
                    Textarea::make('bio')->rows(3)
                        ->helperText('Short intro shown on their page.'),
                ])->columns(2),

            Section::make('Socials')
                ->description('Only the ones you fill in can be used as the credit link.')
                ->schema([
                    TextInput::make('socials.instagram')->label('Instagram')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('socials.youtube')->label('YouTube')->url(),
                    TextInput::make('socials.tiktok')->label('TikTok')->url(),
                    TextInput::make('socials.facebook')->label('Facebook')->url(),
                    TextInput::make('socials.x')->label('X (Twitter)')->url(),
                    TextInput::make('socials.website')->label('Personal website')->url(),
                ])->columns(2),

            Section::make('Credit link')
                ->description('Where a click on their image credit goes. Only filled-in socials are offered.')
                ->schema([
                    Select::make('credit_link')
                        ->label('Link image credits to')
                        ->options(fn (?Photographer $record): array => static::creditLinkOptions($record))
                        ->default('none'),
                ]),

            Section::make('Page images')
                ->description('Only used once their page is live.')
                ->schema([
                    MediaPicker::make('thumbnail_media_id')->label('Card image'),
                    MediaPicker::make('static_masthead_media_id')->label('Masthead image'),
                ]),

            Section::make('Their page')
                ->description('Adding content here publishes their page at /photographers/{slug}. Leave empty and they have no page — just image credits.')
                ->schema([
                    Builder::make('profile_blocks')
                        ->label('Page content')
                        ->blocks(ContentBuilderBlocks::blocks())
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            Section::make('SEO')
                ->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    Textarea::make('seo_description')->rows(2),
                ])->collapsed(),
        ];
    }

    /**
     * Credit-link targets selectable for this record: 'none', every socials key
     * with a URL in it, and 'profile' only once the page is actually live. A
     * target that would resolve to nothing is never offered.
     *
     * @return array<string, string>
     */
    public static function creditLinkOptions(?Photographer $record): array
    {
        $options = ['none' => Photographer::CREDIT_LINK_OPTIONS['none']];

        if ($record?->hasPublicPage()) {
            $options['profile'] = Photographer::CREDIT_LINK_OPTIONS['profile'];
        }

        foreach ($record?->socials ?? [] as $platform => $url) {
            if (filled($url) && isset(Photographer::CREDIT_LINK_OPTIONS[$platform])) {
                $options[$platform] = Photographer::CREDIT_LINK_OPTIONS[$platform];
            }
        }

        return $options;
    }
}
