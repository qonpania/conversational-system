<?php

namespace App\Jobs;

use App\Models\CoverageZone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCoverageKmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $relativePath,
        public ?int $userId = null,
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::disk('local')->path($this->relativePath);

        if (! file_exists($fullPath)) {
            Log::warning("[KML] Archivo no encontrado: {$fullPath}");
            return;
        }

        $xml = simplexml_load_file($fullPath);

        if (! $xml) {
            Log::error('[KML] No se pudo parsear el archivo.');
            return;
        }

        // Registrar namespace KML
        $xml->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');

        $placemarks = $xml->xpath('//k:Placemark');

        if (! $placemarks) {
            Log::warning('[KML] No se encontraron placemarks.');
            return;
        }

        // Opcional: limpiar tabla antes de importar nueva versión
        // CoverageZone::truncate();

        foreach ($placemarks as $pm) {
            try {
                // ===== 1) ExtendedData -> metadata =====
                $metadata = [];

                if (isset($pm->ExtendedData->Data)) {
                    foreach ($pm->ExtendedData->Data as $data) {
                        $name = (string) $data['name'];
                        $value = (string) $data->value;
                        $metadata[$name] = $value;
                    }
                }

                // ===== 2) Polygon coordinates =====
                $coordsNodes = $pm->xpath('.//k:Polygon/k:outerBoundaryIs/k:LinearRing/k:coordinates');

                if (! $coordsNodes || ! isset($coordsNodes[0])) {
                    continue;
                }

                $coordsStr = trim((string) $coordsNodes[0]);
                if ($coordsStr === '') {
                    continue;
                }

                $coords = collect(explode(' ', $coordsStr))
                    ->filter()
                    ->map(function (string $pair) {
                        [$lon, $lat] = explode(',', $pair);
                        return [
                            (float) $lon,
                            (float) $lat,
                        ];
                    })
                    ->values()
                    ->all();

                if (count($coords) < 3) {
                    continue; // no es un polígono válido
                }

                // ===== 3) Datos "bonitos" para la UI =====
                $departamento = $metadata['DEPARTAMEN'] ?? null;
                $provincia    = $metadata['PROVINCIA'] ?? null;
                $distrito     = $metadata['DISTRITO'] ?? null;
                $score        = isset($metadata['Puntaje'])
                    ? (float) $metadata['Puntaje']
                    : null;

                $name = $metadata['Grupo']
                    ?? $distrito
                    ?? 'Zona sin nombre';

                CoverageZone::create([
                    'name'         => $name,
                    'departamento' => $departamento,
                    'provincia'    => $provincia,
                    'distrito'     => $distrito,
                    'score'        => $score,
                    'polygon'      => $coords,
                    'metadata'     => $metadata,
                ]);
            } catch (\Throwable $e) {
                Log::error('[KML] Error procesando Placemark: '.$e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('[KML] Procesamiento finalizado.');
    }
}
