<?php

// Filament admin inbox for contact-form enquiries. Read-only (submissions
// arrive via the public form, never created here); supports viewing, a
// new<->handled status toggle, a status filter, and a nav badge counting
// unhandled enquiries.

namespace App\Filament\Resources;

use App\Filament\Resources\ContactEnquiryResource\Pages;
use App\Models\ContactEnquiry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactEnquiryResource extends Resource
{
    protected static ?string $model = ContactEnquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?int $navigationSort = 5;

    /** Enquiries are only created by the public contact form. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Count of unhandled enquiries, shown as a sidebar badge (null hides it). */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('message')->limit(60)->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'new' ? 'warning' : 'gray'),
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['new' => 'New', 'handled' => 'Handled']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markHandled')
                    ->label('Mark handled')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ContactEnquiry $record): bool => $record->status === 'new')
                    ->action(fn (ContactEnquiry $record) => $record->update([
                        'status' => 'handled',
                        'handled_at' => now(),
                    ])),
                Tables\Actions\Action::make('markNew')
                    ->label('Mark as new')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (ContactEnquiry $record): bool => $record->status === 'handled')
                    ->action(fn (ContactEnquiry $record) => $record->update([
                        'status' => 'new',
                        'handled_at' => null,
                    ])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name'),
            TextEntry::make('email')->copyable(),
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('status')
                ->badge()
                ->color(fn (string $state): string => $state === 'new' ? 'warning' : 'gray'),
            TextEntry::make('created_at')->label('Received')->dateTime(),
            TextEntry::make('handled_at')->dateTime()->placeholder('—'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactEnquiries::route('/'),
            'view' => Pages\ViewContactEnquiry::route('/{record}'),
        ];
    }
}
