<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaLibraryResource\Pages;
use App\Models\MediaLibrary;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaLibraryResource extends Resource
{
    protected static ?string $model = MediaLibrary::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Name / Alt Text')
                ->required()
                ->maxLength(255),

            Select::make('folder')
                ->label('Folder')
                ->options(fn (): array => static::folderOptions())
                ->searchable()
                ->createOptionForm([
                    TextInput::make('folder')
                        ->label('New Folder Name')
                        ->required(),
                ])
                ->createOptionUsing(fn (array $data): string => $data['folder'])
                ->nullable(),

            SpatieMediaLibraryFileUpload::make('file')
                ->collection('file')
                ->image()
                ->required()
                ->label('Image File')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('file')
                    ->collection('file')
                    ->label('Image')
                    ->width(80)
                    ->height(60),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('folder')
                    ->label('Folder')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('folder')
                    ->label('Folder')
                    ->options(fn (): array => static::folderOptions()),
            ])
            ->defaultSort('created_at', 'desc')
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

    /**
     * Distinct folder names for the form Select and the table filter, scoped so a
     * contributor only ever sees (and can file into) their own folders — never the house
     * folders. Owners see every folder.
     *
     * @return array<string, string>
     */
    public static function folderOptions(): array
    {
        $user = auth()->user();

        return MediaLibrary::whereNotNull('folder')
            ->where('folder', '!=', '')
            ->when($user && $user->isContributor(), fn ($query) => $query->where('user_id', $user->id))
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder', 'folder')
            ->toArray();
    }

    /**
     * Contributors only ever see their own uploads; owners see everything.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->isContributor()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaLibraries::route('/'),
            'create' => Pages\CreateMediaLibrary::route('/create'),
            'edit' => Pages\EditMediaLibrary::route('/{record}/edit'),
        ];
    }
}
