<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Forms\ContentBuilderBlocks;
use App\Filament\Resources\SpotGuideResource\Pages;
use App\Models\Country;
use App\Models\SpotGuide;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SpotGuideResource extends Resource
{
    protected static ?string $model = SpotGuide::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Spot Guides';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('SpotGuide')
                ->tabs([
                    Tabs\Tab::make('General')
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                            TextInput::make('slug')
                                ->required()
                                ->unique(ignoreRecord: true),
                            Select::make('country_id')
                                ->label('Country')
                                ->options(Country::pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            TextInput::make('timezone')
                                ->placeholder('Europe/Athens'),
                            TextInput::make('latitude')
                                ->numeric(),
                            TextInput::make('longitude')
                                ->numeric(),
                            Toggle::make('is_published')
                                ->label('Published'),
                        ])->columns(2),

                    Tabs\Tab::make('Masthead & Thumbnail')
                        ->schema([
                            MediaPicker::make('thumbnail_media_id')
                                ->label('Thumbnail'),
                            MediaPicker::make('static_masthead_media_id')
                                ->label('Static Masthead'),
                            MediaPicker::make('og_image_media_id')
                                ->label('OG Image'),
                        ]),

                    Tabs\Tab::make('Spot Overview')
                        ->schema([
                            TextInput::make('spot_overview.sailing_style')->label('Sailing Style'),
                            TextInput::make('spot_overview.best_conditions')->label('Best Conditions'),
                            TextInput::make('spot_overview.best_direction')->label('Best Wind Direction'),
                            TextInput::make('spot_overview.wind_conditions')->label('Wind Conditions'),
                            TextInput::make('spot_overview.water_conditions')->label('Water Conditions'),
                            TextInput::make('spot_overview.launch_zone')->label('Launch Zone'),
                        ])->columns(2),

                    Tabs\Tab::make('Introduction & Gallery')
                        ->schema([
                            RichEditor::make('introduction_text')
                                ->label('Introduction')
                                ->columnSpanFull(),
                            MediaPicker::make('gallery_media_ids')
                                ->label('Gallery')
                                ->multiple(),
                        ]),

                    Tabs\Tab::make('Water Conditions')
                        ->schema([
                            MediaPicker::make('water_conditions_bg_media_id')
                                ->label('Background Image'),
                            RichEditor::make('water_conditions.content')
                                ->label('Content')
                                ->columnSpanFull(),
                            Toggle::make('water_conditions.text_right')
                                ->label('Text on Right'),
                        ]),

                    Tabs\Tab::make('Wind Conditions')
                        ->schema([
                            MediaPicker::make('wind_conditions_bg_media_id')
                                ->label('Background Image'),
                            RichEditor::make('wind_conditions.content')
                                ->label('Content')
                                ->columnSpanFull(),
                            Toggle::make('wind_conditions.text_right')
                                ->label('Text on Right'),
                        ]),

                    Tabs\Tab::make('When To Go')
                        ->schema([
                            RichEditor::make('when_to_go')
                                ->label('When To Go Content')
                                ->columnSpanFull(),
                        ]),

                    Tabs\Tab::make('Windsurfing Locations')
                        ->schema([
                            Repeater::make('windsurfingLocations')
                                ->relationship()
                                ->schema([
                                    TextInput::make('name')->required(),
                                    Textarea::make('description'),
                                    TextInput::make('latitude')->numeric(),
                                    TextInput::make('longitude')->numeric(),
                                    TextInput::make('sort_order')->numeric()->default(0),
                                ])
                                ->columns(2)
                                ->orderColumn('sort_order'),
                        ]),

                    Tabs\Tab::make('Where To Stay')
                        ->schema([
                            RichEditor::make('where_to_stay_intro')
                                ->label('Introduction')
                                ->columnSpanFull(),
                            Repeater::make('stayRecommendations')
                                ->label('Hotels & Accommodation')
                                ->relationship('recommendations', modifyQueryUsing: fn ($query) => $query->where('type', 'stay'))
                                ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => array_merge($data, ['type' => 'stay']))
                                ->mutateRelationshipDataBeforeSaveUsing(fn(array $data) => array_merge($data, ['type' => 'stay']))
                                ->schema([
                                    MediaPicker::make('thumbnail_media_id')
                                        ->label('Image')
                                        ->columnSpanFull(),
                                    TextInput::make('name')->required(),
                                    Textarea::make('description'),
                                    TextInput::make('url')->label('Google Maps / Website URL'),
                                    TextInput::make('latitude')->numeric(),
                                    TextInput::make('longitude')->numeric(),
                                    TextInput::make('sort_order')->numeric()->default(0),
                                ])
                                ->columns(2)
                                ->orderColumn('sort_order'),
                        ]),

                    Tabs\Tab::make('Where To Eat')
                        ->schema([
                            RichEditor::make('where_to_eat_intro')
                                ->label('Introduction')
                                ->columnSpanFull(),
                            Repeater::make('eatRecommendations')
                                ->label('Restaurants & Cafes')
                                ->relationship('recommendations', modifyQueryUsing: fn ($query) => $query->where('type', 'eat'))
                                ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => array_merge($data, ['type' => 'eat']))
                                ->mutateRelationshipDataBeforeSaveUsing(fn(array $data) => array_merge($data, ['type' => 'eat']))
                                ->schema([
                                    MediaPicker::make('thumbnail_media_id')
                                        ->label('Image')
                                        ->columnSpanFull(),
                                    TextInput::make('name')->required(),
                                    Textarea::make('description'),
                                    TextInput::make('url')->label('Google Maps / Website URL'),
                                    TextInput::make('latitude')->numeric(),
                                    TextInput::make('longitude')->numeric(),
                                    TextInput::make('sort_order')->numeric()->default(0),
                                ])
                                ->columns(2)
                                ->orderColumn('sort_order'),
                        ]),

                    Tabs\Tab::make('Travelling To')
                        ->schema([
                            MediaPicker::make('travelling_to_bg_media_id')
                                ->label('Background Image'),
                            RichEditor::make('travelling_to.content')
                                ->label('Content')
                                ->columnSpanFull(),
                            Toggle::make('travelling_to.text_right')
                                ->label('Text on Right'),
                        ]),

                    Tabs\Tab::make('Lessons & Hire')
                        ->schema([
                            MediaPicker::make('lessons_and_hire_bg_media_id')
                                ->label('Background Image'),
                            RichEditor::make('lessons_and_hire.content')
                                ->label('Content')
                                ->columnSpanFull(),
                            Toggle::make('lessons_and_hire.text_right')
                                ->label('Text on Right'),
                            TextInput::make('lessons_and_hire.latitude')
                                ->label('Map Latitude')
                                ->numeric(),
                            TextInput::make('lessons_and_hire.longitude')
                                ->label('Map Longitude')
                                ->numeric(),
                        ])->columns(2),

                    Tabs\Tab::make('Content Builder')
                        ->schema([
                            Builder::make('content_blocks')
                                ->label('Content Blocks')
                                ->blocks(ContentBuilderBlocks::blocks())
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),

                    Tabs\Tab::make('SEO')
                        ->schema([
                            TextInput::make('seo_title')->label('SEO Title'),
                            Textarea::make('seo_description')->label('SEO Description'),
                            TagsInput::make('seo_keywords')->label('SEO Keywords'),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('country.name')->label('Country')->sortable(),
                IconColumn::make('is_published')->label('Published')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpotGuides::route('/'),
            'create' => Pages\CreateSpotGuide::route('/create'),
            'edit' => Pages\EditSpotGuide::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
