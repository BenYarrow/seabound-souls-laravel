<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Forms\ContentBuilderBlocks;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Page')
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
                            Select::make('template')
                                ->options([
                                    'standard' => 'Standard',
                                    'homepage' => 'Homepage',
                                    'contact' => 'Contact',
                                    'destinations' => 'Destinations',
                                    'search' => 'Search',
                                ])
                                ->default('standard')
                                ->required(),
                            Toggle::make('is_published')->label('Published'),
                        ])->columns(2),

                    Tabs\Tab::make('Masthead')
                        ->schema([
                            MediaPicker::make('thumbnail_media_id')
                                ->label('Thumbnail'),
                            MediaPicker::make('static_masthead_media_id')
                                ->label('Static Masthead'),
                            MediaPicker::make('masthead_slider_media_ids')
                                ->label('Masthead Slider')
                                ->multiple(),
                        ]),

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
                            MediaPicker::make('og_image_media_id')
                                ->label('OG Image'),
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
                TextColumn::make('template')->sortable(),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
