{{-- Dashboard "Fetch all weather" button + status line (last refresh / in-progress). --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Weather data</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Refresh historical weather for every destination. Runs in the background.
                </p>

                @php($pendingCount = $this->getPendingCount())
                @php($lastUpdatedAt = $this->getLastUpdatedAt())

                <div class="mt-2 flex items-center gap-2 text-sm">
                    @if ($pendingCount > 0)
                        <x-filament::loading-indicator class="h-4 w-4 text-primary-500" />
                        <span class="text-primary-600 dark:text-primary-400">
                            {{ $pendingCount }} weather {{ \Illuminate\Support\Str::plural('fetch', $pendingCount) }} in progress…
                        </span>
                    @elseif ($lastUpdatedAt)
                        <span class="text-gray-500 dark:text-gray-400">Last updated {{ $lastUpdatedAt }}.</span>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">No weather data fetched yet.</span>
                    @endif
                </div>
            </div>
            <x-filament::button wire:click="fetchAll" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                Fetch all weather
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
