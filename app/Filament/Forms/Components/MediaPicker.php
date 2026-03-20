<?php

namespace App\Filament\Forms\Components;

use App\Models\MediaLibrary;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Field;

class MediaPicker extends Field
{
    protected string $view = 'filament.forms.components.media-picker';

    protected bool $multiple = false;

    public function multiple(bool $value = true): static
    {
        $this->multiple = $value;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            Action::make('browse')
                ->label('Browse Library')
                ->icon('heroicon-o-photo')
                ->modalHeading('Media Library')
                ->modalContent(function (MediaPicker $component) {
                    return view('livewire.media-picker-browser-wrapper', [
                        'fieldKey' => $component->getStatePath(),
                        'multiple' => $component->isMultiple(),
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->slideOver(),
        ]);
    }

    public function getSelectedMedia(): ?MediaLibrary
    {
        $state = $this->getState();

        if (!$state || $this->multiple) {
            return null;
        }

        return MediaLibrary::find((int) $state);
    }

    public function getSelectedMediaItems(): \Illuminate\Support\Collection
    {
        if (!$this->multiple) {
            return collect();
        }

        $state = $this->getState();

        if (empty($state)) {
            return collect();
        }

        $ids = is_array($state) ? $state : [];

        return MediaLibrary::whereIn('id', $ids)->get()->keyBy('id');
    }
}
