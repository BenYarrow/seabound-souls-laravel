<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $statePath = $getStatePath();
        $isMultiple = $field->isMultiple();
        $state = $getState();

        if ($isMultiple) {
            $ids = is_array($state) ? array_filter($state) : [];
            $selectedItems = !empty($ids)
                ? \App\Models\MediaLibrary::whereIn('id', $ids)->get()->keyBy('id')
                : collect();
        } else {
            $selectedItem = $state ? \App\Models\MediaLibrary::find((int) $state) : null;
        }
    @endphp

    <div
        x-data="{}"
        x-on:media-library-selected.window="
            if ($event.detail.fieldKey === '{{ $statePath }}') {
                @if($isMultiple)
                    $wire.set('{{ $statePath }}', $event.detail.ids);
                @else
                    $wire.set('{{ $statePath }}', $event.detail.ids.length > 0 ? $event.detail.ids[0] : null);
                @endif
                $wire.unmountAction();
            }
        "
    >
        @if(!$isMultiple && isset($selectedItem) && $selectedItem)
            {{-- Compact preview card: constrained 16:9 thumbnail + readable name --}}
            <div class="mb-3 w-64 max-w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="relative">
                    <img
                        src="{{ $selectedItem->getUrl() }}"
                        alt="{{ $selectedItem->name }}"
                        class="aspect-video w-full object-cover"
                    >
                    <button
                        type="button"
                        x-on:click="$wire.set('{{ $statePath }}', null)"
                        class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-sm leading-none text-white hover:bg-red-600"
                        title="Remove"
                    >&times;</button>
                </div>
                <p class="truncate px-3 py-2 text-sm text-gray-700 dark:text-gray-200" title="{{ $selectedItem->name }}">
                    {{ $selectedItem->name ?: 'Untitled' }}
                </p>
            </div>
        @elseif($isMultiple && isset($selectedItems) && $selectedItems->isNotEmpty())
            <div
                class="flex flex-wrap gap-2 mb-3"
                x-sortable
                x-on:end="$wire.set('{{ $statePath }}', $event.target.sortable.toArray().map(Number))"
            >
                @foreach($ids as $id)
                    @if($item = $selectedItems->get($id))
                        <div
                            class="relative group"
                            x-sortable-item="{{ $id }}"
                        >
                            <img
                                src="{{ $item->getUrl() }}"
                                alt="{{ $item->name }}"
                                class="w-20 h-16 object-cover rounded border border-gray-200 cursor-grab active:cursor-grabbing"
                                x-sortable-handle
                            >
                            <button
                                type="button"
                                x-on:click="
                                    let current = $wire.get('{{ $statePath }}') || [];
                                    $wire.set('{{ $statePath }}', current.filter(id => id !== {{ $id }}));
                                "
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs leading-none hover:bg-red-600 opacity-0 group-hover:opacity-100 transition-opacity"
                                title="Remove"
                            >&times;</button>
                        </div>
                    @endif
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mb-3">{{ count($ids) }} image(s) selected &mdash; drag to reorder</p>
        @endif

        <x-filament::button
            color="gray"
            size="sm"
            type="button"
            x-on:click="$wire.mountFormComponentAction('{{ $statePath }}', 'browse')"
        >
            @if((!$isMultiple && isset($selectedItem) && $selectedItem) || ($isMultiple && isset($selectedItems) && $selectedItems->isNotEmpty()))
                Change
            @else
                Browse Library
            @endif
        </x-filament::button>
    </div>
</x-dynamic-component>
