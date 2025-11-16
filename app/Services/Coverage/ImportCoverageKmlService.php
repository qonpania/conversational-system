<?php

namespace App\Services\Coverage;

use App\Models\CoverageZone;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class ImportCoverageKmlService
{
    /**
     * Procesa un archivo KML de cobertura y crea registros en coverage_zones.
     */
    public function handle(
        string $absolutePath,
        bool $replaceExisting = false,
        ?string $notes = null,
    ): array {
        if (! file_exists($absolutePath)) {
            throw new \RuntimeException("El archivo KML no existe en la ruta: {$absolutePath}");
        }

        Log::info('Importando archivo KML de cobertura', [
            'path'             => $absolutePath,
            'replace_existing' => $replaceExisting,
            'notes'            => $notes,
        ]);

        // Si quieres empezar “limpio”, borras todo
        if ($replaceExisting) {
            CoverageZone::truncate();
        }

        $content = file_get_contents($absolutePath);

        if ($content === false) {
            throw new \RuntimeException("No se pudo leer el archivo KML.");
        }

        $xml = simplexml_load_string($content);

        if (! $xml) {
            throw new \RuntimeException("El archivo KML no tiene un XML válido.");
        }

        // Namespaces típicos de KML
        $namespaces = $xml->getDocNamespaces();
        $kmlNs = $namespaces[''] ?? $namespaces['kml'] ?? 'http://www.opengis.net/kml/2.2';

        $kml = $xml->children($kmlNs);

        // Intentamos llegar a Document; algunos KML vienen directo sin Folder
        $document = $kml->Document ?? $xml->Document ?? null;

        $zonesCreated     = 0;  // registros insertados
        $polygonsCreated  = 0;  // polígonos procesados (1 por fila)
        $districtsSet     = []; // para contar únicos

        if ($document) {
            // Algunos KML tienen varios Folder, otros solo Placemark directo
            $folders = $document->Folder;
            if (count($folders) === 0) {
                // Tratamos Document como un “Folder” si no hay Folder explícito
                $folders = [$document];
            }

            foreach ($folders as $folder) {
                foreach ($folder->Placemark as $placemark) {
                    $this->processPlacemark($placemark, $kmlNs, $zonesCreated, $polygonsCreated, $districtsSet);
                }
            }
        }

        $districtsCount = count($districtsSet);

        return [
            'zones'     => $zonesCreated ?: null,
            'polygons'  => $polygonsCreated ?: null,
            'districts' => $districtsCount ?: null,
        ];
    }

    /**
     * Procesa un Placemark: extrae metadata y crea una CoverageZone por cada polígono.
     */
    protected function processPlacemark(
        SimpleXMLElement $placemark,
        string $kmlNs,
        int &$zonesCreated,
        int &$polygonsCreated,
        array &$districtsSet,
    ): void {
        // --- Metadata de ExtendedData ---
        $metadata = $this->extractMetadataFromPlacemark($placemark);

        $departamento = $metadata['DEPARTAMEN'] ?? null;
        $provincia    = $metadata['PROVINCIA'] ?? null;
        $distrito     = $metadata['DISTRITO'] ?? null;
        $score        = isset($metadata['Puntaje']) ? (float) $metadata['Puntaje'] : null;

        if ($distrito) {
            $districtsSet[$distrito] = true;
        }

        // Nombre “amigable” de la zona
        $name = null;
        if (isset($placemark->name)) {
            $name = trim((string) $placemark->name);
        }
        if (! $name) {
            $name = $distrito ?: 'Zona sin nombre';
        }

        // --- Polígonos (MultiGeometry / Polygon / coordinates) ---
        if (isset($placemark->MultiGeometry)) {
            foreach ($placemark->MultiGeometry->Polygon as $polygon) {
                $points = $this->extractPolygonPoints($polygon);
                if (empty($points)) {
                    continue;
                }

                CoverageZone::create([
                    'name'         => $name,
                    'departamento' => $departamento,
                    'provincia'    => $provincia,
                    'distrito'     => $distrito,
                    'score'        => $score,
                    'polygon'      => $points,   // se castea a JSON
                    'metadata'     => $metadata, // todo el ExtendedData crudo
                ]);

                $zonesCreated++;
                $polygonsCreated++;
            }
        } elseif (isset($placemark->Polygon)) {
            // Algunos KML no tienen MultiGeometry, solo Polygon directo
            $points = $this->extractPolygonPoints($placemark->Polygon);
            if (! empty($points)) {
                CoverageZone::create([
                    'name'         => $name,
                    'departamento' => $departamento,
                    'provincia'    => $provincia,
                    'distrito'     => $distrito,
                    'score'        => $score,
                    'polygon'      => $points,
                    'metadata'     => $metadata,
                ]);

                $zonesCreated++;
                $polygonsCreated++;
            }
        }
    }

    /**
     * Extrae todas las claves/valores de ExtendedData->Data.
     *
     * @return array<string, string|null>
     */
    protected function extractMetadataFromPlacemark(SimpleXMLElement $placemark): array
    {
        $metadata = [];

        if (! isset($placemark->ExtendedData)) {
            return $metadata;
        }

        foreach ($placemark->ExtendedData->Data as $dataNode) {
            $attrs = $dataNode->attributes();
            if (! isset($attrs['name'])) {
                continue;
            }

            $key = (string) $attrs['name'];
            $value = null;

            if (isset($dataNode->value)) {
                $value = trim((string) $dataNode->value);
            }

            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /**
     * Recibe un nodo <Polygon> y devuelve un array de puntos [ [lon, lat], ... ].
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function extractPolygonPoints(SimpleXMLElement $polygon): array
    {
        if (
            ! isset($polygon->outerBoundaryIs) ||
            ! isset($polygon->outerBoundaryIs->LinearRing) ||
            ! isset($polygon->outerBoundaryIs->LinearRing->coordinates)
        ) {
            return [];
        }

        $coordsText = trim((string) $polygon->outerBoundaryIs->LinearRing->coordinates);

        if ($coordsText === '') {
            return [];
        }

        $points = [];
        // Los KML suelen separar puntos por espacios o saltos de línea
        $pairs = preg_split('/\s+/', $coordsText);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            // formato típico: lon,lat,alt
            $parts = explode(',', $pair);
            if (count($parts) < 2) {
                continue;
            }

            $lon = (float) $parts[0];
            $lat = (float) $parts[1];

            $points[] = [$lon, $lat];
        }

        return $points;
    }
}
