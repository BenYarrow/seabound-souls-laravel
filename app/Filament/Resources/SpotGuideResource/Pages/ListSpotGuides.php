<?php

namespace App\Filament\Resources\SpotGuideResource\Pages;

use App\Filament\Resources\SpotGuideResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSpotGuides extends ListRecords
{
    protected static string $resource = SpotGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
