<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaLibraryResource\Pages;
use App\Models\MediaLibrary;
use App\Models\Photographer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

            // Retro-assignment path: any existing image can be credited later by
            // editing it here, not only at upload time. Owner-only: `->preload()`
            // eagerly loads every photographer's name/id into the page, and a
            // contributor able to pick one could attribute their own upload to a
            // photographer they have no connection to, producing a false public
            // credit that links out to that photographer's socials.
            Select::make('photographer_id')
                ->label('Photographer')
                ->relationship('photographer', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Our own image')
                ->helperText('Leave blank for the site\'s own photography.')
                ->visible(fn (): bool => (bool) auth()->user()?->isOwner()),

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
                TextColumn::make('photographer.name')
                    ->label('Photographer')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—')
                    ->visible(fn (): bool => (bool) auth()->user()?->isOwner()),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('folder')
                    ->label('Folder')
                    ->options(fn (): array => static::folderOptions()),
                SelectFilter::make('photographer_id')
                    ->label('Photographer')
                    ->options(fn (): array => Photographer::orderBy('name')->pluck('name', 'id')->toArray())
                    // Owner-only: the options list is the whole photographer roster,
                    // which a contributor has no legitimate reason to browse.
                    ->visible(fn (): bool => (bool) auth()->user()?->isOwner()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // A batch of images typically arrives from one photographer;
                    // assigning them one at a time would be miserable. Owner-only
                    // (see PhotographerPolicy): a contributor could otherwise
                    // attribute their own uploads to any photographer in the
                    // roster, producing a false public credit. `->authorize()`
                    // (not just `->visible()`) so a direct network call to run
                    // the action is blocked too, not only its button.
                    BulkAction::make('assignPhotographer')
                        ->label('Assign photographer')
                        ->icon('heroicon-o-camera')
                        ->authorize(fn (): bool => (bool) auth()->user()?->isOwner())
                        ->form([
                            Select::make('photographer_id')
                                ->label('Photographer')
                                ->options(fn (): array => Photographer::orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->placeholder('Our own image (clear the credit)'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['photographer_id' => $data['photographer_id'] ?? null]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Distinct folder names for the form Select and the table filter. Everyone
     * except the owner only ever sees (and can file into) their own folders,
     * never the house folders; the owner sees every folder.
     *
     * @return array<string, string>
     */
    public static function folderOptions(): array
    {
        $user = auth()->user();

        return MediaLibrary::whereNotNull('folder')
            ->where('folder', '!=', '')
            // Opt-OUT for the owner rather than opt-IN for contributors: any role
            // added later is scoped by default instead of falling through to the
            // full library, house media included. Fail-closed on the user itself
            // too (`! $user?->isOwner()`, not `$user && ! $user->isOwner()`): a
            // guest (null user) must land in the SCOPED branch, not bypass
            // scoping entirely.
            ->when(! $user?->isOwner(), fn ($query) => $query->where('user_id', $user?->id))
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder', 'folder')
            ->toArray();
    }

    /**
     * Everyone except the owner only ever sees their own uploads; the owner sees
     * everything, house media included.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // See folderOptions(): scoped unless you are the owner, fail-closed for
        // a guest too.
        if (! $user?->isOwner()) {
            $query->where('user_id', $user?->id);
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
