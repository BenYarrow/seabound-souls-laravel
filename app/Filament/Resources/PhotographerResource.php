<?php

// Owner-only admin for photographers credited on site imagery. The form schema
// lives in PhotographerProfileForm so it can be reused unchanged by a
// photographer self-edit page if logins are ever added.

namespace App\Filament\Resources;

use App\Filament\Forms\PhotographerProfileForm;
use App\Filament\Resources\PhotographerResource\Pages;
use App\Models\Photographer;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhotographerResource extends Resource
{
    protected static ?string $model = Photographer::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema(PhotographerProfileForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('media_count')->label('Images')->counts('media')->sortable(),
                TextColumn::make('credit_link')
                    ->label('Credit links to')
                    ->placeholder('—')
                    // The stored value is the OPTIONS key (e.g. 'instagram'), not
                    // something a reader should have to decode — show the label.
                    ->formatStateUsing(fn (?string $state): ?string => $state ? (Photographer::CREDIT_LINK_OPTIONS[$state] ?? $state) : null),
                IconColumn::make('has_public_page')
                    ->label('Page live')
                    ->boolean()
                    ->state(fn (Photographer $record): bool => $record->hasPublicPage()),
            ])
            ->defaultSort('name')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhotographers::route('/'),
            'create' => Pages\CreatePhotographer::route('/create'),
            'edit' => Pages\EditPhotographer::route('/{record}/edit'),
        ];
    }
}
