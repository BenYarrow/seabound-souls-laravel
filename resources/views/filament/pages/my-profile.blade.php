{{-- Self-service "My Profile" page: shows the live/not-yet-live notice, then the
     shared contributor profile form (images, socials, story blocks) and a save button. --}}
<x-filament-panels::page>
    @if (! auth()->user()->hasPublicProfile())
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Your public profile isn't live yet.</strong>
                It goes live automatically once you have at least one <em>published</em> spot guide —
                so build a guide, get it approved, and your profile (and self-promotion) switches on.
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="text-sm text-green-700 dark:text-green-400">
                Your profile is <strong>live</strong> at
                <a class="underline" href="{{ url('/contributors/'.auth()->user()->slug) }}" target="_blank">/contributors/{{ auth()->user()->slug }}</a>.
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">Save profile</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
