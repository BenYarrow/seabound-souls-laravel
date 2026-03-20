<?php

namespace App\Filament\Resources\SpotGuideResource\Pages;

use App\Filament\Resources\SpotGuideResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSpotGuide extends EditRecord
{
    protected static string $resource = SpotGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
