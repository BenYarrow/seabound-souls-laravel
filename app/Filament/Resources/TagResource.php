<?php

// Admin resource for the curated "Blog Tags" vocabulary. Owner-only (gated by
// TagPolicy). Slug auto-fills from name and clashes only with live tags (partial
// unique index), so a soft-deleted tag's slug can be reused. The SEO & intro
// fields feed the tag page's meta and intro copy.

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Blog Tags';

    protected static ?string $modelLabel = 'blog tag';

    protected static ?string $pluralModelLabel = 'blog tags';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            TextInput::make('slug')
                ->required()
                // Only clash with LIVE tags — a soft-deleted tag's slug is free to
                // reuse (matches the partial DB index).
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at')),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first in the tag bar.'),
            Section::make('SEO & intro')
                ->description('Optional. Blank fields fall back to sensible defaults.')
                ->collapsed()
                ->schema([
                    Textarea::make('description')
                        ->label('Intro paragraph')
                        ->helperText('Shown at the top of the tag page.'),
                    TextInput::make('seo_title')->label('SEO Title'),
                    Textarea::make('seo_description')->label('SEO Description'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug'),
                TextColumn::make('blogs_count')->label('Posts')->counts('blogs'),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
