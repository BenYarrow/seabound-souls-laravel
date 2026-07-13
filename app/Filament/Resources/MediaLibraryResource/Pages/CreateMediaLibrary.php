<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use App\Filament\Resources\MediaLibraryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaLibrary extends CreateRecord
{
    protected static string $resource = MediaLibraryResource::class;

    /**
     * Media created by a rider belongs to them so it stays in their own scoped
     * library and never leaks into the house library; owner-created media is house
     * media (user_id left null). The MediaPicker upload path stamps ownership the
     * same way (see MediaPickerBrowser::saveUpload).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->isRider()) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }
}
