<?php

namespace App\Filament\Resources\ContributorResource\Pages;

use App\Filament\Resources\ContributorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContributor extends EditRecord
{
    protected static string $resource = ContributorResource::class;

    /** Hosts the authored-spot-guides relation manager below the name/email form. */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
