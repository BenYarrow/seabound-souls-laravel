<?php

// Owner-only "Riders" admin section: a roster of invited rider contributors and,
// per rider, the spot guides they have authored. Accounts are created via the
// Invite Rider action (see ListRiders), never a blank create form — so the
// standard create page is disabled. Access is gated to owners by UserPolicy.

namespace App\Filament\Resources;

use App\Filament\Resources\RiderResource\Pages;
use App\Filament\Resources\RiderResource\RelationManagers\SpotGuidesRelationManager;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RiderResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Riders';

    protected static ?int $navigationSort = 6;

    // The resource is built on the User model, so Filament would otherwise derive
    // "User"/"Users" for page titles and breadcrumbs — override to "Rider"/"Riders".
    protected static ?string $modelLabel = 'rider';

    protected static ?string $pluralModelLabel = 'riders';

    /** Rider accounts are created via the Invite Rider action, not a create form. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** The roster is only ever rider accounts — owners never appear here. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', User::ROLE_RIDER);
    }

    public static function form(Form $form): Form
    {
        // Only the display fields are editable here; the password is set by the
        // rider via their signed invite link, never by the owner. `name` is
        // derived from first/last by the User saving hook.
        return $form->schema([
            TextInput::make('first_name')->label('First name')->required(),
            TextInput::make('last_name')->label('Last name')->required(),
            TextInput::make('email')->email()->required()->unique('users', 'email', ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('first_name')->label('First name')->searchable()->sortable(),
                TextColumn::make('last_name')->label('Last name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('authored_spot_guides_count')
                    ->label('Guides')
                    ->counts('authoredSpotGuides')
                    ->sortable(),
                TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SpotGuidesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRiders::route('/'),
            'edit' => Pages\EditRider::route('/{record}/edit'),
        ];
    }
}
