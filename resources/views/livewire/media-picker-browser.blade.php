<div class="p-1">
    {{-- Tabs --}}
    <div class="flex border-b border-gray-200 dark:border-gray-700 mb-4">
        <button
            wire:click="$set('activeTab', 'library')"
            type="button"
            class="px-4 py-2 text-sm font-medium {{ $activeTab === 'library' ? 'border-b-2 border-primary-600 text-primary-600 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
        >
            Library
        </button>
        <button
            wire:click="$set('activeTab', 'upload')"
            type="button"
            class="px-4 py-2 text-sm font-medium {{ $activeTab === 'upload' ? 'border-b-2 border-primary-600 text-primary-600 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
        >
            Upload New
        </button>
    </div>

    @if($activeTab === 'library')
        {{-- Search + Folder filter --}}
        <div class="flex gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search by name..."
                class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
            <select
                wire:model.live="folder"
                class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
                <option value="">All folders</option>
                @foreach($folderOptions as $f)
                    <option value="{{ $f }}">{{ $f }}</option>
                @endforeach
            </select>
        </div>

        {{-- Grid --}}
        @if($mediaItems->isEmpty())
            <p class="text-center text-gray-400 py-12 text-sm">No images in the library yet. Upload one above.</p>
        @else
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3 mb-4">
                @foreach($mediaItems as $item)
                    @php $url = $item->getUrl(); @endphp
                    <div
                        wire:click="toggleSelect({{ $item->id }})"
                        class="cursor-pointer relative rounded-lg overflow-hidden border-2 transition-all {{ in_array($item->id, $selectedIds) ? 'border-primary-500 ring-2 ring-primary-200' : 'border-transparent hover:border-gray-300' }}"
                    >
                        @if($url)
                            <img src="{{ $url }}" alt="{{ $item->name }}" class="w-full aspect-square object-cover">
                        @else
                            <div class="w-full aspect-square bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <span class="text-gray-400 text-xs">No file</span>
                            </div>
                        @endif

                        @if(in_array($item->id, $selectedIds))
                            <div class="absolute top-1 right-1 bg-primary-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">✓</div>
                        @endif

                        <div class="p-1 text-xs text-gray-600 dark:text-gray-300 truncate bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
                            {{ $item->name ?: 'Untitled' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Pagination --}}
        <div class="mb-4">
            {{ $mediaItems->links() }}
        </div>

        {{-- Confirm --}}
        <div class="flex justify-end border-t border-gray-200 dark:border-gray-700 pt-4">
            <x-filament::button
                wire:click="confirm"
                type="button"
                :disabled="empty($selectedIds)"
            >
                @if($multiple)
                    Select {{ count($selectedIds) }} Image(s)
                @else
                    Select Image
                @endif
            </x-filament::button>
        </div>

    @else
        {{-- Upload tab --}}
        <div class="space-y-4">
            @if($uploadSuccess)
                <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-400">
                    Image uploaded and selected. Switch to the Library tab to confirm.
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Name / Alt Text</label>
                <input
                    wire:model="newName"
                    type="text"
                    placeholder="Descriptive name for the image..."
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                @error('newName')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Folder <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span></label>
                <input
                    wire:model="newFolder"
                    type="text"
                    list="folder-suggestions"
                    placeholder="Select existing or type new folder..."
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                <datalist id="folder-suggestions">
                    @foreach($folderOptions as $f)
                        <option value="{{ $f }}">
                    @endforeach
                </datalist>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Image File</label>
                <input
                    wire:model="newFile"
                    type="file"
                    accept="image/*"
                    class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900/30 dark:file:text-primary-400"
                >
                @error('newFile')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div wire:loading wire:target="newFile" class="text-sm text-gray-500 dark:text-gray-400">
                Uploading...
            </div>

            <x-filament::button
                wire:click="saveUpload"
                type="button"
            >
                Upload & Add to Library
            </x-filament::button>
        </div>
    @endif
</div>
