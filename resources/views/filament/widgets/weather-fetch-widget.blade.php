{{-- Dashboard "Fetch all weather" button. Queues FetchAllWeatherJob. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Weather data</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Refresh historical weather for every destination. Runs in the background.
                </p>
            </div>
            <x-filament::button wire:click="fetchAll" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                Fetch all weather
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
