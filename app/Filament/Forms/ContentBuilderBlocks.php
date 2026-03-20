<?php

namespace App\Filament\Forms;

use App\Filament\Forms\Components\MediaPicker;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContentBuilderBlocks
{
    public static function backgroundColourSelect(): Select
    {
        return Select::make('backgroundColour')
            ->label('Background Colour')
            ->options([
                'bg-white' => 'White',
                'bg-primary' => 'Primary',
                'bg-primary-lighter' => 'Primary Lighter',
                'bg-primary-lightest' => 'Primary Lightest',
                'bg-secondary' => 'Secondary',
            ])
            ->default('bg-white');
    }

    public static function textAlignSelect(): Select
    {
        return Select::make('textAlign')
            ->label('Text Alignment')
            ->options([
                'text-left' => 'Left',
                'text-center' => 'Center',
                'text-right' => 'Right',
            ])
            ->default('text-left');
    }

    public static function blocks(): array
    {
        return [
            Builder\Block::make('rich_text')
                ->label('Rich Text')
                ->schema([
                    static::backgroundColourSelect(),
                    static::textAlignSelect(),
                    RichEditor::make('content')
                        ->label('Content')
                        ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote'])
                        ->columnSpanFull(),
                ]),

            Builder\Block::make('single_image')
                ->label('Single Image')
                ->schema([
                    static::backgroundColourSelect(),
                    MediaPicker::make('media_library_id')
                        ->label('Image'),
                ]),

            Builder\Block::make('image_pair')
                ->label('Image Pair')
                ->schema([
                    static::backgroundColourSelect(),
                    MediaPicker::make('imageLeftMediaId')
                        ->label('Left Image'),
                    MediaPicker::make('imageRightMediaId')
                        ->label('Right Image'),
                ]),

            Builder\Block::make('gallery')
                ->label('Gallery')
                ->schema([
                    Toggle::make('thumbnailsOnly')
                        ->label('Thumbnails Only'),
                    MediaPicker::make('mediaIds')
                        ->label('Images')
                        ->multiple(),
                ]),

            Builder\Block::make('content_with_background_image')
                ->label('Content with Background Image')
                ->schema([
                    MediaPicker::make('backgroundImageMediaId')
                        ->label('Background Image'),
                    RichEditor::make('content')
                        ->label('Content')
                        ->columnSpanFull(),
                    Toggle::make('textRight')
                        ->label('Text on Right'),
                ]),

            Builder\Block::make('split_image_text')
                ->label('Split Image & Text')
                ->schema([
                    MediaPicker::make('media_library_id')
                        ->label('Image'),
                    RichEditor::make('text')
                        ->label('Text')
                        ->columnSpanFull(),
                    Toggle::make('reverse')
                        ->label('Reverse (image on right)'),
                ]),

            Builder\Block::make('list_content_blogs')
                ->label('List: Blog Posts')
                ->schema([
                    TextInput::make('blockTitle')
                        ->label('Block Title'),
                    static::backgroundColourSelect(),
                    Select::make('customBlogEntries')
                        ->label('Blog Posts')
                        ->multiple()
                        ->relationship('', 'title')
                        ->searchable()
                        ->preload(),
                ]),

            Builder\Block::make('list_content_spot_guides')
                ->label('List: Spot Guides')
                ->schema([
                    TextInput::make('blockTitle')
                        ->label('Block Title'),
                    static::backgroundColourSelect(),
                    Select::make('customSpotGuideEntries')
                        ->label('Spot Guides')
                        ->multiple()
                        ->relationship('', 'title')
                        ->searchable()
                        ->preload(),
                ]),

            Builder\Block::make('infographic')
                ->label('Infographic')
                ->schema([
                    static::backgroundColourSelect(),
                ]),
        ];
    }
}
