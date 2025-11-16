<x-filament-panels::page>
    <form wire:submit.prevent="submit" class="space-y-6">
        {{-- Bloque principal de subida --}}
        <x-filament::section>
            <x-slot name="heading">
                Subir archivo KML de cobertura
            </x-slot>

            <x-slot name="description">
                Carga el archivo KML con las zonas de cobertura para que el agente pueda validar
                coordenadas de latitud/longitud en tiempo real.
            </x-slot>

            <div class="space-y-4">
                {{-- Campos del formulario Filament --}}
                {{ $this->form }}
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <x-filament::button
                    type="button"
                    color="gray"
                    outlined
                    wire:click="resetForm"
                >
                    Limpiar formulario
                </x-filament::button>

                <x-filament::button
                    type="submit"
                >
                    Procesar KML
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Resumen del último import --}}
        @if ($this->lastImportSummary)
            <x-filament::section>
                <x-slot name="heading">
                    Última importación de cobertura
                </x-slot>

                <div class="grid gap-4 md:grid-cols-3">
                    {{-- Zonas --}}
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 dark:bg-gray-900/60 dark:border-gray-800 p-4 shadow-sm">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Zonas de cobertura
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->lastImportSummary['zones'] ?? '–' }}
                        </div>
                    </div>

                    {{-- Polígonos --}}
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 dark:bg-gray-900/60 dark:border-gray-800 p-4 shadow-sm">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Polígonos procesados
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->lastImportSummary['polygons'] ?? '–' }}
                        </div>
                    </div>

                    {{-- Distritos --}}
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 dark:bg-gray-900/60 dark:border-gray-800 p-4 shadow-sm">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Distritos únicos
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->lastImportSummary['districts'] ?? '–' }}
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </form>
</x-filament-panels::page>
