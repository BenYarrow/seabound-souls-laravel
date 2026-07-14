<?php

// Read-only panel on the Edit Contributor page listing the spot guides this contributor has
// authored, with their review status and a link to open each in the full guide
// editor. Guides are created/edited by contributors themselves, so this panel does not
// create, attach, or inline-edit — it is a roster with an "Open" shortcut.

namespace App\Filament\Resources\ContributorResource\RelationManagers;

use App\Filament\Resources\SpotGuideResource;
use App\Models\SpotGuide;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpotGuidesRelationManager extends RelationManager
{
    protected static string $relationship = 'authoredSpotGuides';

    protected static ?string $title = 'Spot Guides';

    protected static ?string $recordTitleAttribute = 'title';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('review_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucwords(str_replace('_', ' ', $state ?? 'draft')))
                    ->color(fn (?string $state): string => match ($state) {
                        SpotGuide::STATUS_IN_REVIEW => 'warning',
                        SpotGuide::STATUS_CHANGES_REQUESTED => 'danger',
                        SpotGuide::STATUS_APPROVED => 'success',
                        default => 'gray',
                    }),
                IconColumn::make('is_published')->label('Published')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
            ])
            ->actions([
                // Link out to the full spot-guide editor rather than editing the
                // large tabbed form inline within this panel.
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SpotGuide $record): string => SpotGuideResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
