<x-filament-panels::page>
    @php
        /** @var \App\Models\CoverageZone $record */
        $polygon = $record->polygon ?? []; // lo que guardamos en BD: [[lon, lat], ...]
        // Leaflet espera [lat, lng], así que invertimos cada par
        $leafletPoints = collect($polygon)
            ->map(fn ($p) => [$p[1] ?? null, $p[0] ?? null])  // [lat, lng]
            ->filter(fn ($p) => $p[0] !== null && $p[1] !== null)
            ->values()
            ->all();
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Mapa de la zona de cobertura
        </x-slot>

        <x-slot name="description">
            Visualiza el polígono de cobertura asociado a esta zona usando el mapa interactivo.
        </x-slot>

        <div class="mt-4 space-y-3">
            {{-- Contenedor del mapa: altura fija para que se vea SIEMPRE --}}
            <div
                id="coverage-map"
                class="w-full rounded-xl border border-gray-700/60 bg-gray-900/80 overflow-hidden"
                style="height: 420px;"
            ></div>

            <p class="text-xs text-gray-400">
                Polígono almacenado como [{{ count($leafletPoints) }} puntos]. Usa el zoom y arrastra para revisar con detalle.
            </p>
        </div>
    </x-filament::section>

    @push('styles')
        {{-- Leaflet CSS desde CDN --}}
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin=""
        />
    @endpush

    @push('scripts')
        {{-- Leaflet JS desde CDN --}}
        <script
            src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""
        ></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const points = @json($leafletPoints);

                // Si no hay puntos, no hacemos nada
                if (!Array.isArray(points) || points.length === 0) {
                    return;
                }

                // Verificamos que Leaflet (L) esté disponible
                if (typeof L === 'undefined') {
                    console.error('Leaflet no está cargado.');
                    return;
                }

                // Crear mapa
                const map = L.map('coverage-map', {
                    scrollWheelZoom: true,
                });

                // Capa base (OpenStreetMap)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                // Dibujar polígono
                const polygon = L.polygon(points, {
                    color: '#38bdf8',        // azul cian
                    weight: 2,
                    fillColor: '#22c55e',    // verde suave
                    fillOpacity: 0.25,
                }).addTo(map);

                // Ajustar el mapa para que el polígono se vea completo
                map.fitBounds(polygon.getBounds());
            });
        </script>
    @endpush
</x-filament-panels::page>
