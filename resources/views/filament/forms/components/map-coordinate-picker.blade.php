{{--
    Map-click coordinate picker: renders a "Pick on map" button that opens a
    Mapbox modal. Clicking the map picks a point; "Use this point" writes the
    coordinates into this field's sibling latitude/longitude fields via
    $wire.set. The field itself stores nothing (dehydrated(false) on the
    backing MapCoordinatePicker class).
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $latPath = $field->getLatitudePath();
        $lngPath = $field->getLongitudePath();
        $mapboxToken = config('services.mapbox.token');
    @endphp

    {{-- Alpine component: lazy-loads mapbox-gl from CDN the first time the modal
         opens, renders a clickable map, and writes the picked coordinates back
         into the sibling latitude/longitude fields via $wire.set. --}}
    <div
        x-data="mapCoordinatePicker({
            token: @js($mapboxToken),
            latPath: @js($latPath),
            lngPath: @js($lngPath),
        })"
    >
        <x-filament::button type="button" color="gray" size="sm" x-on:click="open()">
            Pick on map
        </x-filament::button>

        <div x-show="showModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             x-on:click.self="close()">
            <div class="w-full max-w-2xl rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Click the map to set the location</p>
                    <button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="close()">&times;</button>
                </div>
                <div x-ref="map" class="h-80 w-full rounded-lg"></div>
                <p class="mt-2 text-xs text-gray-500" x-text="picked ? `Selected: ${picked.lat.toFixed(5)}, ${picked.lng.toFixed(5)}` : 'No point selected yet.'"></p>
                <div class="mt-3 flex justify-end gap-2">
                    <x-filament::button type="button" color="gray" size="sm" x-on:click="close()">Cancel</x-filament::button>
                    <x-filament::button type="button" size="sm" x-on:click="confirm()" x-bind:disabled="!picked">Use this point</x-filament::button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Registered once; each field instance gets its own Alpine data object.
        window.mapCoordinatePicker = window.mapCoordinatePicker || function (config) {
            return {
                showModal: false,
                map: null,
                marker: null,
                picked: null,
                /** Ensure mapbox-gl (JS + CSS) is loaded, once, before first use. */
                async ensureMapbox() {
                    if (window.mapboxgl) return;
                    // Cache the in-flight load promise so concurrent/re-entrant calls (e.g. close+reopen
                    // before the first load resolves) await the same load instead of injecting duplicate tags.
                    if (!window.__mapboxGlLoading) {
                        window.__mapboxGlLoading = new Promise((resolve, reject) => {
                            const css = document.createElement('link');
                            css.rel = 'stylesheet';
                            css.href = 'https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.css';
                            document.head.appendChild(css);
                            const js = document.createElement('script');
                            js.src = 'https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.js';
                            js.onload = resolve;
                            js.onerror = reject;
                            document.head.appendChild(js);
                        }).catch((error) => {
                            // A failed CDN load (offline, blocked, etc.) would otherwise leave a
                            // permanently-rejected promise cached, so every later open() would
                            // reject immediately without ever retrying the load. Clear the cache
                            // so the next open() attempts a fresh load, while still propagating
                            // this attempt's failure to its caller.
                            window.__mapboxGlLoading = null;
                            throw error;
                        });
                    }
                    await window.__mapboxGlLoading;
                },
                /** Open the modal and (re)initialise the map centred on current coords. */
                async open() {
                    this.showModal = true;
                    await this.ensureMapbox();
                    window.mapboxgl.accessToken = config.token;
                    const parsedLat = parseFloat(this.$wire.get(config.latPath));
                    const parsedLng = parseFloat(this.$wire.get(config.lngPath));
                    // Checked against the raw parse, before the display fallbacks below are
                    // applied, so 20/0 (just map defaults, not real data) aren't mistaken for
                    // an existing coordinate.
                    const hasExistingCoords = Number.isFinite(parsedLat) && Number.isFinite(parsedLng);
                    const lat = parsedLat || 20;
                    const lng = parsedLng || 0;
                    this.$nextTick(() => {
                        this.map = new window.mapboxgl.Map({
                            container: this.$refs.map,
                            style: 'mapbox://styles/mapbox/outdoors-v12',
                            center: [lng, lat],
                            zoom: hasExistingCoords ? 10 : 1.5,
                        });
                        this.map.on('click', (event) => {
                            this.picked = event.lngLat;
                            if (this.marker) this.marker.remove();
                            this.marker = new window.mapboxgl.Marker().setLngLat(event.lngLat).addTo(this.map);
                        });
                    });
                },
                /** Write the picked point into the sibling lat/lng fields. */
                confirm() {
                    if (!this.picked) return;
                    this.$wire.set(config.latPath, Number(this.picked.lat.toFixed(7)));
                    this.$wire.set(config.lngPath, Number(this.picked.lng.toFixed(7)));
                    this.close();
                },
                close() {
                    this.showModal = false;
                    if (this.map) { this.map.remove(); this.map = null; }
                    this.marker = null;
                    this.picked = null;
                },
            };
        };
    </script>
</x-dynamic-component>
