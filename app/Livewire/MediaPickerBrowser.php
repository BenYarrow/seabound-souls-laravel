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
        $user = auth()->user();

        return MediaLibrary::whereNotNull('folder')
            ->when($user && $user->isRider(), fn ($q) => $q->where('user_id', $user->id))
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder')
            ->toArray();
    }

    public function toggleSelect(int $id): void
    {
        // Livewire public methods are directly network-callable regardless of what
        // the client rendered — a rider could call toggleSelect() with an id the
        // scoped browse query never surfaced (house media, another rider's media).
        // Re-check the same role-scoping predicate used in render() before adding it.
        if (! $this->isSelectableByCurrentUser($id)) {
            return;
        }

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
                'user_id' => auth()->user()?->isRider() ? auth()->id() : null,
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
        // selectedIds may have been hydrated from a tampered wire:snapshot rather
        // than built purely via toggleSelect(), so re-filter here too rather than
        // trusting the property is already scoped.
        $allowedIds = array_values(array_filter(
            $this->selectedIds,
            fn (int $id) => $this->isSelectableByCurrentUser($id),
        ));

        $this->dispatch('media-library-selected',
            fieldKey: $this->fieldKey,
            ids: $allowedIds,
        );
    }

    /**
     * Whether the given media id is within the current user's selection scope:
     * riders may only select their own uploads; owners (and guests, defensively)
     * may select anything. Mirrors the role-scoping predicate used in render().
     */
    protected function isSelectableByCurrentUser(int $id): bool
    {
        $user = auth()->user();

        return MediaLibrary::query()
            ->when($user && $user->isRider(), fn ($q) => $q->where('user_id', $user->id))
            ->whereKey($id)
            ->exists();
    }

    public function render()
    {
        $user = auth()->user();

        $mediaItems = MediaLibrary::query()
            ->when($user && $user->isRider(), fn ($q) => $q->where('user_id', $user->id))
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
