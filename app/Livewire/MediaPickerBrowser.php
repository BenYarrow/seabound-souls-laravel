<?php

namespace App\Livewire;

use App\Models\MediaLibrary;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaPickerBrowser extends Component
{
    use WithFileUploads, WithPagination;

    public string $fieldKey = '';
    public bool $multiple = false;
    public array $selectedIds = [];
    public string $search = '';
    public string $folder = '';
    public string $activeTab = 'library';

    public $newFile = null;
    public string $newName = '';
    public string $newFolder = '';
    public bool $uploadSuccess = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFolder(): void
    {
        $this->resetPage();
    }

    public function getFolderOptions(): array
    {
        return MediaLibrary::whereNotNull('folder')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder')
            ->toArray();
    }

    public function toggleSelect(int $id): void
    {
        if ($this->multiple) {
            if (in_array($id, $this->selectedIds)) {
                $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($i) => $i !== $id));
            } else {
                $this->selectedIds[] = $id;
            }
        } else {
            $this->selectedIds = [$id];
        }
    }

    public function saveUpload(): void
    {
        $this->uploadSuccess = false;

        $this->validate([
            'newFile' => 'required|image|max:10240',
            'newName' => 'nullable|string|max:255',
        ]);

        try {
            $ml = MediaLibrary::create([
                'name' => $this->newName ?: $this->newFile->getClientOriginalName(),
                'folder' => $this->newFolder ?: null,
            ]);

            $ml->addMedia($this->newFile->getRealPath())
                ->usingFileName($this->newFile->getClientOriginalName())
                ->toMediaCollection('file');

            $this->selectedIds[] = $ml->id;
            $this->newFile = null;
            $this->newName = '';
            $this->newFolder = '';
            $this->uploadSuccess = true;
            $this->activeTab = 'library';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->addError('newFile', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function confirm(): void
    {
        $this->dispatch('media-library-selected',
            fieldKey: $this->fieldKey,
            ids: $this->selectedIds,
        );
    }

    public function render()
    {
        $mediaItems = MediaLibrary::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->folder, fn ($q) => $q->where('folder', $this->folder))
            ->latest()
            ->paginate(24);

        return view('livewire.media-picker-browser', [
            'mediaItems' => $mediaItems,
            'folderOptions' => $this->getFolderOptions(),
        ]);
    }
}
