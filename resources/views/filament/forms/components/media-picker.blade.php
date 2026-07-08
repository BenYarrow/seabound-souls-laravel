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
            {{-- Preview card: constrained 16:9 thumbnail + focal-point click-to-set + full (wrapping) name --}}
            <div class="mb-3 w-96 max-w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="relative">
                    {{-- Focal-point click-to-set: click anywhere on the image to store that point.
                         fx/fy are initialised from the persisted values (defaulting to 50/50 centre)
                         and then updated live on each click. The img object-position mirrors the
                         stored focal point so the editor sees the effect immediately. --}}
                    <div
                        x-data="{ fx: {{ $selectedItem->focal_x ?? 50 }}, fy: {{ $selectedItem->focal_y ?? 50 }} }"
                        class="relative cursor-crosshair"
                        x-on:click="
                            const r = $el.getBoundingClientRect();
                            fx = Math.round((($event.clientX - r.left) / r.width) * 100);
                            fy = Math.round((($event.clientY - r.top) / r.height) * 100);
                            fetch('{{ route('admin.media.focal', $selectedItem->id) }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify({ x: fx, y: fy }),
                            });
                        "
                    >
                        <img src="{{ $selectedItem->getUrl() }}" alt="{{ $selectedItem->name }}" class="aspect-video w-full object-cover" :style="`object-position: ${fx}% ${fy}%`">
                        <span class="pointer-events-none absolute h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-500/70 shadow" :style="`left: ${fx}%; top: ${fy}%`"></span>
                    </div>
                    <button
                        type="button"
                        x-on:click="$wire.set('{{ $statePath }}', null)"
                        class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-base leading-none text-white hover:bg-red-600"
                        title="Remove"
                    >&times;</button>
                </div>
                <p class="px-3 py-2.5 text-sm leading-snug text-gray-700 dark:text-gray-200">
                    {{ $selectedItem->name ?: 'Untitled' }}
                </p>
                <p class="px-3 pb-2 text-xs text-gray-500 dark:text-gray-400">Click the photo to set its focal point.</p>
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
